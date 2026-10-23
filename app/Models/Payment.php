<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model
{
    use HasPublicId;
    protected $guarded = ['id'];
    protected function casts(): array { return ['amount' => 'integer', 'received_at' => 'datetime']; }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
