<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset(User $user, array $input): void
    {
        Validator::make($input, ['password' => ['required', 'string', 'max:128', Password::min(12)->mixedCase()->numbers(), 'confirmed']])->validate();
        DB::transaction(function () use ($user, $input) {
            $user->forceFill(['password' => $input['password'], 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            if (! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();
            }
        });
    }
}
