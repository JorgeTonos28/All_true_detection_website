<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Message extends Model
{
    protected $fillable = ['sender_id', 'body'];
    protected function casts(): array { return ['body' => 'encrypted']; }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
    public function conversation() { return $this->belongsTo(Conversation::class); }
}
