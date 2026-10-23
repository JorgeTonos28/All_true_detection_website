<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'is_active' => 'boolean'];
    }

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
