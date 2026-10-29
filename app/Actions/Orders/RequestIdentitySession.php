<?php

namespace App\Actions\Orders;

use App\Jobs\ProcessDiditSession;
use App\Models\CustomerAccount;
use App\Models\IdentitySession;
use App\Models\IdentitySubject;
use App\Models\Order;
use App\Models\OrderPerson;
use App\Models\User;
use App\Services\Audit;
use App\Services\IdentityPartnerAccess;
use App\Services\IdentityReferences;
use App\Services\IdentitySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class RequestIdentitySession
{
    public function handle(User $actor, Order $order, OrderPerson $person, array $data, string $channel = 'web'): IdentitySession
    {
        Gate::forUser($actor)->authorize('view', $order);
        abort_unless(! $actor->isStaff() && $actor->customer_account_id === $order->customer_account_id, 403);
        abort_unless($person->order_id === $order->id && $person->identity_mode === 'didit' && $person->services()->where('code', 'identity-verification')->exists(), 404);
        abort_unless(in_array($channel, ['web', 'api_credit'], true), 422);
        if ($channel === 'api_credit') {
            IdentityPartnerAccess::authorize($actor, 'identity:verify');
        }
        $recurring = $person->identity_subject_id !== null;
        $notice = config($recurring ? 'identity.reuse_notice_version' : 'identity.notice_version');
        Validator::make($data, ['consent' => 'required|accepted', 'notice_version' => 'required|in:'.$notice])->validate();
        abort_unless(IdentitySettings::ready(), 503);
        if ($recurring) {
            abort_unless(IdentitySettings::reuseReady(), 503);
            abort_unless($person->identitySubject?->customer_account_id === $actor->customer_account_id && IdentityReferences::usable($person->identitySubject), 409);
            if (! $person->identitySession()->exists()) {
                IdentityReferences::refreshSource($person->identitySubject);
            }
        }
        $session = DB::transaction(function () use ($actor, $order, $person, $channel, $recurring, $notice) {
            // Serialize budget checks before any snapshot reads, including requests for different orders.
            DB::table('settings')->where('key', 'identity_verification_mode')->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $account = CustomerAccount::whereKey($order->customer_account_id)->lockForUpdate()->firstOrFail();
            abort_if($order->status->value === 'cancelled', 409, 'Esta solicitud está cancelada.');
            if ($channel === 'api_credit') {
                abort_unless($account->hasIdentityApiCredit(), 403);
                abort_if($order->invoice()->exists() || $order->items()->whereHas('service', fn ($q) => $q->where('code', '!=', 'identity-verification'))->exists(), 409, 'El crédito requiere una solicitud exclusiva de identidad y sin factura individual.');
            }
            abort_if(IdentitySession::where('order_id', $order->id)->where('access_channel', '!=', $channel)->exists(), 409, 'No se puede cambiar el canal de una verificación iniciada.');
            // Hosted/SDK surfaces may show the provider's verdict. Web always exercises the prepaid flow.
            abort_if($channel === 'web' && ! $order->hasConfirmedPayment(), 409, 'Confirme el pago total de la factura antes de iniciar la captura Didit.');
            $existing = $person->identitySession()->first();
            if ($existing) {
                return $existing;
            }
            $today = IdentitySession::where('created_at', '>=', now()->startOfDay());
            abort_if((clone $today)->count() >= max(0, (int) config('identity.daily_session_limit')), 429, 'Límite diario de sesiones alcanzado.');
            $limit = $channel === 'api_credit' ? $account->identity_api_daily_limit : config('identity.account_daily_session_limit');
            abort_if((clone $today)->where('access_channel', $channel)->whereHas('order', fn ($q) => $q->where('customer_account_id', $order->customer_account_id))->count() >= max(0, (int) $limit), 429, 'Límite diario de sesiones de la cuenta alcanzado.');
            $config = config('identity.didit');
            $subject = $recurring ? IdentitySubject::whereKey($person->identity_subject_id)->lockForUpdate()->firstOrFail() : null;
            if ($subject) {
                abort_unless(IdentityReferences::usable($subject), 409, 'La referencia no está disponible.');
                abort_if($subject->sessions()->whereNull('provider_completed_at')->exists(), 409, 'Hay otra captura de esta identidad pendiente. Termine o concilie esa captura antes de crear otra.');
            }
            $session = IdentitySession::create(['order_id' => $order->id, 'order_person_id' => $person->id,
                'application_id' => $config['application_id'], 'workflow_id' => $recurring ? $config['authentication_workflow_id'] : $config['workflow_id'], 'environment' => $config['environment'], 'state' => 'pending',
                'identity_subject_id' => $subject?->id, 'verification_kind' => $recurring ? 'recurring' : 'initial', 'provider_reference' => $subject?->provider_reference,
                'access_channel' => $channel, 'credit_authorized_by' => $channel === 'api_credit' ? $account->identity_api_approved_by : null,
                'credit_authorized_at' => $channel === 'api_credit' ? $account->identity_api_approved_at : null,
                'consented_at' => now(), 'notice_version' => $notice]);
            Audit::record($actor, 'identity.consent_confirmed', $session, ['notice_version' => $session->notice_version]);

            return $session;
        });
        ProcessDiditSession::dispatch($session->id)->afterCommit();

        return $session->fresh();
    }
}
