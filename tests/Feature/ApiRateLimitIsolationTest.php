<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ReusableIdentityScenario;
use Tests\TestCase;

class ApiRateLimitIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_upload_budget_does_not_consume_identity_budget_but_still_blocks_its_own_burst(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        $order = app(CreateOrder::class)->handle($case->client, PersonVerificationWorkflowTest::data(['identity-verification']));
        $headers = ['Authorization' => 'Bearer '.$case->token, 'Accept' => 'application/json', 'Content-Type' => 'multipart/form-data'];
        $attachments = '/api/v1/orders/'.$order->public_id.'/attachments';
        for ($index = 0; $index < 10; $index++) {
            $this->post($attachments, [], $headers)->assertUnprocessable();
        }
        $this->post($attachments, [], $headers)->assertStatus(429)->assertHeader('Retry-After');
        $identity = '/api/v1/orders/'.$order->public_id.'/people/'.$order->person->public_id.'/identity-session';
        $this->postJson($identity, ['consent' => true, 'notice_version' => config('identity.notice_version')], ['Authorization' => 'Bearer '.$case->token])->assertStatus(202);
    }
}
