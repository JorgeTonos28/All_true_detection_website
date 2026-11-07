<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit;
use App\Services\OrderEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecordPayment
{
    public function handle(User $actor, Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($actor, $invoice, $data) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('administer', $invoice->order);
            $existing = $invoice->payments()->where('reference', $data['reference'])->first();
            if ($existing) {
                abort_unless($existing->amount === (int) $data['amount'] && $existing->method === $data['method'], 409, 'Referencia ya utilizada con otros datos.');

                return $existing;
            }
            $amount = (int) $data['amount'];
            abort_unless($amount > 0 && $amount <= $invoice->outstanding() && $invoice->status !== 'paid', 409, 'El pago debe ser positivo y no exceder el saldo pendiente.');
            $payment = $invoice->payments()->create(['recorded_by' => $actor->id, 'amount' => $amount, 'method' => $data['method'], 'reference' => $data['reference'], 'received_at' => $data['received_at'] ?? now()]);
            if ($invoice->outstanding() === 0) {
                $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            }
            Audit::record($actor, 'payment.recorded', $payment, ['amount' => $amount, 'invoice_id' => $invoice->public_id]);
            OrderEvents::record($invoice->order, $invoice->status === 'paid' ? 'invoice.paid' : 'payment.recorded');

            return $payment;
        }, 3);
    }
}
