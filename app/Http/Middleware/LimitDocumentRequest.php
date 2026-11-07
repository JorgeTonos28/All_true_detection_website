<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LimitDocumentRequest
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST')) {
            $limit = match (true) {
                $request->is('portal/orders') => 22 * 1024 * 1024,
                $request->is('api/v1/orders/*/attachments', 'portal/orders/*/attachments', 'portal/orders/*/results') => 12 * 1024 * 1024,
                default => null,
            };
            abort_if($limit && (int) $request->header('Content-Length', 0) > $limit, 413, 'La carga excede el tamaño total permitido.');
        }

        return $next($request);
    }
}
