<?php

namespace App\Console\Commands;

use App\Models\OrderAttachment;
use App\Models\OrderResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillDocumentMetadata extends Command
{
    protected $signature = 'portal:document-metadata';

    protected $description = 'Calcula tamaño y SHA-256 de documentos privados existentes, sin modificar su contenido';

    public function handle(): int
    {
        $failed = 0;
        $updated = 0;
        foreach ([OrderAttachment::class, OrderResult::class] as $model) {
            $model::whereNotNull('file_path')->where(fn ($q) => $q->whereNull('file_size')->orWhereNull('sha256'))->chunkById(100, function ($documents) use (&$failed, &$updated): void {
                foreach ($documents as $document) {
                    if (! Storage::disk('local')->exists($document->file_path)) {
                        $failed++;

                        continue;
                    }
                    $stream = Storage::disk('local')->readStream($document->file_path);
                    if (! is_resource($stream)) {
                        $failed++;

                        continue;
                    }
                    try {
                        $hash = hash_init('sha256');
                        $bytes = hash_update_stream($hash, $stream);
                        $document->update(['file_size' => $bytes, 'sha256' => hash_final($hash)]);
                        $updated++;
                    } finally {
                        fclose($stream);
                    }
                }
            });
        }
        $this->info("Documentos preparados: {$updated}. Archivos ausentes o ilegibles: {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
