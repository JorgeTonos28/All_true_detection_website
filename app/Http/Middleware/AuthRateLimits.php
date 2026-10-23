<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
class AuthRateLimits
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') && $request->is('register', 'forgot-password', 'reset-password', 'user/confirm-password', 'user/confirmed-two-factor-authentication')) {
            $key = 'auth:'.$request->path().':'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, $request->is('register') ? 5 : 10)) { abort(429, 'Demasiados intentos. Inténtelo más tarde.'); }
            RateLimiter::hit($key, $request->is('register') ? 3600 : 60);
        }
        return $next($request);
    }
}
