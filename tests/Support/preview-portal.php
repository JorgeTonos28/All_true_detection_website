<?php

// CLI-only visual QA fixture. Always uses a fresh, temporary database; never the configured database.
use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Messages\ReplyToConversation;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\StoreResult;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\ApiRequestLog;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Notifications\PortalActivity;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (PHP_SAPI !== 'cli' || ! $app->environment('local', 'testing')) {
    throw new RuntimeException('Solo disponible en CLI local/testing.');
}
$path = tempnam(storage_path('framework/testing'), 'atd-preview-');
config(['database.default' => 'portal_preview', 'database.connections.portal_preview' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true], 'mail.default' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
Notification::fake();
Artisan::call('migrate', ['--database' => 'portal_preview', '--force' => true]);
Artisan::call('db:seed', ['--database' => 'portal_preview', '--force' => true]);
$password = Str::password(24);
$admin = User::factory()->create(['name' => 'Administración ATD', 'email' => 'admin@atd.test', 'password' => $password, 'role' => Role::Admin]);
$client = app(CreateNewUser::class)->create(['name' => 'Laura Méndez', 'company' => 'Comercial Horizonte', 'email' => 'cliente@atd.test', 'password' => $password, 'password_confirmation' => $password, 'terms' => true]);
$client->forceFill(['email_verified_at' => now()])->save();
foreach (['Validación de credenciales profesionales', 'Evaluación de nuevo ingreso', 'Verificación de perfil ejecutivo'] as $index => $subject) {
    $order = app(CreateOrder::class)->handle($client, ['subject' => $subject, 'details' => 'Solicitamos verificar la información autorizada del expediente. Coordinar los siguientes pasos con el departamento de Recursos Humanos.', 'external_reference' => 'RRHH-'.($index + 1), 'items' => [['service_code' => ['university-degree', 'polygraph', 'profile-verification'][$index], 'quantity' => 1]]]);
    app(UpdateOrder::class)->handle($admin, $order, ['assigned_to' => $admin->id, 'status' => 'reviewing']);
    if ($index > 0) {
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items->first()->id => 750000], 'tax_basis_points' => 1800]);
        if ($index === 2) {
            app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
            app(UpdateOrder::class)->handle($admin, $order, ['status' => 'completed']);
            app(RecordPayment::class)->handle($admin, $invoice, ['amount' => $invoice->total, 'method' => 'bank_transfer', 'reference' => 'DEMO-TRANSFERENCIA']);
            app(StoreResult::class)->handle($admin, $order, 'La revisión del expediente ha concluido. Esta es una entrega ficticia usada exclusivamente para revisar la interfaz.');
        }
    }
    app(ReplyToConversation::class)->handle($admin, $order->conversation, 'Hemos recibido su solicitud. Nuestro equipo está revisando la documentación para confirmar el alcance y los próximos pasos.');
}
$client->createToken('Conexión de RRHH (demostración)', ['orders:read', 'orders:write', 'results:read', 'invoices:read'], now()->addDays(30));
$endpoint = WebhookEndpoint::create(['customer_account_id' => $client->customer_account_id, 'url' => 'https://integracion.example.com/atd', 'secret' => Str::random(64), 'events' => ['result.published', 'invoice.paid']]);
$eventId = (string) Str::uuid();
$delivery = $endpoint->deliveries()->create(['order_id' => $order->id, 'event_id' => $eventId, 'event_type' => 'result.published', 'payload' => ['id' => $eventId, 'type' => 'result.published', 'data' => ['order_id' => $order->public_id]], 'attempts' => 8, 'response_status' => 503, 'failed_at' => now()]);
$delivery->history()->create(['response_status' => 503, 'error_code' => 'http_error', 'duration_ms' => 124, 'created_at' => now()]);
foreach ([42, 81, 36, 127, 64] as $index => $duration) {
    ApiRequestLog::create(['request_id' => (string) Str::uuid(), 'customer_account_id' => $client->customer_account_id, 'user_id' => $client->id, 'method' => 'GET', 'route' => 'api/v1/orders/{order}/deliveries', 'status' => $index === 3 ? 409 : 200, 'duration_ms' => $duration, 'created_at' => now()->subMinutes($index)]);
}
foreach ([$client, $admin] as $recipient) {
    $recipient->notifications()->create(['id' => (string) Str::uuid(), 'type' => PortalActivity::class, 'data' => ['title' => 'Pago total confirmado', 'copy' => 'ATD confirmó el pago de la factura de demostración.', 'path' => '/portal/orders/'.$order->public_id, 'reference' => $order->public_id]]);
}
echo json_encode(['database' => $path, 'admin' => $admin->email, 'client' => $client->email, 'password' => $password], JSON_THROW_ON_ERROR).PHP_EOL;
