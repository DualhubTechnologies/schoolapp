<?php

namespace App\Support;

use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Filesystem\Filesystem;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Makes uploaded pictures (logo, signature, student photo) a sensible size
 * on the server, after upload.
 *
 * Resizing used to happen in the browser, before upload. On iPhones that
 * silently hangs for large pictures — the upload spins forever with no
 * message — so the phone now sends the original and it is shrunk here.
 * Anything that cannot be read (an unusual format, a picture too large to
 * open safely) is kept as uploaded rather than failing the save.
 */
class ImageShrinker
{
    /** Larger pictures are stored as uploaded, to stay within PHP's memory. */
    public const MAX_PIXELS = 40_000_000;

    /**
     * A FileUpload save callback: store the file as Filament normally
     * would, then shrink it to fit within the given box ($square: cut the
     * middle square out first, as for a logo or passport photo).
     */
    public static function saveWithin(int $maxWidth, int $maxHeight, bool $square = false): \Closure
    {
        return static function (BaseFileUpload $component, TemporaryUploadedFile $file) use ($maxWidth, $maxHeight, $square): ?string {
            $path = $component->saveUploadedFile($file);

            if ($path !== null) {
                static::shrink($component->getDisk(), $path, $maxWidth, $maxHeight, $square);
            }

            return $path;
        };
    }

    /**
     * Turn off Filament's in-browser crop and resize on an upload field
     * (avatar() switches them on), so phones send the picture as it is.
     */
    public static function noBrowserResize(FileUpload $field): FileUpload
    {
        return $field
            // avatar() also demands an already-square picture; the square
            // is cut on the server instead (saveWithin(..., square: true)).
            ->imageAspectRatio(null)
            ->automaticallyCropImagesToAspectRatio(false)
            ->automaticallyResizeImagesToWidth(null)
            ->automaticallyResizeImagesToHeight(null)
            ->automaticallyResizeImagesMode(null);
    }

    /**
     * Shrink the picture at $path to fit within $maxWidth x $maxHeight,
     * keeping its shape, format and transparency. Smaller pictures are
     * left untouched.
     */
    public static function shrink(Filesystem $disk, string $path, int $maxWidth, int $maxHeight, bool $square = false): bool
    {
        try {
            $contents = $disk->get($path);
            $info = is_string($contents) ? @getimagesizefromstring($contents) : false;

            if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return false;
            }

            [$width, $height] = $info;
            $turned = $info['mime'] === 'image/jpeg' ? static::orientation($contents) : 1;

            // A phone photo taken sideways is stored sideways with a note
            // to turn it; the box applies to the picture as it is seen.
            [$seenWidth, $seenHeight] = in_array($turned, [5, 6, 7, 8], true) ? [$height, $width] : [$width, $height];
            $cropped = $square && $seenWidth !== $seenHeight;
            $side = min($seenWidth, $seenHeight);
            $scale = $cropped
                ? min($maxWidth / $side, $maxHeight / $side, 1)
                : min($maxWidth / $seenWidth, $maxHeight / $seenHeight, 1);

            if (($scale >= 1 && $turned === 1 && ! $cropped) || $width * $height > self::MAX_PIXELS) {
                return false;
            }

            static::allowMemory();

            $source = @imagecreatefromstring($contents);

            if ($source === false) {
                return false;
            }

            $source = static::upright($source, $turned);

            // The part of the picture kept: all of it, or its middle square.
            $fromWidth = $cropped ? $side : imagesx($source);
            $fromHeight = $cropped ? $side : imagesy($source);
            $fromX = intdiv(imagesx($source) - $fromWidth, 2);
            $fromY = intdiv(imagesy($source) - $fromHeight, 2);

            $newWidth = max(1, (int) round($fromWidth * $scale));
            $newHeight = max(1, (int) round($fromHeight * $scale));

            $resized = imagecreatetruecolor($newWidth, $newHeight);

            if ($info['mime'] !== 'image/jpeg') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagefill($resized, 0, 0, (int) imagecolorallocatealpha($resized, 0, 0, 0, 127));
            }

            imagecopyresampled($resized, $source, 0, 0, $fromX, $fromY, $newWidth, $newHeight, $fromWidth, $fromHeight);

            ob_start();
            match ($info['mime']) {
                'image/png' => imagepng($resized, null, 6),
                'image/webp' => imagewebp($resized, null, 85),
                default => imagejpeg($resized, null, 85),
            };
            $shrunk = (string) ob_get_clean();

            return $shrunk !== '' && $disk->put($path, $shrunk);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * The EXIF orientation of a JPEG (1 = upright), when PHP can read it.
     */
    protected static function orientation(string $jpeg): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($jpeg));

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    /**
     * Turn a picture the way its EXIF orientation says it should be seen.
     */
    protected static function upright(\GdImage $image, int $orientation): \GdImage
    {
        $turned = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($turned !== false && in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($turned, IMG_FLIP_HORIZONTAL);
        }

        return $turned === false ? $image : $turned;
    }

    /**
     * Opening a large phone photo needs more memory than PHP's usual 128 MB.
     */
    protected static function allowMemory(): void
    {
        $limit = (string) ini_get('memory_limit');

        if ($limit !== '-1' && (int) $limit < 512 && str_ends_with(strtoupper($limit), 'M')) {
            @ini_set('memory_limit', '512M');
        }
    }
}
