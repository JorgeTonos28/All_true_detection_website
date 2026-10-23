<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireStaffTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        if (config('portal.require_staff_two_factor') && $request->user()->isStaff() && ! $request->user()->two_factor_confirmed_at) {
            if ($request->expectsJson()) {
                abort(403, 'Debe configurar la autenticación de dos factores.');
            }

            return redirect()->route('portal.security')->with('status', 'Configure y confirme 2FA para acceder a las funciones internas.');
        }

        return $next($request);
    }
}
