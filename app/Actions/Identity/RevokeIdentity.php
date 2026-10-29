<?php

namespace App\Actions\Identity;

use App\Models\IdentitySubject;
use App\Models\User;
use App\Services\Audit;
use App\Services\IdentityPartnerAccess;
use Illuminate\Support\Facades\DB;

class RevokeIdentity
{
    public function handle(User $actor, IdentitySubject $subject, bool $api = false): void
    {
        abort_unless($actor->is_active && $actor->hasVerifiedEmail() && ! $actor->isStaff() && $actor->customer_account_id === $subject->customer_account_id, 403);
        if ($api) {
            IdentityPartnerAccess::authorize($actor, 'identity:verify');
        }
        DB::transaction(function () use ($actor, $subject) {
            $subject = IdentitySubject::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            if (! $subject->revoked_at) {
                $subject->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
                Audit::record($actor, 'identity.reference_revoked', $subject);
            }
        });
    }
}
