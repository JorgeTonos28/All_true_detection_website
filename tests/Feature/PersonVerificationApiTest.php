<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\StoreResult;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $email): array
    {
        $user = app(CreateNewUser::class)->create(['name' => 'Integrador ficticio', 'email' => $email, 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return ['Authorization' => 'Bearer '.$user->createToken('Pruebas', ['orders:read', 'orders:write', 'results:read'])->plainTextToken];
    }

    public function test_person_creation_upload_isolation_and_paid_published_structured_delivery(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->seed();
        $headers = $this->token('integration@example.test');
        $payload = PersonVerificationWorkflowTest::data();
        $createHeaders = $headers + ['Idempotency-Key' => 'person-api-001'];
        $response = $this->postJson('/api/v1/orders', $payload, $createHeaders)->assertCreated()->assertJsonPath('data.person.identity_number', '00000000000')->assertJsonPath('data.verification.ready_for_review', false);
        $id = $response->json('data.id');
        $this->postJson('/api/v1/orders', $payload, $createHeaders)->assertCreated()->assertJsonPath('data.id', $id);
        $payload['person']['last_name'] = 'Distinta';
        $this->postJson('/api/v1/orders', $payload, $createHeaders)->assertConflict();
        $this->getJson('/api/v1/orders', $headers)->assertOk()->assertJsonMissingPath('data.0.person')->assertDontSee('00000000000');
        foreach (['identity_front', 'selfie'] as $purpose) {
            $this->post('/api/v1/orders/'.$id.'/attachments', ['file' => UploadedFile::fake()->image('evidencia.png'), 'purpose' => $purpose], $headers + ['Content-Type' => 'multipart/form-data'])->assertCreated()->assertJsonPath('data.purpose', $purpose)->assertDontSee('file_path');
        }
        $this->getJson('/api/v1/orders/'.$id, $headers)->assertOk()->assertJsonPath('data.verification.ready_for_review', true)->assertHeader('Cache-Control', 'no-store, private');
        $otherHeaders = $this->token('other-integration@example.test');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/orders/'.$id, $otherHeaders)->assertForbidden()->assertDontSee('00000000000');
        $this->getJson('/api/v1/orders/'.$id.'/attachments', $otherHeaders)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $order = Order::where('public_id', $id)->firstOrFail();
        $checks = [];
        foreach (['university-degree', 'exequatur', 'identity-verification'] as $code) {
            $checks[$code] = ['outcome' => 'inconclusive', 'notes' => 'INFORME HUMANO SINTÉTICO RESERVADO'];
        }
        $result = app(StoreResult::class)->handle($admin, $order, 'Resumen sintético reservado', null, $checks);
        $this->getJson('/api/v1/orders/'.$id.'/deliveries', $headers)->assertConflict()->assertDontSee('SINTÉTICO');
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => $order->items->mapWithKeys(fn ($item) => [$item->id => 100])->all()]);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'completed']);
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 300, 'method' => 'cash', 'reference' => 'SYNTHETIC-API-1']);
        $this->getJson('/api/v1/orders/'.$id.'/deliveries', $headers)->assertOk()->assertJsonCount(0, 'data');
        app(PublishResult::class)->handle($admin, $result);
        $this->getJson('/api/v1/orders/'.$id.'/deliveries', $headers)->assertOk()->assertJsonPath('data.0.verification_findings.method', 'manual')->assertJsonCount(3, 'data.0.verification_findings.checks');
    }

    public function test_required_person_fields_and_client_chosen_provider_results_are_rejected(): void
    {
        Notification::fake();
        $this->seed();
        $headers = $this->token('validate@example.test') + ['Idempotency-Key' => 'validate-person-001'];
        $data = PersonVerificationWorkflowTest::data(['identity-verification']);
        unset($data['person']);
        $this->postJson('/api/v1/orders', $data, $headers)->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['person']]]);
        $data = PersonVerificationWorkflowTest::data(['identity-verification']);
        $data['person']['biometric_result'] = 'verified';
        $this->postJson('/api/v1/orders', $data, $headers)->assertUnprocessable();
        $data = PersonVerificationWorkflowTest::data(['identity-verification']);
        $data['items'][0]['quantity'] = 2;
        $this->postJson('/api/v1/orders', $data, $headers)->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['items.0.quantity']]]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pre_profile_idempotency_retry_returns_only_the_matching_historical_order(): void
    {
        Notification::fake();
        $this->seed();
        $headers = $this->token('legacy-person@example.test') + ['Idempotency-Key' => 'pre-profile-001'];
        $client = User::where('email', 'legacy-person@example.test')->firstOrFail();
        $payload = ['subject' => 'Caso anterior', 'details' => 'Solicitud anterior a la ficha de persona', 'consent' => true, 'items' => [['service_code' => 'exequatur', 'quantity' => 1]]];
        $order = Order::create(['customer_account_id' => $client->customer_account_id, 'requested_by' => $client->id, 'subject' => $payload['subject'], 'details' => $payload['details'], 'consented_at' => now()]);
        $service = Service::where('code', 'exequatur')->firstOrFail();
        $order->items()->create(['service_id' => $service->id, 'service_name' => $service->name, 'quantity' => 1]);
        DB::table('idempotency_keys')->insert(['user_id' => $client->id, 'key' => 'pre-profile-001', 'request_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'order_id' => $order->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/api/v1/orders', $payload, $headers)->assertCreated()->assertJsonPath('data.id', $order->public_id)->assertJsonPath('data.person', null);
        $payload['subject'] = 'Cambio no permitido';
        $this->postJson('/api/v1/orders', $payload, $headers)->assertConflict();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_people', 0);
    }
}
