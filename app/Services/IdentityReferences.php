<?php

namespace App\Services;

use App\Models\IdentitySession;
use App\Models\IdentitySubject;
use App\Models\Order;
use Carbon\CarbonImmutable;

class IdentityReferences
{
    public static function releasedSource(IdentitySession $source): bool
    {
        if ($source->verification_kind !== 'initial' || $source->order->status->value === 'cancelled' || ($source->decision['outcome'] ?? null) !== 'verified' || $source->provider_status !== 'Approved') {
            return false;
        }
        if ($source->access_channel === 'api_credit') {
            return $source->credit_authorized_at && $source->credit_authorized_by && $source->credit_accepted_at;
        }
        $order = $source->order;
        if ($order->status->value !== 'completed' || ! $order->hasConfirmedPayment()) {
            return false;
        }

        return $order->results()->whereNotNull('published_at')->get()->contains(fn ($result) => collect($result->verification_findings['checks'] ?? [])
            ->contains(fn ($check) => ($check['session_id'] ?? null) === $source->public_id && ($check['outcome'] ?? null) === 'verified'));
    }

    public static function validAt(IdentitySubject $subject, mixed $at): bool
    {
        $at = CarbonImmutable::parse($at);
        if ($subject->application_id !== config('identity.didit.application_id') || $subject->environment !== config('identity.didit.environment')
            || $subject->expires_at->lessThanOrEqualTo($at) || ($subject->revoked_at && $subject->revoked_at->lessThanOrEqualTo($at))) {
            return false;
        }
        $source = $subject->sourceSession()->with('order.account')->first();

        return $source && $source->order->customer_account_id === $subject->customer_account_id
            && $source->application_id === $subject->application_id && $source->environment === $subject->environment
            && hash_equals($subject->provider_reference, $source->providerReference()) && self::releasedSource($source);
    }

    public static function usable(IdentitySubject $subject): bool
    {
        return self::validAt($subject, now());
    }

    public static function refreshSource(IdentitySubject $subject): void
    {
        $started = now()->startOfSecond();
        app(ProcessIdentitySession::class)->handle($subject->source_identity_session_id);
        $source = $subject->sourceSession()->firstOrFail();
        abort_unless(! $source->sync_requested && $source->last_synced_at?->greaterThanOrEqualTo($started), 503, 'No se pudo confirmar la referencia de identidad con el proveedor.');
        abort_unless(self::usable($subject), 409, 'La referencia requiere una nueva verificación documental o no está habilitada para reutilización.');
    }

    public static function validResult(IdentitySession $session): bool
    {
        if ($session->verification_kind !== 'recurring' || ($session->decision['outcome'] ?? null) !== 'verified') {
            return true;
        }

        return self::boundToSession($session) && self::validAt($session->subject, $session->decision['reviewed_at']);
    }

    public static function boundToSession(IdentitySession $session): bool
    {
        $subject = $session->subject;

        return $subject && $subject->customer_account_id === $session->order->customer_account_id
            && $session->person->identity_subject_id === $subject->id && $session->person->order_id === $session->order_id
            && $subject->application_id === $session->application_id && $subject->environment === $session->environment
            && hash_equals($subject->provider_reference, $session->providerReference());
    }

    public static function releasableOrder(Order $order): bool
    {
        return ! IdentitySession::where('order_id', $order->id)->where('verification_kind', 'recurring')->with('subject')->get()
            ->contains(fn ($session) => ! self::validResult($session));
    }
}
