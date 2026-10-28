<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class IdentitySession extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected $hidden = ['verification_url', 'provider_status', 'decision', 'provider_session_id', 'application_id', 'workflow_id'];

    protected function casts(): array
    {
        return ['verification_url' => 'encrypted', 'provider_status' => 'encrypted', 'decision' => 'encrypted:array', 'sync_requested' => 'boolean', 'consented_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function person()
    {
        return $this->belongsTo(OrderPerson::class, 'order_person_id');
    }

    public function publicState(): string
    {
        return match ($this->state) {
            'pending', 'creating' => 'preparing',
            'awaiting_person' => 'awaiting_person',
            'received' => 'received',
            'create_uncertain', 'needs_attention' => 'attention_required',
            default => 'processing',
        };
    }
}
