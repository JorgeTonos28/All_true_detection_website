<?php
namespace Tests\Feature;
use App\Actions\Auth\CreateNewUser;
use App\Actions\Auth\ResetUserPassword;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
class PortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    public function test_registration_cannot_promote_itself_and_requires_consent(): void
    {
        Notification::fake();
        $data = ['name'=>'Cliente','email'=>'client@example.com','password'=>'PasswordSegura123','password_confirmation'=>'PasswordSegura123','role'=>'admin','terms'=>true];
        $this->post('/register', $data)->assertRedirect('/portal');
        $user = User::first();
        $this->assertSame(Role::Client, $user->role);
        $this->assertNotNull($user->customer_account_id);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }
    public function test_disabled_accounts_cannot_login_and_logins_are_limited(): void
    {
        $user = User::factory()->create(['is_active'=>false]);
        $this->post('/login',['email'=>$user->email,'password'=>'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        for ($i=0; $i<5; $i++) { $response = $this->post('/login',['email'=>$user->email,'password'=>'wrong']); }
        $response->assertStatus(429);
    }
    public function test_two_factor_login_does_not_establish_a_session_before_the_challenge(): void
    {
        $user = User::factory()->create(['two_factor_secret'=>encrypt('secret'),'two_factor_confirmed_at'=>now()]);
        $this->post('/login',['email'=>$user->email,'password'=>'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }
    public function test_reset_revokes_api_tokens_and_recovery_does_not_enumerate_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->createToken('integration',['orders:read']);
        app(ResetUserPassword::class)->reset($user,['password'=>'OtraPassword123','password_confirmation'=>'OtraPassword123']);
        $this->assertSame(0,$user->tokens()->count());
        $this->postJson('/forgot-password',['email'=>'missing@example.com'])->assertOk()->assertJsonStructure(['message']);
    }
    public function test_authentication_views_render_and_include_security_headers(): void
    {
        foreach (['/login','/register','/forgot-password'] as $url) {
            $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('X-Frame-Options','DENY');
        }
    }
}
