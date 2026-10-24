<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class OrderResult extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return ['summary' => 'encrypted', 'published_at' => 'datetime', 'file_size' => 'integer'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
