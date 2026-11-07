<?php

namespace App\Support;

use Illuminate\Routing\UrlGenerator;

class AuthenticationLinks
{
    public static function generator(?string $origin = null): UrlGenerator
    {
        // Clone the generator so email URLs never change the current web request.
        $generator = clone app('url');
        $origin ??= config('app.url');
        $generator->forceRootUrl(rtrim($origin, '/'));
        $generator->forceScheme(parse_url($origin, PHP_URL_SCHEME));

        return $generator;
    }
}
