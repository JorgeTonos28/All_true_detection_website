<?php

namespace App\Actions\Billing;

use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit;
use App\Services\OrderEvents;
use App\Services\QuoteLines;
use App\Support\PersonVerification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueInvoice
{
    public function handle(User $actor, Order $order, array $data, bool $requireExplicitPrices = false): Invoice
    {
        return DB::transaction(function () use ($actor, $order, $data, $requireExplicitPrices) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('administer', $order);
            abort_if($order->people()->whereHas('identitySession', fn ($q) => $q->where('access_channel', 'api_credit'))->exists(), 409, 'Esta solicitud pertenece al consumo a crédito; no admite una factura individual.');
            abort_unless(in_array($order->status, [OrderStatus::Submitted, OrderStatus::Reviewing], true) && ! $order->invoice()->exists(), 409, 'La orden no admite una nueva factura.');
            PersonVerification::requireEvidence($order);
            $quote = QuoteLines::build($order);
            $prices = $data['prices'] ?? [];
            if (array_diff_key($prices, $quote) || ($requireExplicitPrices && array_diff_key($quote, $prices))) {
                throw ValidationException::withMessages(['prices' => 'Revise y confirme el precio de cada partida de esta solicitud.']);
            }
            $subtotal = 0;
            $lines = [];
            $itemPrices = [];
            foreach ($quote as $key => $line) {
                $price = array_key_exists($key, $prices) ? $prices[$key] : $line['suggested_price'];
                abort_unless(is_int($price) || (is_string($price) && ctype_digit($price)), 422, 'Debe cotizar cada servicio en centavos enteros.');
                $price = (int) $price;
                abort_unless($price > 0 && $price <= 100000000, 422);
                $amount = $price * $line['quantity'];
                $subtotal += $amount;
                $itemPrices[$line['item_id']][$price] = true;
                $lines[] = Arr::only($line, ['service_code', 'service_name', 'option_code', 'option_name', 'quantity']) + ['unit_price' => $price, 'total' => $amount];
            }
            abort_unless($subtotal > 0 && $subtotal <= 100000000000, 422);
            $basisPoints = (int) ($data['tax_basis_points'] ?? 0);
            abort_unless($basisPoints >= 0 && $basisPoints <= 10000, 422);
            $tax = intdiv($subtotal * $basisPoints + 5000, 10000);
            foreach ($order->items as $item) {
                $distinctPrices = $itemPrices[$item->id];
                $item->update(['unit_price' => count($distinctPrices) === 1 ? array_key_first($distinctPrices) : null]);
            }
            $settings = Setting::values();
            $invoice = $order->invoice()->create([
                'number' => 'ATD-'.now()->format('Y').'-'.Str::ulid(), 'currency' => config('portal.currency'),
                'issuer' => collect($settings)->only(['company_name', 'email', 'phone', 'address'])->all(),
                'recipient' => $order->account->only(['name', 'billing_email', 'tax_id', 'billing_address']) + ['account_type' => $order->account->account_type?->value],
                'line_items' => $lines, 'subtotal' => $subtotal, 'tax_basis_points' => $basisPoints, 'tax' => $tax, 'total' => $subtotal + $tax,
                'issued_at' => now(), 'due_date' => $data['due_date'] ?? null,
            ]);
            $order->update(['status' => OrderStatus::Quoted]);
            Audit::record($actor, 'invoice.issued', $invoice, ['total' => $invoice->total, 'currency' => $invoice->currency]);
            OrderEvents::record($order, 'invoice.issued');

            return $invoice;
        }, 3);
    }
}
