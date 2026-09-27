<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Add to home screen": the web app manifest and its icons. For a signed-in
 * school user both carry the school's own name and logo, so the phone
 * shows their school, not SchoolHub; everyone else gets SchoolHub's.
 */
class AppInstallController extends Controller
{
    public const ICON_SIZES = [180, 192, 512];

    public function manifest(): JsonResponse
    {
        $school = $this->school();
        $name = $school->name ?? 'SchoolHub';

        return response()->json([
            'name' => $name,
            'short_name' => $school ? mb_strimwidth($school->name, 0, 14, '…') : 'SchoolHub',
            'description' => 'School fees, marks, report cards and payroll.',
            'id' => '/dashboard',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f4f6fa',
            'theme_color' => '#0d1f38',
            'icons' => collect([192, 512])->map(fn (int $size) => [
                'src' => route('filament.app.app.icon', ['size' => $size]),
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'any',
            ])->all(),
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function icon(int $size): Response|BinaryFileResponse
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);

        $logo = $this->school()?->logo;
        $disk = Storage::disk('public');

        if ($logo && $disk->exists($logo) && ($png = $this->squareIcon($disk->path($logo), $size))) {
            return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
        }

        return response()->file(public_path($size === 512 ? 'images/schoolhub-icon-512.png' : 'images/schoolhub-icon-192.png'), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    protected function school(): ?School
    {
        return auth()->user()?->school;
    }

    /**
     * The logo centred on white with a margin, as a square PNG: home screen
     * icons are cut to a circle or rounded square on many phones.
     *
     * @param  int<1, max>  $size  one of ICON_SIZES
     */
    protected function squareIcon(string $path, int $size): ?string
    {
        $contents = @file_get_contents($path);
        $source = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $source) {
            return null;
        }

        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));

        $inner = (int) round($size * 0.78);
        $scale = min($inner / imagesx($source), $inner / imagesy($source));
        $width = (int) round(imagesx($source) * $scale);
        $height = (int) round(imagesy($source) * $scale);

        imagecopyresampled($canvas, $source, intdiv($size - $width, 2), intdiv($size - $height, 2), 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }
}
