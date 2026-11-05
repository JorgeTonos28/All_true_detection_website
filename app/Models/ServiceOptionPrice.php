<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOptionPrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['option_code', 'unit_price'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
