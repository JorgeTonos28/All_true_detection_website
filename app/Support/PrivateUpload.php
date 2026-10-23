<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PrivateUpload
{
    public static function validate(UploadedFile $file): void
    {
        Validator::make(['file' => $file], ['file' => self::rules()])->validate();
        $binary = config('portal.document_scan_binary');
        if (! $binary) {
            abort_if(app()->isProduction(), 503, 'El escáner de documentos no está configurado.');

            return;
        }
        try {
            $result = Process::timeout(30)->run([$binary, '--no-summary', '--', $file->getPathname()]);
        } catch (\Throwable) {
            abort(503, 'El escáner de documentos no está disponible.');
        }
        if ($result->exitCode() === 1) {
            throw ValidationException::withMessages(['file' => 'El documento fue rechazado por el control antivirus.']);
        }
        abort_unless($result->successful(), 503, 'El escáner de documentos no está disponible.');
    }

    public static function rules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:10240'];
    }
}
