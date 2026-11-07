<?php

namespace Tests\Feature;

use App\Jobs\ProcessDiditSession;
use App\Services\DiditSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\ReusableIdentityScenario;
use Tests\TestCase;

class IdentityWebhookReplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_signed_event_does_not_amplify_queue_work(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        Queue::fake();
        $source = $case->source;
        $body = ['event_id' => (string) Str::uuid(), 'session_id' => $source->provider_session_id, 'application_id' => $source->application_id,
            'workflow_id' => $source->workflow_id, 'vendor_data' => $source->providerReference(), 'environment' => $source->environment, 'webhook_type' => 'status.updated', 'timestamp' => now()->timestamp];
        $raw = json_encode($body, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', json_encode(DiditSignature::canonical(json_decode($raw)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), config('identity.didit.webhook_secret'));
        for ($index = 0; $index < 3; $index++) {
            $this->call('POST', '/api/providers/didit/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE_V2' => $signature, 'HTTP_X_TIMESTAMP' => (string) now()->timestamp], $raw)->assertOk();
        }
        Queue::assertPushed(ProcessDiditSession::class, 1);
        $this->assertTrue($source->fresh()->sync_requested);
    }
}
