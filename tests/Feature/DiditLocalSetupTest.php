<?php

namespace Tests\Feature;

use App\Models\IdentitySession;
use App\Models\OrderPerson;
use App\Services\DiditClient;
use App\Services\DiditSignature;
use App\Services\IdentitySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DiditLocalSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_preflights_both_workflows_without_creating_sessions_or_revealing_key(): void
    {
        DiditProviderTest::configure();
        config(['identity.didit.authentication_workflow_id' => '33333333-3333-4333-8333-333333333333', 'identity.didit.reuse_privacy_approved' => true]);
        Http::preventStrayRequests();
        Http::fake(fn ($request) => Http::response([
            'workflow_id' => str_contains($request->url(), '33333333') ? config('identity.didit.authentication_workflow_id') : config('identity.didit.workflow_id'),
            'status' => 'published', 'is_archived' => false, 'features' => str_contains($request->url(), '33333333') ? 'LIVENESS + FACE_MATCH' : 'OCR + LIVENESS + FACE_MATCH',
            'workflow_graph' => ['nodes' => ['liveness' => ['feature' => 'LIVENESS', 'config' => ['face_liveness_method' => 'ACTIVE_3D']]]],
        ]));
        $this->artisan('portal:identity-status', ['--check-provider' => true])->expectsOutputToContain('No se crearon sesiones')->doesntExpectOutputToContain(config('identity.didit.api_key'))->assertSuccessful();
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        $this->assertDatabaseCount('identity_sessions', 0);
    }

    public function test_unsigned_webhook_remains_closed_and_live_polling_needs_explicit_local_opt_in(): void
    {
        DiditProviderTest::configure();
        config(['identity.didit.local_polling' => true, 'identity.didit.webhook_secret' => null]);
        $this->app->instance('env', 'local');
        $this->assertTrue(IdentitySettings::ready());
        try {
            DiditSignature::verify(Request::create('/webhooks/didit', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{}'));
            $this->fail('No se aceptan webhooks sin firma aunque haya polling local.');
        } catch (HttpException $error) {
            $this->assertSame(503, $error->getStatusCode());
        }
        $this->app->instance('env', 'production');
        $this->assertFalse(IdentitySettings::localPolling());
        $this->assertFalse(IdentitySettings::ready());
        $this->app->instance('env', 'local');
        config(['identity.didit.environment' => 'live']);
        $this->assertFalse(IdentitySettings::ready());
        config(['identity.didit.local_live_testing' => true]);
        $this->assertTrue(IdentitySettings::ready());
        $this->app->instance('env', 'production');
        config(['identity.didit.production_approved' => true]);
        $this->assertFalse(IdentitySettings::ready());
        $this->assertFalse(IdentitySettings::localPolling());
    }

    public function test_sandbox_creates_carry_provider_guard_but_live_creates_do_not(): void
    {
        DiditProviderTest::configure();
        Http::preventStrayRequests();
        $session = new IdentitySession(['workflow_id' => config('identity.didit.workflow_id'), 'environment' => 'sandbox']);
        $session->public_id = 'synthetic';
        $session->setRelation('person', new OrderPerson(['profile' => ['first_name' => 'Synthetic', 'last_name' => 'Test']]));
        Http::fake(fn ($request) => Http::response(['session_id' => '44444444-4444-4444-8444-444444444444', 'workflow_id' => $session->workflow_id, 'vendor_data' => 'synthetic', 'url' => 'https://verify.didit.me/session/synthetic']));
        app(DiditClient::class)->create($session);
        Http::assertSent(fn ($request) => $request['sandbox_scenario'] === 'approve');
        $this->assertArrayHasKey('callback', Http::recorded()->last()[0]->data());
        config(['identity.didit.sandbox_scenario' => 'decline_document_expired']);
        app(DiditClient::class)->create($session);
        $this->assertSame('decline_document_expired', Http::recorded()->last()[0]->data()['sandbox_scenario']);
        $this->app->instance('env', 'local');
        config(['identity.didit.local_polling' => true]);
        app(DiditClient::class)->create($session);
        $this->assertArrayNotHasKey('callback', Http::recorded()->last()[0]->data());
        $session->environment = 'live';
        config(['identity.didit.environment' => 'live']);
        app(DiditClient::class)->create($session);
        $this->assertArrayNotHasKey('sandbox_scenario', Http::recorded()->last()[0]->data());
        $this->assertArrayHasKey('callback', Http::recorded()->last()[0]->data());
    }

    public function test_live_pilot_rejects_extra_provider_features_before_any_creation(): void
    {
        DiditProviderTest::configure();
        $this->app->instance('env', 'local');
        config(['identity.didit.environment' => 'live', 'identity.didit.local_live_testing' => true]);
        Http::preventStrayRequests();
        foreach (['AML', 'FACE_SEARCH'] as $extra) {
            Http::fake(fn () => Http::response([
                'workflow_id' => config('identity.didit.workflow_id'), 'status' => 'published', 'is_archived' => false,
                'features' => 'OCR + LIVENESS + FACE_MATCH + '.$extra,
                'workflow_graph' => ['nodes' => ['liveness' => ['feature' => 'LIVENESS', 'config' => ['face_liveness_method' => 'ACTIVE_3D']]]],
            ]));
            try {
                app(DiditClient::class)->assertActiveWorkflow(new IdentitySession(['workflow_id' => config('identity.didit.workflow_id')]));
                $this->fail('El piloto no puede activar comprobaciones adicionales.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('solo admite', $error->getMessage());
            }
            Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        }
    }
}
