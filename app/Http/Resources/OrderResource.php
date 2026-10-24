<?php

namespace App\Http\Resources;

use App\Support\PersonVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'subject' => $this->subject, 'details' => $this->details, 'external_reference' => $this->external_reference,
            'status' => $this->status->value, 'created_at' => $this->created_at->toIso8601String(), 'updated_at' => $this->updated_at->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => ['service_code' => $i->service->code, 'service_name' => $i->service_name, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price])),
            // Detail only: never bulk-export identity data in the paginated list.
            'person' => $this->whenLoaded('person', fn () => $this->person?->profile),
            'verification' => $this->whenLoaded('person', fn () => PersonVerification::evidenceStatus($this->resource)),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice && $request->user()?->tokenCan('invoices:read') ? new InvoiceResource($this->invoice->setRelation('order', $this->resource)) : null),
            'links' => ['self' => url('/api/v1/orders/'.$this->public_id), 'results' => url('/api/v1/orders/'.$this->public_id.'/results'), 'messages' => url('/api/v1/orders/'.$this->public_id.'/messages')],
        ];
    }
}
