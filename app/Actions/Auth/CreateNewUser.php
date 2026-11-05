<?php

namespace App\Actions\Auth;

use App\Enums\AccountType;
use App\Models\CustomerAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        if (($input['account_type'] ?? null) instanceof AccountType) {
            $input['account_type'] = $input['account_type']->value;
        }
        $input['email'] = mb_strtolower(trim($input['email'] ?? ''));
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'company' => ['nullable', 'required_if:account_type,business', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'max:128', Password::min(12)->mixedCase()->numbers(), 'confirmed'],
            'terms' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($data) {
            $account = CustomerAccount::create(['account_type' => $data['account_type'], 'name' => $data['account_type'] === 'business' ? $data['company'] : $data['name'], 'billing_email' => $data['email']]);
            $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
            $user->customer_account_id = $account->id;
            $user->save();

            return $user;
        });
    }
}
