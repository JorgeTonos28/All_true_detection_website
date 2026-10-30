<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrder;
use App\Services\ProcessIdentitySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ReusableIdentityScenario;
use Tests\TestCase;

class IdentityRedTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_write_scope_cannot_export_the_capture_capability_by_post_retry(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        $order = app(CreateOrder::class)->handle($case->client, PersonVerificationWorkflowTest::data(['identity-verification']));
        $path = '/api/v1/orders/'.$order->public_id.'/people/'.$order->person->public_id.'/identity-session';
        $full = ['Authorization' => 'Bearer '.$case->token];
        $this->postJson($path, ['consent' => true, 'notice_version' => config('identity.notice_version')], $full)->assertStatus(202);
        $session = $order->person->identitySession()->firstOrFail();
        $session->update(['state' => 'awaiting_person', 'verification_url' => 'https://verify.didit.me/session/synthetic-private-capability']);
        $token = $case->client->createToken('Write only', ['identity:verify']);
        app('auth')->forgetGuards();
        $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];
        $this->getJson($path, $headers)->assertForbidden();
        $this->postJson($path, ['consent' => true, 'notice_version' => config('identity.notice_version')], $headers)
            ->assertStatus(202)->assertJsonPath('data.verification_url', null)->assertDontSee('synthetic-private-capability');
    }

    public function test_unconfirmed_provider_update_with_outage_withholds_the_previous_verdict(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        $case->readFailure = true;
        $case->source->update(['sync_requested' => true]);
        app(ProcessIdentitySession::class)->handle($case->source->id);
        $path = '/api/v1/orders/'.$case->order->public_id.'/people/'.$case->order->person->public_id.'/identity-session/result';
        $this->getJson($path, ['Authorization' => 'Bearer '.$case->token])->assertConflict();
        $case->readFailure = false;
        app(ProcessIdentitySession::class)->handle($case->source->id);
        $this->getJson($path, ['Authorization' => 'Bearer '.$case->token])->assertOk()->assertJsonPath('data.outcome', 'verified');
    }

    public function test_web_publication_is_temporarily_held_during_an_unconfirmed_update(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario('web');
        $this->assertTrue($case->order->fresh()->canReleaseResults());
        $case->source->update(['sync_requested' => true]);
        $this->assertFalse($case->order->fresh()->canReleaseResults());
        app(ProcessIdentitySession::class)->handle($case->source->id);
        $this->assertTrue($case->order->fresh()->canReleaseResults());
    }
}
