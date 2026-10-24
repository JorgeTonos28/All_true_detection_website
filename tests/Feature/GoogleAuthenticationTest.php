<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function google(string $id = 'google-123', string $email = 'google@example.com', bool $verified = true): GoogleUser
    {
        return (new GoogleUser)->setRaw(['verified_email' => $verified])->map(['id' => $id, 'email' => $email, 'name' => 'Google Client']);
    }

    public function test_google_registration_creates_verified_client_and_account(): void
    {
        Socialite::shouldReceive('driver->user')->once()->andReturn($this->google());
        $this->get('/auth/google/callback')->assertRedirect('/portal');
        $user = User::first();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->isStaff());
        $this->assertNotNull($user->customer_account_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_does_not_link_an_existing_account_by_email(): void
    {
        $user = User::factory()->create(['email' => 'google@example.com']);
        Socialite::shouldReceive('driver->user')->once()->andReturn($this->google());
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertNull($user->fresh()->google_id);
        $this->assertGuest();
    }

    public function test_state_validation_and_verified_email_are_required(): void
    {
        Socialite::shouldReceive('driver->user')->once()->andThrow(new InvalidStateException);
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        Socialite::shouldReceive('driver->user')->once()->andReturn($this->google(verified: false));
        $this->get('/auth/google/callback')->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_login_cannot_bypass_two_factor_challenge(): void
    {
        $user = User::factory()->create(['google_id' => 'google-123', 'email' => 'google@example.com', 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()]);
        Socialite::shouldReceive('driver->user')->once()->andReturn($this->google());
        $this->get('/auth/google/callback')->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_authenticated_linking_requires_same_verified_email(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);
        Socialite::shouldReceive('driver->user')->once()->andReturn($this->google());
        $this->actingAs($user)->withSession(['google.link_user' => $user->id])->get('/auth/google/callback')->assertForbidden();
        $this->assertNull($user->fresh()->google_id);
    }
}
