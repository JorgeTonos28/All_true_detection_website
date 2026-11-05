<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Orders\CreateOrder;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Support\PersonVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RevisedServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        $this->seed();
        Notification::fake();
        Storage::fake('local');
        Http::preventStrayRequests();
        $user = app(CreateNewUser::class)->create(['account_type' => 'individual', 'name' => 'Cliente de catálogo', 'email' => 'catalog-api@example.test', 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function test_bailiff_request_requires_its_own_credential_before_quoting(): void
    {
        $client = $this->client();
        $headers = ['Authorization' => 'Bearer '.$client->createToken('Service test', ['orders:read', 'orders:write'])->plainTextToken, 'Idempotency-Key' => 'bailiff-service-001', 'Accept' => 'application/json'];
        $response = $this->postJson('/api/v1/orders', ['subject' => 'Validación de alguacil', 'consent' => true,
            'items' => [['service_code' => 'bailiff-credentials', 'quantity' => 1]],
            'person' => ['first_name' => 'Persona', 'last_name' => 'Ficticia', 'identity_number' => '00000000000', 'purpose' => 'Verificación profesional autorizada', 'authorization_confirmed' => true, 'bailiff' => ['credential_number' => 'TEST-001', 'jurisdiction' => 'Jurisdicción de prueba']]], $headers)->assertCreated();
        $order = Order::where('public_id', $response->json('data.id'))->firstOrFail();
        $admin = User::factory()->create(['role' => Role::Admin]);
        try {
            app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items()->sole()->id => 10000]]);
            $this->fail('Missing credential must block quotation.');
        } catch (ValidationException) {
            $this->assertFalse(PersonVerification::evidenceStatus($order)['ready_for_review']);
        }
        $this->post('/api/v1/orders/'.$order->public_id.'/attachments', ['purpose' => 'bailiff_credential', 'file' => UploadedFile::fake()->image('credential.png', 80, 50)], $headers + ['Content-Type' => 'multipart/form-data'])->assertCreated();
        $this->assertTrue(PersonVerification::evidenceStatus($order->fresh())['ready_for_review']);
        $this->assertNotNull(app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items()->sole()->id => 10000]]));
        Http::assertNothingSent();
    }

    public function test_service_modes_are_validated_against_the_persons_selected_services(): void
    {
        $client = $this->client();
        $payload = ['subject' => 'Modalidad de evaluación', 'consent' => true, 'items' => [['service_code' => 'polygraph', 'quantity' => 1]],
            'people' => [['first_name' => 'Persona', 'last_name' => 'Ficticia', 'authorization_confirmed' => true, 'service_codes' => ['polygraph'], 'service_options' => ['polygraph' => 'specific']]]];
        $order = app(CreateOrder::class)->handle($client, $payload);
        $this->assertSame('specific', $order->person->profile['service_options']['polygraph']);
        $payload['people'][0]['service_options']['profile-verification'] = 'management';
        $this->expectException(ValidationException::class);
        app(CreateOrder::class)->handle($client, $payload);
    }
}
