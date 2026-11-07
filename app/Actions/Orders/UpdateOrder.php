<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Services\Audit;
use App\Services\OrderEvents;
use App\Support\PersonVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateOrder
{
    public function handle(User $actor, Order $order, array $data): Order
    {
        return DB::transaction(function () use ($actor, $order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $order);
            if (array_key_exists('assigned_to', $data)) {
                Gate::forUser($actor)->authorize('administer', $order);
                abort_unless(! $data['assigned_to'] || User::whereKey($data['assigned_to'])->whereIn('role', [Role::Admin, Role::Collaborator])->where('is_active', true)->exists(), 422, 'Asignación no válida.');
                $order->assigned_to = $data['assigned_to'];
            }
            if (isset($data['status']) && $data['status'] !== $order->status->value) {
                $allowed = array_map(fn ($state) => $state->value, $order->status->nextStates());
                abort_unless(in_array($data['status'], $allowed, true), 409, 'La transición de estado no está permitida.');
                if ($order->status === OrderStatus::Quoted) {
                    abort_unless($order->invoice()->exists(), 409, 'Emita la factura antes de iniciar el trabajo.');
                }
                if ($data['status'] === 'cancelled') {
                    Gate::forUser($actor)->authorize('administer', $order);
                    abort_if($order->invoice()->exists(), 409, 'Una orden facturada no puede cancelarse.');
                    foreach ($order->people()->with('identitySession')->get() as $person) {
                        $session = $person->identitySession;
                        if ($session) {
                            // Only unclaimed, never-sent work can be closed without provider confirmation.
                            $session->newQuery()->whereKey($session->id)->where('state', 'pending')->whereNull('provider_session_id')
                                ->update(['state' => 'cancelled_before_capture', 'provider_completed_at' => now(), 'updated_at' => now()]);
                        }
                    }
                }
                if (in_array($data['status'], ['reviewing', 'in_progress', 'completed'], true)) {
                    PersonVerification::requireEvidence($order);
                }
                if ($data['status'] === 'completed' && $order->person()->exists()) {
                    abort_if($order->people()->where('identity_mode', 'didit')->whereDoesntHave('identitySession', fn ($q) => $q->whereNotNull('decision'))->exists(), 409, 'Hay verificaciones Didit pendientes.');
                    abort_unless($order->results()->whereNotNull('verification_findings')->exists(), 409, 'Prepare primero una conclusión humana por servicio.');
                }
                $order->status = OrderStatus::from($data['status']);
            }
            $order->save();
            Audit::record($actor, 'order.updated', $order, ['status' => $order->status->value, 'assigned_to' => $order->assigned_to]);
            OrderEvents::record($order, 'order.updated');

            return $order;
        }, 3);
    }
}
