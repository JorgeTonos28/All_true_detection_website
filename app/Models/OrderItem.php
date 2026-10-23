<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['service_id', 'service_name', 'quantity', 'unit_price'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
