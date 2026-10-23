<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(App\Http\Middleware\ApiContext::class);
        $middleware->append(App\Http\Middleware\SecurityHeaders::class);
        $middleware->web(append: [App\Http\Middleware\EnsureActiveUser::class, App\Http\Middleware\AuthRateLimits::class]);
        $middleware->alias([
            'active' => App\Http\Middleware\EnsureActiveUser::class,
            'staff-2fa' => App\Http\Middleware\RequireStaffTwoFactor::class,
            'integration' => App\Http\Middleware\IntegrationAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*')) { return null; }
            $status = match (true) {
                $e instanceof \Illuminate\Validation\ValidationException => 422,
                $e instanceof \Illuminate\Auth\AuthenticationException => 401,
                $e instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };
            $message = match ($status) { 401=>'Token requerido o expirado.',403=>'Acceso no autorizado.',404=>'Recurso no encontrado.',422=>'Revise los datos enviados.',429=>'Se ha excedido el límite de solicitudes.',409=>$e->getMessage(),default=>'No se pudo procesar la solicitud.' };
            $headers = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getHeaders() : [];
            return response()->json(['error'=>['code'=>'http_'.$status,'message'=>$message,'fields'=>$e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : (object) []], 'meta'=>['request_id'=>$request->attributes->get('request_id')]], $status, $headers + ['X-Request-ID'=>(string) $request->attributes->get('request_id'),'Cache-Control'=>'no-store, private']);
        });
    })->create();
