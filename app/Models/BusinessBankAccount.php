<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessBankAccount extends Model
{
    use HasFactory;

    protected $fillable = ['bank_name', 'account_type', 'account_holder', 'account_number', 'currency', 'is_active', 'sort_order'];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function paymentInstructions(): array
    {
        return $this->only(['bank_name', 'account_type', 'account_holder', 'account_number', 'currency']);
    }
}
