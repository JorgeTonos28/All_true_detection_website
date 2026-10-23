<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ApiContext
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
            $request->attributes->set('request_id', (string) Str::uuid());
        }
        $response = $next($request);
        if ($request->is('api/*')) { $response->headers->set('X-Request-ID', $request->attributes->get('request_id')); }
        return $response;
    }
}
