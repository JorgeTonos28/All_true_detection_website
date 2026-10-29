<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class IdentitySubject extends Model
{
    use HasPublicId;

    protected $guarded = ['*'];

    protected $hidden = ['profile', 'provider_reference', 'application_id', 'source_identity_session_id'];

    protected function casts(): array
    {
        return ['profile' => 'encrypted:array', 'consented_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function sourceSession()
    {
        return $this->belongsTo(IdentitySession::class, 'source_identity_session_id');
    }

    public function sessions()
    {
        return $this->hasMany(IdentitySession::class);
    }
}
