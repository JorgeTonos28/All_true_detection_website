<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class IntegrationAccess
{
    public function handle(Request $request, Closure $next, ?string $scope = null)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $request->attributes->set('integration_user_id', $user->id);
            $request->attributes->set('integration_account_id', $user->customer_account_id);
            $request->attributes->set('integration_token_id', $token->id);
        }
        abort_unless($user && $user->is_active && $user->hasVerifiedEmail() && ! $user->isStaff() && $user->customer_account_id, 403, 'Integración no autorizada.');
        abort_unless($token instanceof PersonalAccessToken, 401, 'Se requiere un token de integración.');
        abort_if($scope && ! $token->can($scope), 403, 'El token no tiene el permiso requerido.');

        return $next($request);
    }
}
