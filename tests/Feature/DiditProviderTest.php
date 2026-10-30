<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Orders\CreateOrder;
use App\Models\AuditEvent;
use App\Models\IdentitySession;
use App\Models\Setting;
use App\Services\DiditClient;
use App\Services\DiditSignature;
use App\Services\ProcessIdentitySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IdentityWebPayment;
use Tests\TestCase;

class DiditProviderTest extends TestCase
{
    use RefreshDatabase;

    public static function configure(): void
    {
        config(['identity.didit' => ['api_key' => 'synthetic-private-api-key', 'webhook_secret' => 'synthetic-signing-secret-123456789', 'application_id' => '11111111-1111-4111-8111-111111111111', 'workflow_id' => '22222222-2222-4222-8222-222222222222', 'environment' => 'sandbox', 'production_approved' => false, 'privacy_approved' => true]]);
    }

    private function makeIdentitySession(): IdentitySession
    {
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        self::configure();
        $this->seed();
        Setting::put('identity_verification_mode', 'didit');
        $client = app(CreateNewUser::class)->create(['name' => 'Synthetic', 'email' => 'didit@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $order = app(CreateOrder::class)->handle($client, PersonVerificationWorkflowTest::data(['identity-verification']));
        IdentityWebPayment::settle($order);

        return IdentitySession::create(['order_id' => $order->id, 'order_person_id' => $order->person->id, 'application_id' => config('identity.didit.application_id'), 'workflow_id' => config('identity.didit.workflow_id'), 'environment' => 'sandbox', 'state' => 'pending', 'consented_at' => now(), 'notice_version' => config('identity.notice_version')]);
    }

    public static function decision(IdentitySession $session, string $status = 'Approved'): array
    {
        return ['session_id' => $session->provider_session_id, 'workflow_id' => $session->workflow_id, 'vendor_data' => $session->public_id, 'environment' => 'sandbox', 'status' => $status,
            'id_verifications' => [['status' => 'Approved', 'issuing_state' => 'DOM', 'verification_method' => 'document', 'personal_number' => $session->person->profile['identity_number']]],
            'liveness_checks' => [['status' => 'Approved', 'method' => 'ACTIVE_3D']], 'face_matches' => [['status' => 'Approved']]];
    }

    public static function workflow(IdentitySession $session): array
    {
        return ['workflow_id' => $session->workflow_id, 'status' => 'published', 'is_archived' => false, 'features' => 'OCR + LIVENESS + FACE_MATCH',
            'workflow_graph' => ['nodes' => ['life' => ['feature' => 'LIVENESS', 'config' => ['face_liveness_method' => 'ACTIVE_3D']]]]];
    }

    private function webhook(array $body, bool $valid = true)
    {
        $raw = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $canonical = json_encode(DiditSignature::canonical(json_decode($raw)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
        $signature = $valid ? hash_hmac('sha256', $canonical, config('identity.didit.webhook_secret')) : str_repeat('0', 64);

        return $this->call('POST', '/api/providers/didit/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE_V2' => $signature, 'HTTP_X_TIMESTAMP' => (string) now()->timestamp], $raw);
    }

    private function envelope(IdentitySession $session): array
    {
        return ['event_id' => (string) Str::uuid(), 'session_id' => $session->provider_session_id ?? (string) Str::uuid(), 'application_id' => $session->application_id, 'workflow_id' => $session->workflow_id, 'environment' => $session->environment, 'vendor_data' => $session->public_id, 'webhook_type' => 'status.updated', 'status' => 'Approved', 'timestamp' => now()->timestamp, 'decision' => ['name' => 'José', 'url' => 'https://example.test/a', 'empty' => (object) [], 'keys' => (object) ['9' => 9, '10' => 10]]];
    }

    public function test_creation_is_claimed_once_and_timeout_does_not_duplicate_billable_session(): void
    {
        $session = $this->makeIdentitySession();
        Http::fake(['*/workflows/*' => Http::response(self::workflow($session)), '*/session/' => Http::failedConnection()]);
        app(ProcessIdentitySession::class)->handle($session->id);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('create_uncertain', $session->fresh()->state);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('identity_webhook_receipts', 0);
    }

    public static function creationFailures(): array
    {
        return [
            'credits' => [402, ['code' => 'insufficient_credits'], 'provider_rejected', 'insufficient_credits', true],
            'validation' => [422, ['detail' => 'invalid'], 'provider_rejected', 'invalid_request', true],
            'upstream credits error stays uncertain' => [502, ['code' => 'insufficient_credits'], 'create_uncertain', 'insufficient_credits', false],
            'rate limit stays uncertain' => [429, ['code' => 'rate_limited'], 'create_uncertain', 'rate_limited', false],
        ];
    }

    #[DataProvider('creationFailures')]
    public function test_creation_failures_keep_only_safe_diagnostics_and_never_retry_automatically(int $status, array $body, string $state, string $reason, bool $rejected): void
    {
        $session = $this->makeIdentitySession();
        Http::fake(['*/workflows/*' => Http::response(self::workflow($session)), '*/session/' => Http::response($body + ['private_data' => 'synthetic-sensitive-content'], $status)]);
        app(ProcessIdentitySession::class)->handle($session->id);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame($state, $session->fresh()->state);
        $this->assertSame('attention_required', $session->fresh()->publicState());
        $this->assertNull($session->fresh()->provider_session_id);
        Http::assertSentCount(2);
        $event = AuditEvent::where('action', 'identity.creation_failed')->sole();
        $this->assertSame(['http_status' => $status, 'failure_code' => $reason, 'definitely_rejected' => $rejected], $event->metadata);
        $this->assertStringNotContainsString('synthetic-sensitive-content', json_encode($event));
    }

    public function test_successful_create_and_reconciliation_use_only_the_fixed_server_api(): void
    {
        $session = $this->makeIdentitySession();
        $id = (string) Str::uuid();
        $body = self::decision($session, 'Not Started');
        $body['session_id'] = $id;
        $responses = Http::fakeSequence()->push(self::workflow($session))->push(['session_id' => $id, 'workflow_id' => $session->workflow_id, 'vendor_data' => $session->public_id, 'url' => 'https://verify.didit.me/session/synthetic'], 201)->push($body);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('awaiting_person', $session->fresh()->state);
        $this->assertSame($id, $session->fresh()->provider_session_id);
        Http::assertSent(fn ($request) => $request->url() === 'https://verification.didit.me/v3/session/' && $request['vendor_data'] === $session->public_id && $request['expected_details']['id_country'] === 'DOM');
        Http::assertSentCount(3);
        $this->assertNull($session->fresh()->decision);
    }

    public function test_environment_mismatch_is_diagnosable_without_exposing_or_accepting_provider_evidence(): void
    {
        $session = $this->makeIdentitySession();
        $session->update(['provider_session_id' => (string) Str::uuid(), 'environment' => 'live']);
        $body = self::decision($session, 'Not Started');
        $body['session_url'] = 'https://verify.didit.me/session/private-synthetic-url';
        $body['private_evidence'] = 'synthetic-sensitive-evidence';
        Http::fake(fn () => Http::response($body));
        $status = app(DiditClient::class)->bindingStatus($session);
        $this->assertFalse($status['ok']);
        $this->assertTrue($status['binding_matches']);
        $this->assertSame('sandbox', $status['environment']);
        $this->assertSame('live', $status['expected_environment']);
        $this->assertStringNotContainsString('private-synthetic-url', json_encode($status));
        $this->assertStringNotContainsString('synthetic-sensitive-evidence', json_encode($status));
        $this->expectException(\RuntimeException::class);
        app(DiditClient::class)->decision($session);
    }

    public function test_signed_webhook_recovers_uncertain_creation_without_importing_decisions(): void
    {
        $session = $this->makeIdentitySession();
        $session->update(['state' => 'create_uncertain']);
        $body = $this->envelope($session);
        $this->webhook($body)->assertOk();
        $this->webhook($body)->assertOk();
        $this->assertDatabaseCount('identity_webhook_receipts', 1);
        $this->assertTrue($session->fresh()->sync_requested);
        $this->assertNull($session->fresh()->decision);
        $this->assertSame($body['session_id'], $session->fresh()->provider_session_id);
        $this->assertStringNotContainsString('José', json_encode(DB::table('identity_webhook_receipts')->first()));
        Http::assertNothingSent();
    }

    public function test_forgery_replay_app_environment_and_session_mismatches_are_rejected(): void
    {
        $session = $this->makeIdentitySession();
        $session->update(['provider_session_id' => (string) Str::uuid()]);
        $body = $this->envelope($session);
        $this->webhook($body, false)->assertUnauthorized();
        $old = $body;
        $old['timestamp'] -= 301;
        $this->webhook($old)->assertUnauthorized();
        foreach (['application_id' => (string) Str::uuid(), 'workflow_id' => (string) Str::uuid(), 'environment' => 'live', 'vendor_data' => (string) Str::ulid()] as $key => $value) {
            $this->webhook(array_replace($body, [$key => $value]))->assertNotFound();
        }
        $this->webhook(array_replace($body, ['session_id' => (string) Str::uuid()]))->assertConflict();
        $this->assertDatabaseCount('identity_webhook_receipts', 0);
    }

    public function test_api_decision_requires_all_checks_and_matching_person_and_discards_private_media(): void
    {
        $session = $this->makeIdentitySession();
        $session->update(['provider_session_id' => (string) Str::uuid(), 'state' => 'awaiting_person']);
        $body = self::decision($session);
        $body['media'] = 'https://example.test/secret-document';
        $responses = Http::fakeSequence()->push($body);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('verified', $session->fresh()->decision['outcome']);
        $this->assertStringNotContainsString('secret-document', json_encode($session->fresh()->decision));
        $this->assertDatabaseCount('order_results', 1);
        $result = $session->order->results()->firstOrFail();
        $this->assertNull($result->published_at);
        $this->assertStringNotContainsString('secret-document', json_encode($result->verification_findings));
        $body['id_verifications'][0]['personal_number'] = '99999999999';
        $responses->push($body);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('inconclusive', $session->fresh()->decision['outcome']);
        $body['id_verifications'] = null;
        $responses->push($body);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('inconclusive', $session->fresh()->decision['outcome']);
    }

    public function test_only_expected_didit_https_urls_are_accepted(): void
    {
        foreach (['https://evil.test/x', 'http://verify.didit.me/x', 'https://verify.didit.me:443/x', 'https://user@verify.didit.me/x'] as $url) {
            $this->assertFalse(DiditClient::safeUrl($url));
        }
        $this->assertTrue(DiditClient::safeUrl('https://verify.didit.me/session/synthetic'));
    }

    public function test_non_active_workflow_is_stopped_before_session_creation(): void
    {
        $session = $this->makeIdentitySession();
        $workflow = self::workflow($session);
        $workflow['workflow_graph']['nodes']['life']['config']['face_liveness_method'] = 'PASSIVE';
        Http::fake(['*' => Http::response($workflow)]);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('needs_attention', $session->fresh()->state);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_passive_or_missing_liveness_method_cannot_produce_verified(): void
    {
        $session = $this->makeIdentitySession();
        $session->update(['provider_session_id' => (string) Str::uuid(), 'state' => 'awaiting_person']);
        $body = self::decision($session);
        $body['liveness_checks'][0]['method'] = 'PASSIVE';
        $responses = Http::fakeSequence()->push($body);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('inconclusive', $session->fresh()->decision['outcome']);
        $this->assertSame('active_liveness_required', $session->fresh()->decision['reason']);
        unset($body['liveness_checks'][0]['method']);
        $responses->push($body);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('inconclusive', $session->fresh()->decision['outcome']);
    }
}
