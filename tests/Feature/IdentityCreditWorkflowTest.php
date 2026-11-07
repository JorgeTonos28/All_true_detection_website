<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Identity\ManagePartnerAccess;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\RequestIdentitySession;
use App\Enums\Role;
use App\Jobs\ProcessDiditSession;
use App\Models\IdentitySession;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\ProcessIdentitySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IdentityWebPayment;
use Tests\TestCase;

class IdentityCreditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $admin;

    private array $headers;

    private Order $order;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        DiditProviderTest::configure();
        $this->seed();
        Setting::put('identity_verification_mode', 'didit');
        $this->client = app(CreateNewUser::class)->create(['name' => 'Synthetic', 'email' => 'credit@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'account_type' => 'individual', 'terms' => true]);
        $this->client->forceFill(['email_verified_at' => now()])->save();
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->headers = ['Authorization' => 'Bearer '.$this->client->createToken('Synthetic', ['orders:read', 'orders:write', 'results:read', 'identity:verify', 'identity:read'])->plainTextToken];
        $this->order = app(CreateOrder::class)->handle($this->client, PersonVerificationWorkflowTest::data(['identity-verification']));
        $this->url = '/api/v1/orders/'.$this->order->public_id.'/people/'.$this->order->person->public_id.'/identity-session';
    }

    private function grant(bool $enabled = true, int $limit = 20): void
    {
        app(ManagePartnerAccess::class)->handle($this->admin, $this->client->account, ['enabled' => $enabled, 'daily_limit' => $limit]);
    }

    private function requestSession(): IdentitySession
    {
        $this->postJson($this->url, ['consent' => true, 'notice_version' => config('identity.notice_version')], $this->headers)->assertStatus(202)->assertJsonPath('data.billing_mode', 'partner_credit');

        return IdentitySession::firstOrFail();
    }

    public function test_credit_requires_current_account_approval_and_dedicated_scope(): void
    {
        $consent = ['consent' => true, 'notice_version' => config('identity.notice_version')];
        $this->postJson($this->url, $consent, $this->headers)->assertForbidden();
        $this->grant();
        $ordinary = ['Authorization' => 'Bearer '.$this->client->createToken('Ordinary', ['orders:write'])->plainTextToken];
        app('auth')->forgetGuards();
        $this->postJson($this->url, $consent, $ordinary)->assertForbidden();
        app('auth')->forgetGuards();
        $this->postJson($this->url, $consent + ['portrait_image' => 'arbitrary-reference'], $this->headers)->assertUnprocessable();
        $this->postJson($this->url, [], $this->headers)->assertUnprocessable();
        $session = $this->requestSession();
        $this->postJson($this->url, $consent, $this->headers)->assertStatus(202)->assertJsonPath('data.id', $session->public_id);
        $this->assertDatabaseCount('identity_sessions', 1);
        $this->getJson($this->url.'/result', $this->headers)->assertConflict();
        $this->grant(false);
        $this->getJson($this->url, $this->headers)->assertForbidden();
        $this->postJson($this->url, $consent, $this->headers)->assertForbidden();
        app(ProcessIdentitySession::class)->handle($session->id);
        Http::assertNothingSent();
    }

    public function test_live_partner_gets_automatic_technical_result_without_payment_or_manual_publication(): void
    {
        $this->grant();
        config(['identity.didit.environment' => 'live']);
        $session = $this->requestSession();
        $id = (string) Str::uuid();
        $body = DiditProviderTest::decision($session);
        $body['environment'] = 'live';
        $body['session_id'] = $id;
        Http::fakeSequence()->push(DiditProviderTest::workflow($session))->push(['session_id' => $id, 'workflow_id' => $session->workflow_id, 'vendor_data' => $session->public_id, 'url' => 'https://verify.didit.me/session/synthetic'], 201)->push($body);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->getJson($this->url.'/result', $this->headers)->assertOk()->assertJsonPath('data.outcome', 'verified')->assertJsonPath('data.revision', 1)->assertJsonPath('data.environment', 'live')->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('personal_number')->assertDontSee('verification_url');
        $this->assertNotNull($session->fresh()->credit_accepted_at);
        $this->assertSame('completed', $this->order->fresh()->status->value);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('order_results', 0);
        $this->getJson('/api/v1/orders/'.$this->order->public_id.'/results', $this->headers)->assertConflict();
        $webOrder = app(CreateOrder::class)->handle($this->client, PersonVerificationWorkflowTest::data(['identity-verification']));
        try {
            app(RequestIdentitySession::class)->handle($this->client, $webOrder, $webOrder->person, ['consent' => true, 'notice_version' => config('identity.notice_version')]);
            $this->fail('La autorización de partner no elimina el prepago web.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->expectException(HttpException::class);
        app(IssueInvoice::class)->handle($this->admin, $this->order, ['prices' => [$this->order->items->first()->id => 100]]);
    }

    public function test_reconciliation_is_metered_once_and_corrections_notify_without_sensitive_content(): void
    {
        $this->grant();
        WebhookEndpoint::create(['customer_account_id' => $this->client->customer_account_id, 'url' => 'https://partner.example.test/hook', 'secret' => Str::random(48), 'is_active' => true, 'events' => ['identity.verification.updated']]);
        $session = $this->requestSession();
        $session->update(['provider_session_id' => (string) Str::uuid(), 'state' => 'awaiting_person']);
        $body = DiditProviderTest::decision($session);
        $responses = Http::fakeSequence()->push($body)->push($body);
        $processor = app(ProcessIdentitySession::class);
        $processor->handle($session->id);
        $accepted = $session->fresh()->credit_accepted_at->toIso8601String();
        $reviewed = $session->fresh()->decision['reviewed_at'];
        $this->travel(1)->minutes();
        $processor->handle($session->id);
        $this->assertSame($accepted, $session->fresh()->credit_accepted_at->toIso8601String());
        $this->assertSame($reviewed, $session->fresh()->decision['reviewed_at']);
        $this->assertSame(1, $session->fresh()->decision_revision);
        $this->assertDatabaseCount('webhook_deliveries', 1);
        $payload = json_encode(WebhookDelivery::firstOrFail()->payload);
        foreach (['verified', 'Approved', 'identity_number', 'verification_url', 'credit@example.test'] as $private) {
            $this->assertStringNotContainsString($private, $payload);
        }
        $body['status'] = 'Kyc Expired';
        $responses->push($body);
        $processor->handle($session->id);
        $this->getJson($this->url.'/result', $this->headers)->assertOk()->assertJsonPath('data.outcome', 'inconclusive')->assertJsonPath('data.revision', 2)->assertJsonPath('data.requires_review', true);
        $body['status'] = 'Resubmitted';
        $responses->push($body);
        $processor->handle($session->id);
        $this->getJson($this->url.'/result', $this->headers)->assertConflict();
        $this->assertSame('in_progress', $this->order->fresh()->status->value);
        $this->assertDatabaseCount('identity_sessions', 1);
        $this->assertDatabaseCount('webhook_deliveries', 3);
        $this->grant(false);
        $responses->push(DiditProviderTest::decision($session));
        $processor->handle($session->id);
        $this->assertSame(4, $session->fresh()->decision_revision);
        $this->assertDatabaseCount('webhook_deliveries', 3);
        $this->getJson($this->url.'/result', $this->headers)->assertForbidden();
    }

    public function test_channel_is_immutable_partner_limit_is_enforced_and_sandbox_is_not_production(): void
    {
        $this->grant(limit: 1);
        $session = $this->requestSession();
        $consent = ['consent' => true, 'notice_version' => config('identity.notice_version')];
        try {
            app(RequestIdentitySession::class)->handle($this->client, $this->order, $this->order->person, $consent);
            $this->fail('No se puede convertir una sesión de crédito en web.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $other = app(CreateOrder::class)->handle($this->client, PersonVerificationWorkflowTest::data(['identity-verification']));
        $this->postJson('/api/v1/orders/'.$other->public_id.'/people/'.$other->person->public_id.'/identity-session', $consent, $this->headers)->assertTooManyRequests();
        $session->update(['state' => 'received', 'credit_accepted_at' => now(), 'decision_revision' => 1, 'decision' => ['outcome' => 'verified', 'reason' => 'synthetic', 'reviewed_at' => now()->toIso8601String()]]);
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $this->getJson('https://localhost'.$this->url.'/result', $this->headers)->assertConflict();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
        $this->assertDatabaseCount('identity_sessions', 1);
        Http::assertNothingSent();
    }

    public function test_credit_cannot_absorb_mixed_services_or_an_existing_web_session(): void
    {
        $this->grant();
        $consent = ['consent' => true, 'notice_version' => config('identity.notice_version')];
        $mixed = app(CreateOrder::class)->handle($this->client, PersonVerificationWorkflowTest::data(['identity-verification', 'exequatur']));
        $this->postJson('/api/v1/orders/'.$mixed->public_id.'/people/'.$mixed->person->public_id.'/identity-session', $consent, $this->headers)->assertConflict();
        IdentityWebPayment::settle($this->order);
        app(RequestIdentitySession::class)->handle($this->client, $this->order, $this->order->person, $consent);
        $this->postJson($this->url, $consent, $this->headers)->assertConflict();
        $this->getJson($this->url, $this->headers)->assertForbidden();
        $this->getJson($this->url.'/result', $this->headers)->assertForbidden();
        $this->assertDatabaseCount('identity_sessions', 1);
        Http::assertNothingSent();
    }

    public function test_multi_person_credit_completes_only_after_each_decision_and_is_tenant_isolated(): void
    {
        $this->grant();
        $data = PersonVerificationWorkflowTest::data(['identity-verification']);
        $person = $data['person'] + ['service_codes' => ['identity-verification']];
        unset($data['person']);
        $data['people'] = [$person, array_replace($person, ['identity_number' => '00000000001', 'first_name' => 'Otra'])];
        $data['items'][0]['quantity'] = 2;
        $order = app(CreateOrder::class)->handle($this->client, $data);
        $responses = Http::fakeSequence();
        foreach ($order->people as $index => $person) {
            $url = '/api/v1/orders/'.$order->public_id.'/people/'.$person->public_id.'/identity-session';
            $this->postJson($url, ['consent' => true, 'notice_version' => config('identity.notice_version')], $this->headers)->assertStatus(202);
            $session = $person->identitySession()->firstOrFail();
            $session->update(['provider_session_id' => (string) Str::uuid(), 'state' => 'awaiting_person']);
            $responses->push(DiditProviderTest::decision($session));
            app(ProcessIdentitySession::class)->handle($session->id);
            $this->assertSame($index === 0 ? 'in_progress' : 'completed', $order->fresh()->status->value);
            $this->getJson($url.'/result', $this->headers)->assertOk()->assertJsonPath('data.person_id', $person->public_id);
        }
        $other = app(CreateNewUser::class)->create(['name' => 'Other', 'email' => 'isolated-partner@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'account_type' => 'individual', 'terms' => true]);
        $other->forceFill(['email_verified_at' => now()])->save();
        app(ManagePartnerAccess::class)->handle($this->admin, $other->account, ['enabled' => true, 'daily_limit' => 20]);
        $headers = ['Authorization' => 'Bearer '.$other->createToken('Other', ['identity:read'])->plainTextToken];
        app('auth')->forgetGuards();
        $this->getJson($url.'/result', $headers)->assertForbidden();
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('order_results', 0);
    }

    public function test_suspension_during_workflow_preflight_prevents_remote_creation(): void
    {
        $this->grant();
        $session = $this->requestSession();
        Http::fake(function () use ($session) {
            $this->grant(false);

            return Http::response(DiditProviderTest::workflow($session));
        });
        app(ProcessIdentitySession::class)->handle($session->id);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        $this->assertSame('pending', $session->fresh()->state);
        $this->assertNull($session->fresh()->credit_accepted_at);
        $this->order->update(['status' => 'cancelled']);
        $this->grant();
        $this->getJson($this->url, $this->headers)->assertConflict();
        $this->getJson($this->url.'/result', $this->headers)->assertConflict();
    }

    public function test_suspended_reservations_do_not_starve_reconciliation_of_accepted_sessions(): void
    {
        $this->grant();
        $pending = $this->requestSession();
        $order = app(CreateOrder::class)->handle($this->client, PersonVerificationWorkflowTest::data(['identity-verification']));
        $url = '/api/v1/orders/'.$order->public_id.'/people/'.$order->person->public_id.'/identity-session';
        $this->postJson($url, ['consent' => true, 'notice_version' => config('identity.notice_version')], $this->headers)->assertStatus(202);
        $accepted = $order->person->identitySession()->firstOrFail();
        $accepted->update(['provider_session_id' => (string) Str::uuid(), 'state' => 'awaiting_person', 'sync_requested' => true]);
        $this->grant(false);
        Queue::fake();
        $this->artisan('portal:reconcile-identity')->assertSuccessful();
        Queue::assertPushed(ProcessDiditSession::class, fn ($job) => $job->sessionId === $accepted->id);
        Queue::assertNotPushed(ProcessDiditSession::class, fn ($job) => $job->sessionId === $pending->id);
        $this->grant();
        Queue::fake();
        $this->artisan('portal:reconcile-identity')->assertSuccessful();
        Queue::assertPushed(ProcessDiditSession::class, 2);
        Http::assertNothingSent();
    }
}
