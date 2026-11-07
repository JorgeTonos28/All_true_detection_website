<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class CustomerAccount extends Model
{
    use HasPublicId;

    protected $fillable = ['name', 'billing_email', 'tax_id', 'billing_address', 'account_type'];

    protected function casts(): array
    {
        return ['account_type' => AccountType::class, 'identity_api_enabled' => 'boolean', 'identity_api_daily_limit' => 'integer', 'identity_api_approved_at' => 'datetime'];
    }

    public function hasIdentityApiCredit(): bool
    {
        return $this->identity_api_enabled && $this->identity_api_approved_by && $this->identity_api_approved_at;
    }

    public function typeLabel(): string
    {
        return $this->account_type?->label() ?? 'Tipo pendiente';
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
