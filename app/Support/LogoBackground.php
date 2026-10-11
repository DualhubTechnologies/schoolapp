<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

/**
 * Takes the white (or near-white) background off an uploaded logo, so it
 * sits cleanly on report cards, receipts, ID cards and the app's coloured
 * bars instead of showing as a white box.
 *
 * Only the background is removed: the white that reaches the edge of the
 * picture and everything light joined to it. White inside the crest that
 * is enclosed by a line or colour stays. A picture whose edge is not
 * mostly white (a photo, a logo already on a coloured or transparent
 * background) is left alone. The result is saved as PNG, which can be
 * see-through; a JPEG cannot.
 */
class LogoBackground
{
    /** A pixel this light and this grey counts as background. */
    protected const LIGHT = 225;

    protected const GREYNESS = 30;

    /** Pixels lighter than this beside the background fade into it (smooth edges). */
    protected const FADE_FROM = 170;

    /** Share of the edge that must be background before anything is removed. */
    protected const EDGE_SHARE = 0.6;

    /**
     * Clear the background of the picture at $path. Returns the path the
     * cleaned picture was saved under (a .png beside the original), or the
     * original path when nothing was changed.
     */
    public static function clear(Filesystem $disk, string $path): string
    {
        try {
            $contents = $disk->get($path);
            $info = is_string($contents) ? @getimagesizefromstring($contents) : false;

            if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
                || $info[0] * $info[1] > ImageShrinker::MAX_PIXELS) {
                return $path;
            }

            $image = @imagecreatefromstring($contents);

            if ($image === false) {
                return $path;
            }

            $cleaned = static::clearImage($image);

            if ($cleaned === null) {
                return $path;
            }

            ob_start();
            imagepng($cleaned, null, 6);
            $png = (string) ob_get_clean();

            if ($png === '') {
                return $path;
            }

            $newPath = preg_replace('/\.(jpe?g|png|webp)$/i', '', $path).'.png';
            $disk->put($newPath, $png);

            if ($newPath !== $path) {
                $disk->delete($path);
            }

            return $newPath;
        } catch (Throwable $e) {
            report($e);

            return $path;
        }
    }

    /**
     * The picture with its background made see-through, or null when its
     * edge is not a light background (nothing to do).
     */
    public static function clearImage(\GdImage $source): ?\GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);

        // Which pixels look like background, and where to start: the edge.
        $edge = [];
        $edgeLight = 0;

        for ($x = 0; $x < $width; $x++) {
            $edge[] = [$x, 0];
            $edge[] = [$x, $height - 1];
        }

        for ($y = 1; $y < $height - 1; $y++) {
            $edge[] = [0, $y];
            $edge[] = [$width - 1, $y];
        }

        foreach ($edge as [$x, $y]) {
            $edgeLight += static::isBackground($image, $x, $y) ? 1 : 0;
        }

        if ($edgeLight < count($edge) * self::EDGE_SHARE) {
            return null;
        }

        // Flood in from the edge through every light pixel joined to it.
        $removed = [];
        $queue = array_values(array_filter($edge, fn (array $p): bool => static::isBackground($image, $p[0], $p[1])));
        $transparent = (int) imagecolorallocatealpha($image, 255, 255, 255, 127);

        while ($queue !== []) {
            [$x, $y] = array_pop($queue);
            $key = $y * $width + $x;

            if (isset($removed[$key])) {
                continue;
            }

            $removed[$key] = true;
            imagesetpixel($image, $x, $y, $transparent);

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx >= 0 && $ny >= 0 && $nx < $width && $ny < $height
                    && ! isset($removed[$ny * $width + $nx]) && static::isBackground($image, $nx, $ny)) {
                    $queue[] = [$nx, $ny];
                }
            }
        }

        // Soften the outline: light pixels touching the background fade.
        foreach (array_keys($removed) as $key) {
            $x = $key % $width;
            $y = intdiv($key, $width);

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx < 0 || $ny < 0 || $nx >= $width || $ny >= $height || isset($removed[$ny * $width + $nx])) {
                    continue;
                }

                $rgba = imagecolorat($image, $nx, $ny);
                [$r, $g, $b] = [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];
                $light = min($r, $g, $b);

                if ($light > self::FADE_FROM && max($r, $g, $b) - $light <= self::GREYNESS * 2) {
                    $alpha = (int) round(($light - self::FADE_FROM) / (255 - self::FADE_FROM) * 110);
                    imagesetpixel($image, $nx, $ny, (int) imagecolorallocatealpha($image, $r, $g, $b, max(((int) ($rgba >> 24) & 0x7F), $alpha)));
                }
            }
        }

        return $image;
    }

    protected static function isBackground(\GdImage $image, int $x, int $y): bool
    {
        $rgba = imagecolorat($image, $x, $y);

        if ((($rgba >> 24) & 0x7F) >= 120) {
            return true;    // already see-through
        }

        [$r, $g, $b] = [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];

        return min($r, $g, $b) >= self::LIGHT && max($r, $g, $b) - min($r, $g, $b) <= self::GREYNESS;
    }
}
