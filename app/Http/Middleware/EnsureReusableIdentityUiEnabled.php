<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureReusableIdentityUiEnabled
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('portal.reusable_identity_ui_enabled'), 404);

        return $next($request);
    }
}
