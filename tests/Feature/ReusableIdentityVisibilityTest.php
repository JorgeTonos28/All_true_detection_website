<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ReusableIdentityScenario;
use Tests\TestCase;

class ReusableIdentityVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_web_module_hides_navigation_and_blocks_valid_enrollment(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario('web');
        config(['portal.reusable_identity_ui_enabled' => false]);
        $this->actingAs($case->client);
        $this->get('/portal')->assertOk()->assertDontSee(route('portal.identities.index'), false);
        $this->get(route('portal.identities.index'))->assertNotFound();
        $this->post(route('portal.identities.enroll', [$case->order, $case->order->person->public_id]), $case->consent())->assertNotFound();
        $this->assertDatabaseCount('identity_subjects', 0);
    }

    public function test_disabling_web_module_preserves_authorized_api_for_the_isolated_pilot(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        config(['portal.reusable_identity_ui_enabled' => false]);
        $this->getJson('/api/v1/identities', ['Authorization' => 'Bearer '.$case->token])->assertOk();
    }
}
