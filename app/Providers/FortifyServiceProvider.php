<?php

namespace App\Providers;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Auth\ResetUserPassword;
use App\Actions\Auth\UpdateUserPassword;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::authenticateUsing(function (Request $request): ?User {
            $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:128']]);
            $user = User::where('email', mb_strtolower(trim($data['email'])))->first();
            $valid = Hash::check($data['password'], $user?->password ?? '$2y$04$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');

            return $valid && $user?->is_active ? $user : null;
        });
        foreach (['login', 'register', 'forgot-password', 'verify-email', 'confirm-password', 'two-factor-challenge'] as $mode) {
            $method = match ($mode) {
                'login' => 'loginView', 'register' => 'registerView', 'forgot-password' => 'requestPasswordResetLinkView',
                'verify-email' => 'verifyEmailView', 'confirm-password' => 'confirmPasswordView', default => 'twoFactorChallengeView',
            };
            Fortify::$method(fn () => view('auth.form', ['mode' => $mode]));
        }
        Fortify::resetPasswordView(fn (Request $request) => view('auth.form', ['mode' => 'reset-password', 'token' => $request->route('token'), 'email' => $request->email]));
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()), Limit::perMinute(20)->by($r->ip())]);
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by((string) $r->session()->get('login.id').'|'.$r->ip()));
    }
}
