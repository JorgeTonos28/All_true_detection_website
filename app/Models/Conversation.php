<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
class Conversation extends Model
{
    protected $attributes = ['status' => 'open'];
    use HasPublicId;
    protected $guarded = ['id'];
    public function order() { return $this->belongsTo(Order::class); }
    public function account() { return $this->belongsTo(CustomerAccount::class, 'customer_account_id'); }
    public function messages() { return $this->hasMany(Message::class); }
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) { return $query; }
        if ($user->isCollaborator()) { return $query->whereHas('order', fn ($q) => $q->visibleTo($user)); }
        return $query->where('customer_account_id', $user->customer_account_id ?? 0);
    }
}
