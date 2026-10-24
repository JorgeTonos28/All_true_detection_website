<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ApiContext
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
            $request->attributes->set('request_id', (string) Str::uuid());
        }
        if (! $request->is('api/*')) {
            return $next($request);
        }
        $started = hrtime(true);
        $status = 500;
        try {
            abort_if(app()->isProduction() && ! $request->isSecure(), 426, 'La API requiere HTTPS.');
            $response = $next($request);
            $status = $response->getStatusCode();
            $response->headers->set('X-Request-ID', $request->attributes->get('request_id'));

            return $response;
        } catch (\Throwable $error) {
            $status = match (true) {
                $error instanceof ValidationException => 422,
                $error instanceof AuthenticationException => 401,
                $error instanceof AuthorizationException => 403,
                $error instanceof ModelNotFoundException => 404,
                $error instanceof HttpExceptionInterface => $error->getStatusCode(),
                default => 500,
            };
            throw $error;
        } finally {
            try {
                ApiRequestLog::create([
                    'request_id' => $request->attributes->get('request_id'),
                    'user_id' => $request->attributes->get('integration_user_id'),
                    'customer_account_id' => $request->attributes->get('integration_account_id'),
                    'token_id' => $request->attributes->get('integration_token_id'),
                    'method' => substr($request->method(), 0, 10),
                    'route' => substr($request->route()?->uri() ?? 'unmatched', 0, 160),
                    'status' => $status, 'duration_ms' => min(4294967295, max(0, (int) round((hrtime(true) - $started) / 1000000))),
                ]);
            } catch (\Throwable) {
                Log::warning('No se pudo persistir la métrica de API.', ['request_id' => $request->attributes->get('request_id')]);
            }
        }
    }
}
