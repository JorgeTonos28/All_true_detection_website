<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PrivateUpload
{
    public static function validate(UploadedFile $file, float $scanTimeout = 30): void
    {
        Validator::make(['file' => $file], ['file' => self::rules()])->validate();
        self::validateContents($file);
        $binary = config('portal.document_scan_binary');
        if (! $binary) {
            abort_if(app()->isProduction(), 503, 'El escáner de documentos no está configurado.');

            return;
        }
        try {
            $result = Process::timeout($scanTimeout)->run([$binary, '--no-summary', '--', $file->getPathname()]);
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
        return [$required ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx,zip,jpg,jpeg,png,webp', 'extensions:pdf,doc,docx,jpg,jpeg,png,webp', 'max:'.config('portal.document_max_kb')];
    }

    private static function validateContents(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeTypes = [
            'pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'], 'webp' => ['image/webp'], 'doc' => ['application/msword', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'],
        ];
        if (! in_array($file->getMimeType(), $mimeTypes[$extension] ?? [], true)) {
            throw ValidationException::withMessages(['file' => 'El contenido del archivo no coincide con el formato indicado.']);
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $size = @getimagesize($file->getPathname());
            if (! $size || $size[0] > 10000 || $size[1] > 10000 || $size[0] * $size[1] > 40000000) {
                throw ValidationException::withMessages(['file' => 'La imagen no es válida o excede 40 megapíxeles / 10.000 píxeles por lado.']);
            }
        }
        if ($extension === 'doc') {
            // Legacy Word containers cannot reliably be inspected for macros. Ask for DOCX/PDF.
            throw ValidationException::withMessages(['file' => 'Convierta el documento Word antiguo (.doc) a .docx sin macros o PDF.']);
        }
        if ($extension !== 'docx') {
            return;
        }
        abort_unless(class_exists(\ZipArchive::class), 503, 'La validación de documentos Word no está disponible.');
        $zip = new \ZipArchive;
        if ($zip->open($file->getPathname(), \ZipArchive::RDONLY) !== true) {
            throw ValidationException::withMessages(['file' => 'El documento Word no es válido.']);
        }
        try {
            $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('word/document.xml') !== false && $zip->numFiles <= 3000;
            $expanded = 0;
            for ($index = 0; $valid && $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = strtolower($entry['name']);
                $expanded += $entry['size'];
                $valid = ! str_contains($name, '..') && ! str_starts_with($name, '/')
                    && ! str_contains($name, 'vbaproject') && ! str_contains($name, 'embeddings/')
                    && ! preg_match('/\.(exe|dll|js|vbs|ps1|bat|cmd|com|scr)$/', $name)
                    && ($entry['encryption_method'] ?? 0) === 0 && $expanded <= 52428800;
            }
            if (! $valid || $expanded > max(1, $file->getSize()) * 100) {
                throw ValidationException::withMessages(['file' => 'El Word debe ser DOCX sin macros, objetos incrustados, cifrado ni contenido comprimido excesivo.']);
            }
        } finally {
            $zip->close();
        }
    }

    public static function stage(array $files): array
    {
        if (count($files) > config('portal.document_batch_max_files') || array_sum(array_map(fn ($file) => $file->getSize(), $files)) > config('portal.document_batch_max_kb') * 1024) {
            throw ValidationException::withMessages(['documents' => 'Puede cargar hasta 5 documentos y 20 MB en total por envío.']);
        }
        $staged = [];
        $deadline = microtime(true) + 45;
        try {
            foreach ($files as $file) {
                $remaining = $deadline - microtime(true);
                abort_if($remaining <= 0, 503, 'Se agotó el tiempo de validación de documentos. Inténtelo de nuevo.');
                self::validate($file, min(15, $remaining));
                $path = $file->store('documents', 'local');
                abort_unless($path, 503, 'No se pudo almacenar el documento.');
                $staged[] = [
                    'file_path' => $path,
                    'file_name' => mb_strcut(preg_replace('/[\x00-\x1F\x7F]/', '_', basename($file->getClientOriginalName())), 0, 240, 'UTF-8'),
                    'mime_type' => strtolower($file->getClientOriginalExtension()) === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : $file->getMimeType(),
                    'file_size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getPathname()),
                ];
            }

            return $staged;
        } catch (\Throwable $e) {
            self::discard($staged);
            throw $e;
        }
    }

    public static function discard(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk('local')->delete($file['file_path']);
        }
    }
}
