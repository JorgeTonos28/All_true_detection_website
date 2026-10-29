<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IdentitySession extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected $hidden = ['verification_url', 'provider_status', 'decision', 'provider_session_id', 'application_id', 'workflow_id', 'provider_reference'];

    protected function casts(): array
    {
        return ['verification_url' => 'encrypted', 'provider_status' => 'encrypted', 'decision' => 'encrypted:array', 'sync_requested' => 'boolean', 'consented_at' => 'datetime', 'last_synced_at' => 'datetime', 'credit_authorized_at' => 'datetime', 'credit_accepted_at' => 'datetime', 'decision_revision' => 'integer', 'provider_completed_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function person()
    {
        return $this->belongsTo(OrderPerson::class, 'order_person_id');
    }

    public function subject()
    {
        return $this->belongsTo(IdentitySubject::class, 'identity_subject_id');
    }

    public function enrolledSubject()
    {
        return $this->hasOne(IdentitySubject::class, 'source_identity_session_id');
    }

    public function providerReference(): string
    {
        return $this->provider_reference ?? $this->public_id;
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

    public function scopeEligiblePending(Builder $query): Builder
    {
        // Coarse queue admission only; the action/worker recheck the full payment/grant rules.
        return $query->where('state', 'pending')->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('access_channel', 'web')->whereHas('order.invoice', fn ($q) => $q->where('status', 'paid')->where('total', '>', 0)))
            ->orWhere(fn ($q) => $q->where('access_channel', 'api_credit')->whereHas('order.account', fn ($q) => $q
                ->where('identity_api_enabled', true)->whereNotNull('identity_api_approved_by')->whereNotNull('identity_api_approved_at'))));
    }
}
