<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Enums\Role;
use App\Models\ApiRequestLog;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegrationPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_monitoring_is_scoped_and_all_sections_render(): void
    {
        Notification::fake();
        $this->seed();
        $user = app(CreateNewUser::class)->create(['name' => 'Cliente integración', 'email' => 'panel@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $other = app(CreateNewUser::class)->create(['name' => 'Otra empresa', 'email' => 'otherpanel@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $id = (string) Str::uuid();
        foreach ([$user, $other] as $actor) {
            ApiRequestLog::create(['request_id' => $actor->id === $user->id ? $id : (string) Str::uuid(), 'user_id' => $actor->id, 'customer_account_id' => $actor->customer_account_id, 'method' => 'GET', 'route' => 'api/v1/orders', 'status' => 200, 'duration_ms' => 10, 'created_at' => now()]);
            $actor->createToken($actor->id === $user->id ? 'Conexión propia' : 'Conexión ajena', ['orders:read'], now()->addDays(30));
        }
        $endpoint = WebhookEndpoint::create(['customer_account_id' => $other->customer_account_id, 'url' => 'https://private.example.com/atd', 'secret' => 'never-show']);
        $delivery = $endpoint->deliveries()->create(['event_id' => (string) Str::uuid(), 'event_type' => 'endpoint.test', 'payload' => [], 'next_attempt_at' => now()]);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
        foreach (['overview', 'tokens', 'webhooks', 'deliveries', 'requests'] as $section) {
            $this->get(route('portal.integrations', ['section' => $section]))->assertOk()->assertDontSee('Conexión ajena')->assertDontSee('private.example.com')->assertDontSee('never-show');
        }
        $this->get(route('portal.integrations', ['section' => 'requests']))->assertSee($id);
        $this->get(route('portal.integrations.delivery', $delivery))->assertForbidden();
        $this->post(route('portal.integrations.rotate', $endpoint))->assertForbidden();
        $this->get(route('portal.security'))->assertOk();
    }

    public function test_admin_can_supervise_and_revoke_but_collaborator_cannot(): void
    {
        Notification::fake();
        $this->seed();
        config(['portal.require_staff_two_factor' => false]);
        $client = User::factory()->create();
        $token = $client->createToken('Supervisada', ['orders:read'], now()->addDay());
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('portal.integrations', ['section' => 'tokens']))->assertOk()->assertSee('Supervisada')->assertDontSee($token->plainTextToken);
        $this->delete(route('portal.integrations.revoke', $token->accessToken->id))->assertRedirect();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->actingAs(User::factory()->create(['role' => Role::Collaborator]))->get(route('portal.integrations'))->assertForbidden();
    }
}
