<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    public const EVENTS = ['order.created', 'order.updated', 'invoice.issued', 'payment.recorded', 'invoice.paid', 'result.published'];

    protected $hidden = ['secret', 'previous_secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'previous_secret' => 'encrypted', 'events' => 'array', 'is_active' => 'boolean', 'previous_secret_expires_at' => 'datetime', 'secret_rotated_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
