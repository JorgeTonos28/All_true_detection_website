<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class CustomerAccount extends Model
{
    use HasPublicId;
    protected $fillable = ['name', 'billing_email', 'tax_id', 'billing_address'];
    public function users() { return $this->hasMany(User::class); }
    public function orders() { return $this->hasMany(Order::class); }
}
