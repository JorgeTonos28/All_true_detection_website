<?php

namespace App\Console\Commands;

use App\Models\IdentitySession;
use App\Services\DiditClient;
use App\Services\IdentitySettings;
use Illuminate\Console\Command;

class IdentityStatus extends Command
{
    protected $signature = 'portal:identity-status {--check-provider : Consulta los dos workflows sin crear sesiones}';

    protected $description = 'Diagnóstico Didit sin mostrar claves, enlaces de captura ni datos de personas.';

    public function handle(DiditClient $client): int
    {
        $yes = fn ($value) => $value ? 'Sí' : 'No';
        $this->table(['Configuración', 'Estado'], [
            ['Entorno Laravel / Didit', app()->environment().' / '.config('identity.didit.environment')],
            ['Clave API configurada', $yes(filled(config('identity.didit.api_key')))],
            ['Firma webhook configurada', $yes(strlen((string) config('identity.didit.webhook_secret')) >= 16)],
            ['Consulta local sin túnel', $yes(IdentitySettings::localPolling())],
            ['Inicial / recurrente listas', $yes(IdentitySettings::ready()).' / '.$yes(IdentitySettings::reuseReady())],
            ['Modo efectivo del negocio', IdentitySettings::mode()],
        ]);
        $this->line('Sandbox envía sandbox_scenario=approve: Didit lo rechaza si la clave pertenece a una aplicación live.');
        $this->line('Los resultados Sandbox son simulados. Las capturas sí se conservan en Didit; use datos y documentos de prueba.');
        if (! IdentitySettings::reuseReady()) {
            $this->warn('Complete la clave, aplicación, ambos workflows y aprobaciones de privacidad. En producción también se exige firma webhook y aprobación live.');

            return self::FAILURE;
        }
        if ($this->option('check-provider')) {
            foreach (['initial' => 'workflow_id', 'recurring' => 'authentication_workflow_id'] as $kind => $key) {
                $session = new IdentitySession(['workflow_id' => config('identity.didit.'.$key), 'verification_kind' => $kind]);
                try {
                    $client->assertActiveWorkflow($session);
                } catch (\Throwable) {
                    $this->error('No se pudo confirmar el workflow '.$kind.'. Revise permisos de la clave, publicación, ACTIVE_3D y Face Match; inicial con documento, recurrente sin documento.');

                    return self::FAILURE;
                }
                $this->info('Workflow '.$kind.' publicado y compatible con ACTIVE_3D + Face Match.');
            }
            $this->line('Comprobación de lectura terminada. No se crearon sesiones ni se enviaron capturas.');
        }

        return self::SUCCESS;
    }
}
