<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class CustomerAccount extends Model
{
    use HasPublicId;

    protected $fillable = ['name', 'billing_email', 'tax_id', 'billing_address'];

    protected function casts(): array
    {
        return ['identity_api_enabled' => 'boolean', 'identity_api_daily_limit' => 'integer', 'identity_api_approved_at' => 'datetime'];
    }

    public function hasIdentityApiCredit(): bool
    {
        return $this->identity_api_enabled && $this->identity_api_approved_by && $this->identity_api_approved_at;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
