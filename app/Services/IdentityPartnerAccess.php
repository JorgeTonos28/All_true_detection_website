<?php

namespace App\Services;

use App\Models\CustomerAccount;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class IdentityPartnerAccess
{
    public static function authorize(User $actor, string $scope): CustomerAccount
    {
        $token = $actor->currentAccessToken();
        abort_unless($actor->is_active && $actor->hasVerifiedEmail() && ! $actor->isStaff()
            && $token instanceof PersonalAccessToken && $token->can($scope), 403);
        $account = CustomerAccount::find($actor->customer_account_id);
        abort_unless($account?->hasIdentityApiCredit(), 403, 'La cuenta no tiene acceso autorizado a identidad por API.');

        return $account;
    }
}
