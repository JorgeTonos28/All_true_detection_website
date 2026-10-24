<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'max:128', Password::min(12)->mixedCase()->numbers(), 'confirmed'],
        ])->validateWithBag('updatePassword');
        $user->forceFill(['password' => $input['password'], 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();
    }
}
