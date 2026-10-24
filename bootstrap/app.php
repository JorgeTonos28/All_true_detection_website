<?php

use App\Http\Middleware\ApiContext;
use App\Http\Middleware\AuthRateLimits;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\IntegrationAccess;
use App\Http\Middleware\LimitDocumentRequest;
use App\Http\Middleware\RequireApiJson;
use App\Http\Middleware\RequireStaffTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ApiContext::class);
        $middleware->append(LimitDocumentRequest::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [EnsureActiveUser::class, AuthRateLimits::class]);
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'staff-2fa' => RequireStaffTwoFactor::class,
            'integration' => IntegrationAccess::class,
            'api-json' => RequireApiJson::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $status = match (true) {
                $e instanceof ValidationException => 422,
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof ModelNotFoundException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };
            $message = match ($status) {
                400 => 'JSON no válido.',401 => 'Token requerido o expirado.',403 => 'Acceso no autorizado.',404 => 'Recurso no encontrado.',413 => 'Solicitud demasiado grande.',415 => 'Tipo de contenido no compatible con este endpoint.',422 => 'Revise los datos enviados.',426 => 'La API requiere HTTPS.',429 => 'Se ha excedido el límite de solicitudes.',409 => $e->getMessage(),default => 'No se pudo procesar la solicitud.'
            };
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json(['error' => ['code' => 'http_'.$status, 'message' => $message, 'fields' => $e instanceof ValidationException ? $e->errors() : (object) []], 'meta' => ['request_id' => $request->attributes->get('request_id')]], $status, $headers + ['X-Request-ID' => (string) $request->attributes->get('request_id'), 'Cache-Control' => 'no-store, private']);
        });
        $exceptions->respond(fn ($response, $e, $request) => app(SecurityHeaders::class)->secure($request, $response));
    })->create();
