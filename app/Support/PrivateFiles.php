<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Files that must not be reachable by a public address: student photos,
 * the head teacher's signature and finance receipts. They live on the
 * private 'uploads' disk and are only ever shown through signed links
 * that expire, handed out to someone already signed in.
 *
 * School logos are not here -- a logo is public by nature.
 */
class PrivateFiles
{
    public const DISK = 'uploads';

    /** How long a link handed to the browser keeps working. */
    public const LINK_MINUTES = 30;

    /**
     * A signed, expiring link to the file, or null when there is none.
     */
    public static function url(?string $path): ?string
    {
        if (blank($path) || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes(self::LINK_MINUTES));
    }

    /**
     * The file as a data: URI, for PDFs (dompdf cannot fetch signed links).
     */
    public static function dataUri(?string $path): ?string
    {
        if (blank($path) || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        try {
            $disk = Storage::disk(self::DISK);

            return 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path));
        } catch (Throwable) {
            return null;
        }
    }
}
