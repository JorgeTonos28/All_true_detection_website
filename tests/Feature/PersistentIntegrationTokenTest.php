<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Auth\ResetUserPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PersistentIntegrationTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_persistent_tokens_are_explicit_scoped_revocable_and_invalidated_on_password_reset(): void
    {
        Notification::fake();
        $this->seed();
        $user = app(CreateNewUser::class)->create(['name' => 'Automatización', 'email' => 'persistent@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post('/portal/tokens', ['name' => 'Servicio backend', 'scopes' => ['orders:read'], 'lifetime' => 'persistent'])->assertRedirect();
        $token = session('token');
        $this->assertNotNull($token);
        $this->assertNull($user->tokens()->firstOrFail()->expires_at);
        $this->app['auth']->forgetGuards();
        $this->app['auth']->guard('web')->logout();
        $this->withToken($token)->getJson('/api/v1/orders')->assertOk();
        $this->withToken($token)->postJson('/api/v1/orders', [])->assertForbidden();
        $this->travel(366)->days();
        $this->withToken($token)->getJson('/api/v1/orders')->assertOk();
        app(ResetUserPassword::class)->reset($user, ['password' => 'OtraPasswordSegura456', 'password_confirmation' => 'OtraPasswordSegura456']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_existing_creation_defaults_to_expiration_and_rejects_unknown_modes(): void
    {
        Notification::fake();
        $user = app(CreateNewUser::class)->create(['name' => 'Temporal', 'email' => 'expiring@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post('/portal/tokens', ['name' => 'Temporal', 'scopes' => ['orders:read']])->assertRedirect();
        $this->assertNotNull($user->tokens()->firstOrFail()->expires_at);
        $this->postJson('/portal/tokens', ['name' => 'Inválido', 'scopes' => ['orders:read'], 'lifetime' => 'forever-unrestricted'])->assertUnprocessable();
        $this->assertSame(1, $user->tokens()->count());
    }
}
