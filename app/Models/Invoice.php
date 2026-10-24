<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issuer' => 'array', 'recipient' => 'array', 'subtotal' => 'integer', 'tax' => 'integer', 'total' => 'integer', 'tax_basis_points' => 'integer', 'issued_at' => 'datetime', 'paid_at' => 'datetime', 'due_date' => 'date'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function paidAmount(): int
    {
        if (array_key_exists('payments_sum_amount', $this->getAttributes())) {
            return (int) $this->payments_sum_amount;
        }
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->sum('amount');
        }

        return (int) $this->payments()->sum('amount');
    }

    public function outstanding(): int
    {
        return $this->total - $this->paidAmount();
    }
}
