<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDiditSession;
use App\Models\IdentitySession;
use App\Services\IdentitySettings;
use App\Services\ProcessIdentitySession;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReconcileIdentity extends Command
{
    protected $signature = 'portal:reconcile-identity {--recover-creation= : Conciliar una creación incierta por ULID, solo con lecturas}';

    protected $description = 'Procesa sesiones pendientes y reconcilia decisiones Didit sin repetir creaciones inciertas.';

    public function handle(): int
    {
        if (! IdentitySettings::ready()) {
            return $this->option('recover-creation') ? self::FAILURE : self::SUCCESS;
        }
        if ($reference = $this->option('recover-creation')) {
            $session = Str::isUlid($reference) ? IdentitySession::where('public_id', $reference)->first() : null;
            if (! $session || ! app(ProcessIdentitySession::class)->recoverCreation($session->id)) {
                $this->warn('No se pudo confirmar una sesión remota única y vinculada. No se repitió la creación.');

                return self::FAILURE;
            }
            ProcessDiditSession::dispatch($session->id);
            $this->info('Sesión remota recuperada mediante lecturas; conciliación encolada, sin nuevo POST de creación.');

            return self::SUCCESS;
        }
        IdentitySession::where('state', 'creating')->where('updated_at', '<', now()->subMinutes(5))->whereNull('provider_session_id')->update(['state' => 'create_uncertain']);
        $pendingBefore = IdentitySettings::localPolling() ? now()->subSeconds(20) : now()->subMinutes(10);
        IdentitySession::where(fn ($q) => $q->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))->orWhereNotNull('provider_session_id'))
            ->where('application_id', config('identity.didit.application_id'))->where('environment', config('identity.didit.environment'))
            ->where(fn ($q) => $q->where(fn ($q) => $q->eligiblePending())->orWhere(fn ($q) => $q->whereNotNull('provider_session_id')->where(fn ($q) => $q->where('sync_requested', true)->orWhereNull('last_synced_at')
                ->orWhere(fn ($q) => $q->where('state', '!=', 'received')->where('last_synced_at', '<', $pendingBefore))
                ->orWhere(fn ($q) => $q->where('state', 'received')->where('last_synced_at', '<', now()->subDay())))))
            ->orderBy('last_synced_at')->orderBy('id')->limit(100)->get()->each(fn ($session) => ProcessDiditSession::dispatch($session->id));

        return self::SUCCESS;
    }
}
