<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPerson;

class IdentityChecks
{
    public static function completed(OrderPerson $person): bool
    {
        return $person->identity_mode !== 'didit' || $person->identitySession?->decision !== null;
    }

    public static function check(OrderPerson $person): ?array
    {
        $session = $person->identitySession;
        if ($person->identity_mode !== 'didit' || ! $session?->decision) {
            return null;
        }

        return ['person_id' => $person->public_id, 'service_code' => 'identity-verification', 'method' => 'didit', 'session_id' => $session->public_id, 'environment' => $session->environment,
            'outcome' => $session->decision['outcome'], 'notes' => 'Resultado de Didit. Referencia técnica: '.$session->decision['reason'].'. No constituye una decisión de elegibilidad ni garantiza ausencia de fraude.'];
    }

    public static function prepare(Order $order): void
    {
        $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        $people = $order->people()->with(['services', 'identitySession'])->get();
        if ($order->status->value === 'cancelled' || $order->items()->whereHas('service', fn ($q) => $q->where('code', '!=', 'identity-verification'))->exists() || $people->isEmpty() || $people->contains(fn ($person) => $person->identity_mode !== 'didit' || ! self::check($person))) {
            return; // Mixed orders keep their human conclusions; never invent outcomes for other services.
        }
        $checks = $people->map(fn ($person) => self::check($person))->all();
        $key = 'didit:'.$order->public_id.':'.hash('sha256', json_encode($checks, JSON_THROW_ON_ERROR));
        if (! $order->results()->where('automation_key', $key)->exists()) {
            $result = $order->results()->create(['created_by' => null, 'automation_key' => $key, 'summary' => 'Verificación de identidad procesada con Didit. Consulte las conclusiones por persona y sus límites.', 'verification_findings' => ['method' => 'didit', 'reviewed_at' => now()->toIso8601String(), 'checks' => $checks]]);
            Audit::record(null, 'identity.result_prepared', $result);
        }
        // Completion, payment confirmation and publication remain explicit ATD operations.
    }
}
