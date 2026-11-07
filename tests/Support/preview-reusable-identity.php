<?php

// Synthetic, isolated UI fixture. Never points the server at the normal database.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Support\ReusableIdentityScenario;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (PHP_SAPI !== 'cli' || ! $app->environment('local', 'testing')) {
    throw new RuntimeException('Solo CLI local/testing.');
}
$path = tempnam(storage_path('framework/testing'), 'atd-reuse-preview-');
config(['database.default' => 'reuse_preview', 'database.connections.reuse_preview' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true], 'mail.default' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
Artisan::call('migrate', ['--database' => 'reuse_preview', '--force' => true]);
Artisan::call('db:seed', ['--database' => 'reuse_preview', '--force' => true]);
$case = new ReusableIdentityScenario('web');
$subject = $case->enroll();
$password = Str::password(24);
$case->client->update(['name' => 'Cliente de demostración', 'password' => $password]);
$case->admin->update(['name' => 'Administración ficticia', 'password' => $password]);
echo json_encode(['database' => $path, 'client' => $case->client->email, 'admin' => $case->admin->email, 'password' => $password, 'order_id' => $case->order->public_id], JSON_THROW_ON_ERROR).PHP_EOL;
