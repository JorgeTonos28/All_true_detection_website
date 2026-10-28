<?php

namespace App\Actions\Orders;

use App\Jobs\ProcessDiditSession;
use App\Models\IdentitySession;
use App\Models\Order;
use App\Models\OrderPerson;
use App\Models\User;
use App\Services\Audit;
use App\Services\IdentitySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class RequestIdentitySession
{
    public function handle(User $actor, Order $order, OrderPerson $person, array $data): IdentitySession
    {
        Gate::forUser($actor)->authorize('view', $order);
        abort_unless(! $actor->isStaff() && $actor->customer_account_id === $order->customer_account_id, 403);
        abort_unless($person->order_id === $order->id && $person->identity_mode === 'didit' && $person->services()->where('code', 'identity-verification')->exists(), 404);
        Validator::make($data, ['consent' => 'required|accepted', 'notice_version' => 'required|in:'.config('identity.notice_version')])->validate();
        abort_unless(IdentitySettings::ready(), 503);
        $session = DB::transaction(function () use ($actor, $order, $person) {
            // Serialize budget checks before any snapshot reads, including requests for different orders.
            DB::table('settings')->where('key', 'identity_verification_mode')->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($order->status->value === 'cancelled', 409, 'Esta solicitud está cancelada.');
            $existing = $person->identitySession()->first();
            if ($existing) {
                return $existing;
            }
            // Hosted/SDK surfaces may show the provider's own verdict. No live capability before payment.
            abort_if(config('identity.didit.environment') === 'live' && ! $order->hasConfirmedPayment(), 409, 'Confirme el pago total de la factura antes de iniciar la captura Didit.');
            $today = IdentitySession::where('created_at', '>=', now()->startOfDay());
            abort_if((clone $today)->count() >= max(0, (int) config('identity.daily_session_limit')), 429, 'Límite diario de sesiones alcanzado.');
            abort_if((clone $today)->whereHas('order', fn ($q) => $q->where('customer_account_id', $order->customer_account_id))->count() >= max(0, (int) config('identity.account_daily_session_limit')), 429, 'Límite diario de sesiones de la cuenta alcanzado.');
            $config = config('identity.didit');
            $session = IdentitySession::create(['order_id' => $order->id, 'order_person_id' => $person->id,
                'application_id' => $config['application_id'], 'workflow_id' => $config['workflow_id'], 'environment' => $config['environment'], 'state' => 'pending',
                'consented_at' => now(), 'notice_version' => config('identity.notice_version')]);
            Audit::record($actor, 'identity.consent_confirmed', $session, ['notice_version' => $session->notice_version]);

            return $session;
        });
        ProcessDiditSession::dispatch($session->id)->afterCommit();

        return $session->fresh();
    }
}
