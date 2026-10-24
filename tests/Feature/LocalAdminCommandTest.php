<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_admin_command_creates_verified_admin_without_email(): void
    {
        config(['app.env' => 'local', 'app.debug' => true]);

        $this->artisan('portal:create-local-admin', ['email' => 'local-admin@example.com'])
            ->expectsQuestion('Contraseña local (mínimo 12 caracteres)', 'PasswordLocal123!')
            ->expectsQuestion('Repita la contraseña local', 'PasswordLocal123!')
            ->assertExitCode(0);

        $user = User::where('email', 'local-admin@example.com')->firstOrFail();

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(password_verify('PasswordLocal123!', $user->password));
    }

    public function test_local_admin_command_is_rejected_outside_local_debug_environment(): void
    {
        config(['app.env' => 'production', 'app.debug' => false]);

        $this->artisan('portal:create-local-admin', ['email' => 'local-admin@example.com'])
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'local-admin@example.com']);
    }
}
