<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\RecoverAccountPassword;
use App\Notifications\VerifyAccountEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class InvitationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_invitation_can_set_password_verify_email_and_enter_portal(): void
    {
        Notification::fake();
        config(['app.url' => 'http://127.0.0.1:8000']);
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post('/portal/users', ['name' => 'Cliente invitado', 'email' => 'invitado@example.com', 'role' => 'client'])->assertRedirect();
        $client = User::where('email', 'invitado@example.com')->firstOrFail();
        $notification = Notification::sent($client, RecoverAccountPassword::class)->first();
        $url = $notification->toMail($client)->actionUrl;
        $this->assertStringStartsWith('http://127.0.0.1:8000/reset-password/', $url);
        $this->assertTrue(Password::tokenExists($client, $notification->token));

        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Establezca su contraseña');
        $this->post('/reset-password', ['email' => $client->email, 'token' => $notification->token, 'password' => 'PasswordCliente123', 'password_confirmation' => 'PasswordCliente123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('PasswordCliente123', $client->fresh()->password));
        $this->assertFalse(Password::tokenExists($client, $notification->token));
        $this->post('/login', ['email' => $client->email, 'password' => 'PasswordCliente123'])->assertRedirect('/portal');
        $this->get('/portal')->assertRedirect(route('verification.notice'));
        $verification = Notification::sent($client, VerifyAccountEmail::class)->first();
        $verificationUrl = $verification->toMail($client)->actionUrl;
        $this->assertStringStartsWith('http://127.0.0.1:8000/email/verify/', $verificationUrl);
        $this->get($verificationUrl)->assertRedirect();
        $this->assertTrue($client->fresh()->hasVerifiedEmail());
        $this->get('/portal')->assertOk();
        $this->get('/portal/users')->assertForbidden();
    }

    public function test_queued_auth_links_keep_the_configured_origin_and_ignore_request_host(): void
    {
        config(['app.url' => 'https://portal.example.com']);
        app('url')->forceRootUrl('http://localhost');
        $user = User::factory()->create();
        $reset = new RecoverAccountPassword('test-token');
        $verify = new VerifyAccountEmail;
        config(['app.url' => 'http://localhost']);

        $reset = unserialize(serialize($reset));
        $verify = unserialize(serialize($verify));
        $this->assertStringStartsWith('https://portal.example.com/reset-password/', $reset->toMail($user)->actionUrl);
        $this->assertStringStartsWith('https://portal.example.com/email/verify/', $verify->toMail($user)->actionUrl);
        $this->assertStringStartsWith('http://localhost/', route('login'));
    }

    public function test_admin_resend_reports_throttling_and_a_new_link_invalidates_the_old_one(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $user = User::factory()->unverified()->create();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $url = '/portal/users/'.$user->id.'/resend';
        $this->post($url)->assertSessionHasNoErrors();
        $old = Notification::sent($user, RecoverAccountPassword::class)->first();
        $this->post($url)->assertSessionHasErrors('invitation');
        Notification::assertSentToTimes($user, RecoverAccountPassword::class, 1);
        $this->travel(61)->seconds();
        $this->post($url)->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, RecoverAccountPassword::class, 2);
        $this->assertFalse(Password::tokenExists($user, $old->token));
    }
}
