<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'number' => $this->number, 'currency' => $this->currency, 'subtotal' => $this->subtotal, 'tax_basis_points' => $this->tax_basis_points, 'tax' => $this->tax, 'total' => $this->total, 'outstanding' => $this->outstanding(), 'status' => $this->status, 'issued_at' => $this->issued_at->toIso8601String(), 'paid_at' => $this->paid_at?->toIso8601String()];
    }
}
