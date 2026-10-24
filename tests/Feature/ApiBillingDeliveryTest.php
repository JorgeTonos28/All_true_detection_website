<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\StoreResult;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\ApiRequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiBillingDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Notification::fake();
        Storage::fake('local');
        $this->seed();
        $user = app(CreateNewUser::class)->create(['name' => 'Facturación API', 'email' => 'billing-api@example.com', 'password' => 'PasswordCliente123', 'password_confirmation' => 'PasswordCliente123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $order = app(CreateOrder::class)->handle($user, ['subject' => 'Caso API', 'details' => 'Detalle privado del caso', 'items' => [['service_code' => 'polygraph', 'quantity' => 1]]]);
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items->first()->id => 10000], 'due_date' => today()->subDay()]);

        return [$user, $admin, $order, $invoice];
    }

    public function test_invoices_have_dedicated_scope_balance_filters_and_account_isolation(): void
    {
        [$user, $admin, $order, $invoice] = $this->scenario();
        $ordersOnly = $user->createToken('orders-only', ['orders:read'])->plainTextToken;
        $this->withToken($ordersOnly)->getJson('/api/v1/invoices')->assertForbidden();
        $this->withToken($ordersOnly)->getJson('/api/v1/orders/'.$order->public_id)->assertOk()->assertJsonPath('data.invoice', null);
        $token = $user->createToken('billing', ['invoices:read'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/invoices?status=overdue')->assertOk()->assertJsonPath('data.0.outstanding', 10000)->assertJsonPath('data.0.is_overdue', true);
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 4000, 'method' => 'cash', 'reference' => 'ABONO-API-1']);
        $this->withToken($token)->getJson('/api/v1/invoices/'.$invoice->public_id)->assertOk()->assertJsonPath('data.paid_amount', 4000)->assertJsonPath('data.outstanding', 6000)->assertJsonPath('data.payments.0.reference', 'ABONO-API-1');
        $this->withToken($token)->get('/api/v1/invoices/'.$invoice->public_id.'/document')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8')->assertSee('Factura comercial');
        $other = User::factory()->create();
        $otherToken = $other->createToken('other', ['invoices:read'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/invoices/'.$invoice->public_id)->assertForbidden();
    }

    public function test_delivery_manifest_and_download_require_payment_publication_and_scoped_token(): void
    {
        [$user, $admin, $order, $invoice] = $this->scenario();
        $result = app(StoreResult::class)->handle($admin, $order, 'Informe privado', UploadedFile::fake()->createWithContent('resultado.pdf', '%PDF-1.4 contenido del informe'));
        $token = $user->createToken('results', ['results:read'])->plainTextToken;
        $root = '/api/v1/orders/'.$order->public_id;
        $this->withToken($token)->getJson($root.'/deliveries')->assertConflict();
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'completed']);
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 10000, 'method' => 'cash', 'reference' => 'PAGO-API-1']);
        $this->withToken($token)->getJson($root.'/deliveries')->assertOk()->assertJsonCount(0, 'data');
        app(PublishResult::class)->handle($admin, $result);
        $this->withToken($token)->getJson($root.'/deliveries')->assertOk()->assertJsonPath('data.0.sha256', $result->sha256)->assertJsonPath('data.0.file_size', $result->file_size);
        $this->withToken($token)->getJson($root.'/results/'.$result->public_id)->assertOk()->assertJsonPath('data.summary', 'Informe privado');
        $this->withToken($token)->get($root.'/results/'.$result->public_id.'/download')->assertOk()->assertHeader('X-Content-SHA256', $result->sha256);
        $invoice->update(['status' => 'unpaid']);
        $this->withToken($token)->get($root.'/results/'.$result->public_id.'/download')->assertConflict();
    }

    public function test_monitoring_records_route_status_and_duration_without_payload_or_tokens(): void
    {
        [$user] = $this->scenario();
        $token = $user->createToken('monitor', ['orders:read'])->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/v1/orders?external_reference=PRIVATE-QUERY');
        $response->assertOk()->assertHeader('X-Request-ID');
        $log = ApiRequestLog::where('request_id', $response->headers->get('X-Request-ID'))->firstOrFail();
        $this->assertSame($user->customer_account_id, $log->customer_account_id);
        $this->assertSame(200, $log->status);
        $this->assertSame('api/v1/orders', $log->route);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);
        $this->assertStringNotContainsString('PRIVATE-QUERY', $log->toJson());
        $this->assertStringNotContainsString($token, $log->toJson());
        $this->withToken($token)->postJson('/api/v1/orders', ['details' => 'PRIVATE-BODY'])->assertForbidden();
        $this->assertSame(403, ApiRequestLog::latest('id')->first()->status);
    }
}
