<?php

namespace App\Actions\Identity;

use App\Models\IdentitySession;
use App\Models\IdentitySubject;
use App\Models\Order;
use App\Models\User;
use App\Services\Audit;
use App\Services\IdentityPartnerAccess;
use App\Services\IdentityReferences;
use App\Services\IdentitySettings;
use App\Services\ProcessIdentitySession;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class EnrollIdentity
{
    public function handle(User $actor, IdentitySession $source, array $data, string $channel = 'web'): IdentitySubject
    {
        Gate::forUser($actor)->authorize('view', $source->order);
        abort_unless($actor->is_active && $actor->hasVerifiedEmail() && ! $actor->isStaff() && $actor->customer_account_id === $source->order->customer_account_id, 403);
        abort_unless(in_array($channel, ['web', 'api_credit'], true), 422);
        if ($channel === 'api_credit') {
            IdentityPartnerAccess::authorize($actor, 'identity:verify');
            IdentityPartnerAccess::authorize($actor, 'identity:read');
        }
        // A web session must not acquire the API-only result-release exception.
        abort_if($channel === 'web' && $source->access_channel !== 'web', 403);
        Validator::make($data, ['consent' => 'required|accepted', 'notice_version' => 'required|in:'.config('identity.reuse_notice_version')])->validate();
        abort_unless(IdentitySettings::reuseReady(), 503);
        abort_unless($source->provider_session_id && $source->application_id === config('identity.didit.application_id') && $source->environment === config('identity.didit.environment'), 409);
        abort_unless(IdentityReferences::releasedSource($source), 409, 'Primero complete y reciba una verificación documental aprobada.');
        $started = now()->startOfSecond();
        app(ProcessIdentitySession::class)->handle($source->id);

        return DB::transaction(function () use ($actor, $source, $started, $channel) {
            Order::whereKey($source->order_id)->lockForUpdate()->firstOrFail();
            if ($channel === 'api_credit') {
                IdentityPartnerAccess::authorize($actor, 'identity:verify');
                IdentityPartnerAccess::authorize($actor, 'identity:read');
            }
            $source = IdentitySession::whereKey($source->id)->lockForUpdate()->firstOrFail();
            abort_unless(! $source->sync_requested && $source->last_synced_at?->greaterThanOrEqualTo($started), 503, 'No se pudo confirmar la verificación original.');
            abort_unless(IdentityReferences::releasedSource($source), 409);
            $expires = $source->consented_at->copy()->addDays(config('identity.reference_days'));
            abort_unless($expires->isFuture(), 409, 'La verificación original superó su vigencia; solicite una nueva.');
            $existing = $source->enrolledSubject()->first();
            if ($existing) {
                abort_if($existing->revoked_at, 409, 'La referencia fue revocada; requiere otra verificación completa.');

                return $existing;
            }
            $subject = new IdentitySubject;
            $subject->forceFill(['customer_account_id' => $source->order->customer_account_id, 'source_identity_session_id' => $source->id,
                'application_id' => $source->application_id, 'environment' => $source->environment, 'provider_reference' => $source->providerReference(),
                'profile' => Arr::only($source->person->profile, ['first_name', 'last_name', 'identity_number', 'address', 'purpose', 'birth_date']),
                'enrolled_by' => $actor->id, 'notice_version' => config('identity.reuse_notice_version'), 'consented_at' => now(), 'expires_at' => $expires])->save();
            Audit::record($actor, 'identity.reference_enrolled', $subject, ['notice_version' => $subject->notice_version]);

            return $subject;
        }, 3);
    }
}
