<?php

// CLI-only synthetic UI fixture. No real Didit calls, mail or production database.
use App\Actions\Auth\CreateNewUser;
use App\Actions\Orders\CreateOrder;
use App\Enums\Role;
use App\Models\IdentitySession;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (PHP_SAPI !== 'cli' || ! $app->environment('local', 'testing')) {
    throw new RuntimeException('Solo disponible en CLI local/testing.');
}
$path = tempnam(storage_path('framework/testing'), 'atd-didit-preview-');
config(['database.default' => 'didit_preview', 'database.connections.didit_preview' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true], 'mail.default' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
Notification::fake();
Queue::fake();
Http::preventStrayRequests();
Artisan::call('migrate', ['--database' => 'didit_preview', '--force' => true]);
Artisan::call('db:seed', ['--database' => 'didit_preview', '--force' => true]);
config(['identity.didit' => ['api_key' => 'synthetic-ui-only', 'webhook_secret' => 'synthetic-ui-only-signing-secret', 'application_id' => '11111111-1111-4111-8111-111111111111', 'workflow_id' => '22222222-2222-4222-8222-222222222222', 'environment' => 'sandbox', 'privacy_approved' => true, 'production_approved' => false]]);
$password = Str::password(24);
$admin = User::factory()->create(['name' => 'Administración ficticia', 'email' => 'admin-didit@atd.test', 'password' => $password, 'role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
$client = app(CreateNewUser::class)->create(['name' => 'Cliente de demostración', 'email' => 'client-didit@atd.test', 'password' => $password, 'password_confirmation' => $password, 'terms' => true]);
$client->forceFill(['email_verified_at' => now()])->save();
Setting::put('identity_verification_mode', 'didit');
$order = app(CreateOrder::class)->handle($client, ['subject' => 'Identidad · demostración sin datos reales', 'consent' => true, 'items' => [['service_code' => 'identity-verification', 'quantity' => 1]], 'people' => [['first_name' => 'Persona', 'last_name' => 'Ficticia', 'identity_number' => '00000000000', 'address' => 'Dirección ficticia', 'purpose' => 'Revisar visualmente la integración', 'authorization_confirmed' => true, 'service_codes' => ['identity-verification']]]]);
IdentitySession::create(['order_id' => $order->id, 'order_person_id' => $order->person->id, 'application_id' => config('identity.didit.application_id'), 'workflow_id' => config('identity.didit.workflow_id'), 'environment' => 'sandbox', 'provider_session_id' => Str::uuid(), 'state' => 'awaiting_person', 'verification_url' => 'https://verify.didit.me/session/synthetic-ui-do-not-open', 'consented_at' => now(), 'notice_version' => config('identity.notice_version')]);
echo json_encode(['database' => $path, 'admin' => $admin->email, 'client' => $client->email, 'password' => $password, 'order_id' => $order->public_id], JSON_THROW_ON_ERROR).PHP_EOL;
