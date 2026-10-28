<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDiditSession;
use App\Models\IdentitySession;
use App\Services\IdentitySettings;
use Illuminate\Console\Command;

class ReconcileIdentity extends Command
{
    protected $signature = 'portal:reconcile-identity';

    protected $description = 'Procesa sesiones pendientes y reconcilia decisiones Didit sin repetir creaciones inciertas.';

    public function handle(): int
    {
        if (! IdentitySettings::ready()) {
            return self::SUCCESS;
        }
        IdentitySession::where('state', 'creating')->where('updated_at', '<', now()->subMinutes(5))->whereNull('provider_session_id')->update(['state' => 'create_uncertain']);
        IdentitySession::whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->where('application_id', config('identity.didit.application_id'))->where('environment', config('identity.didit.environment'))
            ->where(fn ($q) => $q->where('state', 'pending')->orWhere(fn ($q) => $q->whereNotNull('provider_session_id')->where(fn ($q) => $q->where('sync_requested', true)->orWhereNull('last_synced_at')
                ->orWhere(fn ($q) => $q->where('state', '!=', 'received')->where('last_synced_at', '<', now()->subMinutes(10)))
                ->orWhere(fn ($q) => $q->where('state', 'received')->where('last_synced_at', '<', now()->subDay())))))
            ->orderBy('last_synced_at')->orderBy('id')->limit(100)->get()->each(fn ($session) => ProcessDiditSession::dispatch($session->id));

        return self::SUCCESS;
    }
}
