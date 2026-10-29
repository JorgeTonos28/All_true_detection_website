<?php

use Illuminate\Validation\ValidationException;
use Tests\Support\ApiLab\Lab;

require dirname(__DIR__, 3).'/vendor/autoload.php';
require __DIR__.'/Lab.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
try {
    Lab::boot(($argv[1] ?? '') === 'init');
    $result = ($argv[1] ?? '') === 'init' ? Lab::seed() : Lab::operate(json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'status' => $error instanceof ValidationException ? 422 : (method_exists($error, 'getStatusCode') ? $error->getStatusCode() : 500), 'exception' => class_basename($error),
        'fields' => $error instanceof ValidationException ? array_keys($error->errors()) : []], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(1);
}
