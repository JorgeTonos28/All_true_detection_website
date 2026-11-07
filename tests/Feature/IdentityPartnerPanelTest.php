<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Identity\ManagePartnerAccess;
use App\Actions\Orders\CreateOrder;
use App\Enums\Role;
use App\Models\IdentitySession;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IdentityPartnerPanelTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $email): User
    {
        $user = app(CreateNewUser::class)->create(['name' => 'Synthetic', 'email' => $email, 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'account_type' => 'individual', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function test_partner_approvals_require_admin_recent_password_and_staff_2fa(): void
    {
        Notification::fake();
        $this->seed();
        $client = $this->client('panel-credit@example.test');
        $url = route('portal.integrations.identity.update', $client->account);
        $data = ['enabled' => true, 'daily_limit' => 40];
        $this->actingAs($client)->withSession(['auth.password_confirmed_at' => time()])->patch($url, $data)->assertForbidden();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin)->patch($url, $data)->assertRedirect(route('portal.security'));
        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->withSession(['auth.password_confirmed_at' => 0])->patch($url, $data)->assertRedirect('/user/confirm-password');
        $this->withSession(['auth.password_confirmed_at' => time()])->patch($url, $data)->assertRedirect();
        $this->assertTrue($client->account->fresh()->hasIdentityApiCredit());
        $this->get(route('portal.integrations.identity'))->assertOk()->assertSee('Partners autorizados')->assertSee('Gestionar acceso')->assertDontSee('synthetic-private-api-key');
        $this->patch($url, ['enabled' => false, 'daily_limit' => 40])->assertRedirect();
        $this->assertFalse($client->account->fresh()->hasIdentityApiCredit());
        $this->assertDatabaseHas('audit_events', ['action' => 'identity.partner_suspended']);
    }

    public function test_client_sees_only_own_usage_and_cannot_mint_identity_scopes_without_approval(): void
    {
        Notification::fake();
        Queue::fake();
        $this->seed();
        DiditProviderTest::configure();
        Setting::put('identity_verification_mode', 'didit');
        $client = $this->client('usage-one@example.test');
        $other = $this->client('usage-other@example.test');
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($client)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('portal.integrations', ['section' => 'tokens']))->assertOk()->assertDontSee('value="identity:verify"', false);
        $this->postJson('/portal/tokens', ['name' => 'Unauthorized', 'scopes' => ['identity:verify']])->assertForbidden();
        app(ManagePartnerAccess::class)->handle($admin, $client->account, ['enabled' => true, 'daily_limit' => 10]);
        $client->unsetRelation('account');
        $this->get(route('portal.integrations', ['section' => 'tokens']))->assertOk()->assertSee('value="identity:verify"', false);
        $this->post('/portal/tokens', ['name' => 'Approved', 'scopes' => ['identity:verify', 'identity:read']])->assertRedirect()->assertSessionHas('token');
        $sessions = [];
        foreach ([$client, $other] as $actor) {
            $order = app(CreateOrder::class)->handle($actor, PersonVerificationWorkflowTest::data(['identity-verification']));
            $sessions[] = IdentitySession::create(['order_id' => $order->id, 'order_person_id' => $order->person->id,
                'application_id' => config('identity.didit.application_id'), 'workflow_id' => config('identity.didit.workflow_id'), 'environment' => 'sandbox',
                'state' => 'received', 'access_channel' => 'api_credit', 'consented_at' => now(), 'notice_version' => config('identity.notice_version'),
                'credit_authorized_by' => $admin->id, 'credit_authorized_at' => now(), 'credit_accepted_at' => now(), 'decision_revision' => 1,
                'verification_url' => 'https://verify.didit.me/session/never-show-credit-link', 'decision' => ['outcome' => 'verified', 'reason' => 'private-synthetic-reason']]);
        }
        $this->get(route('portal.integrations.identity', ['environment' => 'sandbox']))->assertOk()->assertSee($sessions[0]->public_id)->assertDontSee($sessions[1]->public_id)->assertDontSee('Gestionar acceso')->assertDontSee('private-synthetic-reason')->assertDontSee('never-show-credit-link');
        $this->get(route('portal.integrations.identity', ['account' => $other->account->public_id]))->assertForbidden();
        $this->get(route('portal.integrations.identity', ['from' => '2025-01-01', 'to' => '2026-10-28']))->assertSessionHasErrors('to');
        $this->get(route('portal.orders.show', $sessions[0]->order))->assertOk()->assertSee('Identidad API a crédito')->assertDontSee('never-show-credit-link')->assertDontSee('private-synthetic-reason');
        $this->get(route('portal.orders.index', ['billing' => 'credit']))->assertOk()->assertSee($sessions[0]->order->public_id)->assertSee('Identidad API a crédito');
        $this->get(route('portal.orders.index', ['billing' => 'unquoted']))->assertOk()->assertDontSee($sessions[0]->order->public_id);
        $this->actingAs(User::factory()->create(['role' => Role::Collaborator, 'two_factor_confirmed_at' => now()]))->get(route('portal.integrations.identity'))->assertForbidden();
    }
}
