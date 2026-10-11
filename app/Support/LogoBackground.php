<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

/**
 * Takes the white (or near-white) background off an uploaded logo or
 * signature, so it
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
    /** A pixel this light on every channel counts as background. */
    protected const LIGHT = 225;

    /** How far from grey a light pixel beside the background may be and still fade. */
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
    public static function clear(Filesystem $disk, string $path, bool $paper = false): string
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

            $cleaned = $paper ? static::clearPaper($image) : static::clearImage($image);

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
                    imagesetpixel($image, $nx, $ny, (int) imagecolorallocatealpha($image, $r, $g, $b, min(127, max(($rgba >> 24) & 0x7F, $alpha))));
                }
            }
        }

        return $image;
    }

    /**
     * A signature (ink on paper, often photographed): every pixel close to
     * the paper's colour becomes see-through, wherever it is (inside the
     * loops of letters too), and the ink keeps its colour with smooth
     * edges. Null when the edge is not light paper.
     */
    public static function clearPaper(\GdImage $source): ?\GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        // The paper's brightness: the middle of the edge pixels (a phone
        // photo's paper is often grey, not white).
        $edge = [];
        $step = max(1, intdiv($width + $height, 400));

        for ($x = 0; $x < $width; $x += $step) {
            $edge[] = static::lightness($source, $x, 0);
            $edge[] = static::lightness($source, $x, $height - 1);
        }

        for ($y = 0; $y < $height; $y += $step) {
            $edge[] = static::lightness($source, 0, $y);
            $edge[] = static::lightness($source, $width - 1, $y);
        }

        sort($edge);
        $paperLight = $edge[intdiv(count($edge), 2)];

        if ($paperLight < 150) {
            return null;
        }

        // Up to 20 darker than the paper is paper; 90 darker or more is ink.
        $clearFrom = $paperLight - 20;
        $solidAt = $paperLight - 90;

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                [$r, $g, $b] = [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];
                $light = static::lightness($source, $x, $y);

                $alpha = match (true) {
                    $light >= $clearFrom => 127,
                    $light <= $solidAt => 0,
                    default => (int) round(($light - $solidAt) / ($clearFrom - $solidAt) * 127),
                };

                imagesetpixel($image, $x, $y, (int) imagecolorallocatealpha($image, $r, $g, $b, min(127, max($alpha, ($rgba >> 24) & 0x7F))));
            }
        }

        return $image;
    }

    protected static function lightness(\GdImage $image, int $x, int $y): int
    {
        $rgba = imagecolorat($image, $x, $y);

        return (int) round(0.299 * (($rgba >> 16) & 0xFF) + 0.587 * (($rgba >> 8) & 0xFF) + 0.114 * ($rgba & 0xFF));
    }

    protected static function isBackground(\GdImage $image, int $x, int $y): bool
    {
        $rgba = imagecolorat($image, $x, $y);

        if ((($rgba >> 24) & 0x7F) >= 120) {
            return true;    // already see-through
        }

        [$r, $g, $b] = [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];

        // At least this light on every channel is near-white (and so grey).
        return min($r, $g, $b) >= self::LIGHT;
    }
}
