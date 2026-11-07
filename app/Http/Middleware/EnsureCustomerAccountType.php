<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCustomerAccountType
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->isStaff() && $user->account && ! $user->account->account_type
            && ! $request->routeIs('portal.account', 'portal.account.update', 'portal.security', 'portal.notifications.*')) {
            if ($request->expectsJson()) {
                abort(409, 'Seleccione el tipo de cuenta en Datos de mi cuenta antes de continuar.');
            }

            return redirect()->route('portal.account')->with('status', 'Confirme si su cuenta corresponde a una persona individual o a una empresa.');
        }

        return $next($request);
    }
}
