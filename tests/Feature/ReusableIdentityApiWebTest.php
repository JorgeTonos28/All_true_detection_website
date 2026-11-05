<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Identity\ManagePartnerAccess;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\UpdateOrder;
use App\Models\IdentitySession;
use App\Models\IdentitySubject;
use App\Models\Order;
use App\Services\DiditSignature;
use App\Services\ProcessIdentitySession;
use App\Support\IntegrationScopes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\IdentityWebPayment;
use Tests\Support\ReusableIdentityScenario;
use Tests\TestCase;

class ReusableIdentityApiWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_registration_and_idempotent_reverification_return_only_scoped_technical_results(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        $headers = ['Authorization' => 'Bearer '.$case->token];
        $enroll = '/api/v1/orders/'.$case->order->public_id.'/people/'.$case->order->person->public_id.'/reusable-identity';
        $this->postJson($enroll, [], $headers)->assertUnprocessable();
        $id = $this->postJson($enroll, $case->consent(), $headers)->assertCreated()->assertJsonPath('data.state', 'available')->assertDontSee('provider_reference')->json('data.id');
        $this->postJson($enroll, $case->consent(), $headers)->assertCreated()->assertJsonPath('data.id', $id);
        $this->getJson('/api/v1/identities', $headers)->assertOk()->assertJsonCount(1, 'data')->assertDontSee($case->source->person->profile['identity_number']);
        $url = '/api/v1/identities/'.$id.'/verifications';
        $response = $this->postJson($url, $case->consent(), $headers + ['Idempotency-Key' => 'api-recurring-001'])->assertStatus(202)->assertDontSee('identity_number')->assertDontSee('address');
        $orderId = $response->json('data.order_id');
        $this->postJson($url, $case->consent(), $headers + ['Idempotency-Key' => 'api-recurring-001'])->assertStatus(202)->assertJsonPath('data.order_id', $orderId);
        $order = Order::where('public_id', $orderId)->firstOrFail();
        $session = $order->person->identitySession;
        app(ProcessIdentitySession::class)->handle($session->id);
        $resultUrl = $response->json('data.identity_session_url').'/result';
        $this->getJson($resultUrl, $headers)->assertOk()->assertJsonPath('data.outcome', 'verified')->assertJsonPath('data.verification_kind', 'recurring')->assertJsonPath('data.document_reverified', false)->assertHeader('Cache-Control', 'no-store, private');
        $case->bodies[$case->source->provider_session_id]['status'] = 'Declined';
        app(ProcessIdentitySession::class)->handle($case->source->id);
        $this->getJson($resultUrl, $headers)->assertConflict();
        $this->assertTrue($session->fresh()->sync_requested);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->getJson($resultUrl, $headers)->assertOk()->assertJsonPath('data.outcome', 'inconclusive')->assertJsonPath('data.revision', 2);
        $this->assertDatabaseCount('identity_subjects', 1);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_recurring_api_rejects_media_substitution_missing_scopes_and_other_accounts(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        $subject = $case->enroll();
        $url = '/api/v1/identities/'.$subject->public_id.'/verifications';
        $headers = ['Authorization' => 'Bearer '.$case->token, 'Idempotency-Key' => 'scope-check-reuse-001'];
        $this->postJson($url, $case->consent() + ['portrait_image' => 'untrusted'], $headers)->assertUnprocessable();
        $this->postJson($url, $case->consent() + ['identity_number' => '00000000000'], $headers)->assertUnprocessable();
        $ordinary = $case->client->createToken('Ordinary', ['orders:write', 'identity:read']);
        app('auth')->forgetGuards();
        $this->postJson($url, $case->consent(), ['Authorization' => 'Bearer '.$ordinary->plainTextToken, 'Idempotency-Key' => 'scope-check-reuse-001'])->assertForbidden();
        $other = app(CreateNewUser::class)->create(['name' => 'Other synthetic', 'email' => 'other-reuse@example.test', 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'account_type' => 'individual', 'terms' => true]);
        $other->forceFill(['email_verified_at' => now()])->save();
        app(ManagePartnerAccess::class)->handle($case->admin, $other->account, ['enabled' => true, 'daily_limit' => 20]);
        $otherToken = $other->createToken('Other', IntegrationScopes::ALL);
        app('auth')->forgetGuards();
        $otherHeaders = ['Authorization' => 'Bearer '.$otherToken->plainTextToken, 'Idempotency-Key' => 'scope-check-reuse-001'];
        $this->getJson('/api/v1/identities', $otherHeaders)->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($url, $case->consent(), $otherHeaders)->assertForbidden();
        $this->postJson('/api/v1/identities/'.$subject->public_id.'/revoke', [], $otherHeaders)->assertForbidden();
        $this->assertDatabaseCount('orders', 1);
        $this->assertCount(0, $case->creates);
    }

    public function test_web_registry_and_recurrent_request_preserve_payment_and_publication_and_expire_without_renewal(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario('web');
        $this->actingAs($case->client)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('portal.orders.show', $case->order))->assertOk()->assertSee('Registrar referencia reutilizable');
        $this->post(route('portal.identities.enroll', [$case->order, $case->order->person->public_id]), $case->consent())->assertRedirect(route('portal.identities.index'));
        $subject = IdentitySubject::firstOrFail();
        $this->get(route('portal.identities.index'))->assertOk()->assertSee('Solicitar reverificación')->assertSee($subject->public_id)->assertDontSee($subject->profile['identity_number']);
        $this->post(route('portal.identities.verify', $subject), $case->consent() + ['idempotency_key' => 'web-repeat-001'])->assertRedirect();
        $order = Order::latest('id')->firstOrFail();
        $capture = route('portal.identity.store', [$order, $order->person->public_id]);
        $this->postJson($capture, $case->consent())->assertConflict();
        IdentityWebPayment::settle($order, $case->admin);
        $this->post($capture, $case->consent())->assertRedirect()->assertSessionHasNoErrors();
        $session = $order->person->identitySession()->firstOrFail();
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->get(route('portal.orders.show', $order))->assertOk()->assertDontSee('active_liveness_stored_reference_match_passed');
        app(UpdateOrder::class)->handle($case->admin, $order, ['status' => 'in_progress']);
        app(UpdateOrder::class)->handle($case->admin, $order, ['status' => 'completed']);
        app(PublishResult::class)->handle($case->admin, $order->results()->firstOrFail());
        $this->get(route('portal.orders.show', $order))->assertOk()->assertSee('Autenticación facial recurrente')->assertSee('No incluye nueva validación del documento');
        $originalExpiry = $subject->expires_at->toIso8601String();
        $this->travel(91)->days();
        $this->get(route('portal.identities.index'))->assertOk()->assertSee('Vencida');
        $this->postJson(route('portal.identities.verify', $subject), $case->consent() + ['idempotency_key' => 'web-repeat-002'])->assertConflict();
        $this->assertSame($originalExpiry, $subject->fresh()->expires_at->toIso8601String());
        $this->assertTrue($order->fresh()->canReleaseResults()); // A dated historical report does not become a fresh check.
    }

    public function test_initial_web_result_cannot_be_enrolled_before_its_release(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario('web');
        $case->order->results()->update(['published_at' => null, 'published_by' => null]);
        $this->actingAs($case->client);
        $this->get(route('portal.orders.show', $case->order))->assertOk()->assertDontSee('Registrar referencia reutilizable');
        $this->postJson(route('portal.identities.enroll', [$case->order, $case->order->person->public_id]), $case->consent())->assertConflict();
        $this->assertDatabaseCount('identity_subjects', 0);
    }

    public function test_signed_callback_recovers_the_correct_recurring_session_not_the_shared_vendor_source(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        $subject = $case->enroll();
        $this->postJson('/api/v1/identities/'.$subject->public_id.'/verifications', $case->consent(), ['Authorization' => 'Bearer '.$case->token, 'Idempotency-Key' => 'callback-reference-001'])->assertStatus(202);
        $session = IdentitySession::where('verification_kind', 'recurring')->firstOrFail();
        $session->update(['state' => 'create_uncertain']);
        $remote = (string) Str::uuid();
        $body = ['event_id' => (string) Str::uuid(), 'session_id' => $remote, 'application_id' => $session->application_id, 'environment' => $session->environment,
            'vendor_data' => $subject->provider_reference, 'metadata' => ['atd_session_id' => $session->public_id], 'webhook_type' => 'status.updated', 'status' => 'Approved', 'timestamp' => now()->timestamp];
        $raw = json_encode($body, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', json_encode(DiditSignature::canonical(json_decode($raw)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), config('identity.didit.webhook_secret'));
        $this->call('POST', '/api/providers/didit/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TIMESTAMP' => (string) now()->timestamp, 'HTTP_X_SIGNATURE_V2' => $signature], $raw)->assertOk();
        $this->assertSame($remote, $session->fresh()->provider_session_id);
        $this->assertNotSame($remote, $case->source->fresh()->provider_session_id);
        $this->assertNull($session->fresh()->decision);
    }
}
