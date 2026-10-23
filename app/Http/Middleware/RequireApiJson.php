<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireApiJson
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            if ($request->isMethod('POST') && $request->is('api/v1/orders/*/attachments')) {
                abort_unless(str_starts_with(strtolower((string) $request->header('Content-Type')), 'multipart/form-data'), 415, 'Use multipart/form-data para los documentos.');

                return $next($request);
            }
            abort_unless($request->isJson(), 415, 'Use Content-Type: application/json.');
            abort_if(strlen($request->getContent()) > 1048576, 413, 'Solicitud demasiado grande.');
            try {
                json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                abort(400, 'El cuerpo JSON no es válido.');
            }
        }

        return $next($request);
    }
}
