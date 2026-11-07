<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;

class ServePortalLocally extends ServeCommand
{
    protected $signature = 'portal:serve
        {--host= : Dirección local del servidor}
        {--port= : Puerto del servidor}
        {--tries=10 : Puertos que se pueden intentar}
        {--no-reload : No reiniciar al cambiar .env}';

    protected $description = 'Inicia Laravel local con los límites de documentos del portal, sin cambiar php.ini';

    public function handle()
    {
        if (config('app.env') !== 'local') {
            $this->error('portal:serve solo está disponible en el entorno local.');

            return self::FAILURE;
        }

        return parent::handle();
    }

    protected function serverCommand()
    {
        $command = parent::serverCommand();
        // ServeCommand starts a separate PHP process: flags on artisan itself are not inherited.
        // Preserve Laravel's router, environment reloading, signals and output handling.
        array_splice($command, 1, 0, [
            '-d', 'upload_max_filesize='.(int) ceil(config('portal.document_max_kb') / 1024).'M',
            '-d', 'post_max_size='.(int) (ceil(config('portal.document_batch_max_kb') / 1024) + 4).'M',
            '-d', 'max_file_uploads='.(int) config('portal.document_initial_max_files', 20),
            '-d', 'max_input_vars=2000',
        ]);

        return $command;
    }
}
