<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class OrderPerson extends Model
{
    use HasPublicId;

    protected $fillable = ['profile', 'evidence_version'];

    protected $attributes = ['evidence_version' => 2];

    protected $hidden = ['profile'];

    protected function casts(): array
    {
        return ['profile' => 'encrypted:array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'order_person_services');
    }

    public function attachments()
    {
        return $this->hasMany(OrderAttachment::class);
    }
}
