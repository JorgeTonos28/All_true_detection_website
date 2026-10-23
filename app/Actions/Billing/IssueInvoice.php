<?php

namespace App\Actions\Billing;

use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit;
use App\Services\OrderEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class IssueInvoice
{
    public function handle(User $actor, Order $order, array $data): Invoice
    {
        return DB::transaction(function () use ($actor, $order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('administer', $order);
            abort_unless(in_array($order->status, [OrderStatus::Submitted, OrderStatus::Reviewing], true) && ! $order->invoice()->exists(), 409, 'La orden no admite una nueva factura.');
            $subtotal = 0;
            foreach ($order->items as $item) {
                $price = $data['prices'][$item->id] ?? null;
                abort_unless(is_int($price) || (is_string($price) && ctype_digit($price)), 422, 'Debe cotizar cada servicio en centavos enteros.');
                $price = (int) $price;
                abort_unless($price > 0 && $price <= 100000000, 422);
                $item->update(['unit_price' => $price]);
                $subtotal += $price * $item->quantity;
            }
            abort_unless($subtotal > 0 && $subtotal <= 100000000000, 422);
            $basisPoints = (int) ($data['tax_basis_points'] ?? 0);
            abort_unless($basisPoints >= 0 && $basisPoints <= 10000, 422);
            $tax = intdiv($subtotal * $basisPoints + 5000, 10000);
            $settings = Setting::values();
            $invoice = $order->invoice()->create([
                'number' => 'ATD-'.now()->format('Y').'-'.Str::ulid(), 'currency' => config('portal.currency'),
                'issuer' => collect($settings)->only(['company_name', 'email', 'phone', 'address'])->all(),
                'recipient' => $order->account->only(['name', 'billing_email', 'tax_id', 'billing_address']),
                'subtotal' => $subtotal, 'tax_basis_points' => $basisPoints, 'tax' => $tax, 'total' => $subtotal + $tax,
                'issued_at' => now(), 'due_date' => $data['due_date'] ?? null,
            ]);
            $order->update(['status' => OrderStatus::Quoted]);
            Audit::record($actor, 'invoice.issued', $invoice, ['total' => $invoice->total, 'currency' => $invoice->currency]);
            OrderEvents::record($order, 'invoice.issued');

            return $invoice;
        }, 3);
    }
}
