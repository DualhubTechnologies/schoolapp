<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Turn a file on the public disk (school logos, branding) into a base64
 * data URI, for dompdf -- it cannot fetch web URLs reliably and http
 * fetching is disabled by default for security. Private files (student
 * photos, signatures) go through PrivateFiles::dataUri() instead.
 */
trait EmbedsImages
{
    protected function embeddableImage(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        try {
            $contents = Storage::disk('public')->get($path);
            $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        } catch (Throwable) {
            return null;
        }
    }
}
