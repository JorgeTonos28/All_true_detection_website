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
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($order->status->value === 'cancelled', 409, 'Esta solicitud está cancelada.');
            $existing = $person->identitySession()->first();
            if ($existing) {
                return $existing;
            }
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
