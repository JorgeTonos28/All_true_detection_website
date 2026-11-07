<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiContractTest extends TestCase
{
    public function test_openapi_covers_every_api_route_and_has_no_broken_local_references(): void
    {
        $spec = json_decode(file_get_contents(base_path('packages/contracts/openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('3.0.3', $spec['openapi']);
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $path = '/'.substr($route->uri(), 7);
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $this->assertArrayHasKey(strtolower($method), $spec['paths'][$path] ?? [], $method.' '.$path);
            }
        }
        $walk = function (array $node) use (&$walk, $spec): void {
            foreach ($node as $key => $value) {
                if ($key === '$ref') {
                    $target = $spec;
                    foreach (explode('/', substr($value, 2)) as $segment) {
                        $this->assertArrayHasKey($segment, $target, $value);
                        $target = $target[$segment];
                    }
                } elseif (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($spec);
    }
}
