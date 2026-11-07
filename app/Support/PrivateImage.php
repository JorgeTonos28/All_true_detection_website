<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PrivateImage
{
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    // Call only after authorizing the parent order AND any result publication/payment gate.
    public static function response($document)
    {
        abort_unless(in_array($document->mime_type, self::TYPES, true), 415, 'Solo las imágenes JPG, PNG y WebP admiten vista previa.');
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, $document->file_name, [
            'Content-Type' => $document->mime_type, 'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff', 'X-Content-SHA256' => $document->sha256,
        ], 'inline');
    }
}
