<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'number' => $this->number, 'order_id' => $this->order->public_id, 'currency' => $this->currency, 'subtotal' => $this->subtotal, 'tax_basis_points' => $this->tax_basis_points, 'tax' => $this->tax, 'total' => $this->total, 'paid_amount' => $this->paidAmount(), 'outstanding' => $this->outstanding(), 'status' => $this->status, 'is_overdue' => $this->status !== 'paid' && $this->due_date?->lt(today()), 'due_date' => $this->due_date?->format('Y-m-d'), 'issued_at' => $this->issued_at->toIso8601String(), 'paid_at' => $this->paid_at?->toIso8601String(),
            'issuer' => $this->issuer, 'recipient' => $this->recipient,
            'lines' => $this->when($this->order->relationLoaded('items'), fn () => $this->order->items->map(fn ($item) => ['service_name' => $item->service_name, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'total' => $item->unit_price * $item->quantity])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => ['id' => $payment->public_id, 'amount' => $payment->amount, 'method' => $payment->method, 'reference' => $payment->reference, 'received_at' => $payment->received_at->toIso8601String()])),
            'document_url' => url('/api/v1/invoices/'.$this->public_id.'/document'),
        ];
    }
}
