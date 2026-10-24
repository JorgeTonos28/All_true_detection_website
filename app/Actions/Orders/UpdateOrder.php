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
                $allowed = match ($order->status) {
                    OrderStatus::Submitted => ['reviewing', 'cancelled'],
                    OrderStatus::Reviewing => ['cancelled'],
                    OrderStatus::Quoted => ['in_progress'],
                    OrderStatus::InProgress => ['completed'],
                    default => [],
                };
                abort_unless(in_array($data['status'], $allowed, true), 409, 'La transición de estado no está permitida.');
                if ($data['status'] === 'cancelled') {
                    Gate::forUser($actor)->authorize('administer', $order);
                    abort_if($order->invoice()->exists(), 409, 'Una orden facturada no puede cancelarse.');
                }
                if (in_array($data['status'], ['reviewing', 'in_progress', 'completed'], true)) {
                    PersonVerification::requireEvidence($order);
                }
                if ($data['status'] === 'completed' && $order->person()->exists()) {
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
