<?php

use Illuminate\Http\Request;
use Tests\Support\ApiLab\Lab;

// Never included by the normal application, served only on loopback by the test runner.
if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 3).'/vendor/autoload.php';
require __DIR__.'/Lab.php';
try {
    $app = Lab::boot();
} catch (Throwable) {
    http_response_code(503);
    echo 'Laboratorio no disponible.';
    exit;
}
$app->handleRequest(Request::capture());
