<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrder;
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
}
