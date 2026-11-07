<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $attributes = ['is_active' => true];

    protected $fillable = ['code', 'name', 'description', 'is_active', 'sort_order', 'category', 'options', 'default_price'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'options' => 'array', 'default_price' => 'integer'];
    }

    public function optionPrices(): HasMany
    {
        return $this->hasMany(ServiceOptionPrice::class);
    }

    public function optionLabels(): array
    {
        return array_column($this->options ?? [], 'name', 'code');
    }
}
