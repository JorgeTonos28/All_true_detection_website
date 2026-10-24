<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPerson extends Model
{
    protected $fillable = ['profile'];

    protected $hidden = ['profile'];

    protected function casts(): array
    {
        return ['profile' => 'encrypted:array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
