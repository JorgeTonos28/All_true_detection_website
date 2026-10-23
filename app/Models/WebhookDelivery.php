<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'next_attempt_at' => 'datetime', 'delivered_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
