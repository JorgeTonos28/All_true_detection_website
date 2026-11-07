<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_requires_confirmation_and_recovery_codes_are_single_use(): void
    {
        $user = User::factory()->create();
        $user->createToken('Old integration', ['orders:read']);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post('/user/two-factor-authentication')->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertSame(0, $user->tokens()->count());
        $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
        $this->post('/user/confirmed-two-factor-authentication', ['code' => $code])->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $recovery = $user->fresh()->recoveryCodes()[0];
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect('/portal');
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recovery, $user->fresh()->recoveryCodes());
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_legacy_default_password_is_invalidated_without_affecting_other_accounts(): void
    {
        $legacy = User::factory()->create(['password' => 'Admin123!']);
        $normal = User::factory()->create(['password' => 'PersonalPassword123']);
        $migration = require database_path('migrations/2026_10_23_000002_revoke_legacy_default_credentials.php');
        $migration->up();
        $this->assertFalse($legacy->fresh()->is_active);
        $this->assertFalse(Hash::check('Admin123!', $legacy->fresh()->password));
        $this->assertTrue($normal->fresh()->is_active);
        $this->assertTrue(Hash::check('PersonalPassword123', $normal->fresh()->password));
    }
}
