<?php

namespace App\Actions\Identity;

use App\Models\CustomerAccount;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ManagePartnerAccess
{
    public function handle(User $actor, CustomerAccount $account, array $data): CustomerAccount
    {
        Gate::forUser($actor)->authorize('manage-business');
        $data = Validator::make($data, ['enabled' => ['required', 'boolean'], 'daily_limit' => ['required', 'integer', 'min:1', 'max:1000']])->validate();

        return DB::transaction(function () use ($actor, $account, $data) {
            $account = CustomerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $account->forceFill(['identity_api_enabled' => $data['enabled'], 'identity_api_daily_limit' => $data['daily_limit'],
                'identity_api_approved_by' => $data['enabled'] ? $actor->id : $account->identity_api_approved_by,
                'identity_api_approved_at' => $data['enabled'] ? now() : $account->identity_api_approved_at])->save();
            Audit::record($actor, $data['enabled'] ? 'identity.partner_authorized' : 'identity.partner_suspended', $account, ['daily_limit' => (int) $data['daily_limit']]);

            return $account;
        }, 3);
    }
}
