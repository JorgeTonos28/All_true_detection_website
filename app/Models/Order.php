<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $attributes = ['status' => 'submitted'];

    use HasPublicId;

    protected $fillable = ['customer_account_id', 'requested_by', 'assigned_to', 'subject', 'details', 'external_reference', 'status', 'consented_at'];

    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'details' => 'encrypted', 'consented_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function results()
    {
        return $this->hasMany(OrderResult::class);
    }

    public function attachments()
    {
        return $this->hasMany(OrderAttachment::class);
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }
        if ($user->isCollaborator()) {
            return $query->where('assigned_to', $user->id);
        }

        return $query->where('customer_account_id', $user->customer_account_id ?? 0);
    }

    public function canReleaseResults(): bool
    {
        $invoice = $this->invoice;

        return $this->status === OrderStatus::Completed && $invoice?->status === 'paid' && $invoice->total > 0 && $invoice->paidAmount() === $invoice->total;
    }
}
