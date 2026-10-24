<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateLocalAdmin extends Command
{
    protected $signature = 'portal:create-local-admin {email} {--name=Administrador local ATD}';

    protected $description = 'Crea un administrador local sin depender del correo (solo APP_ENV=local y APP_DEBUG=true)';

    public function handle(): int
    {
        if (config('app.env') !== 'local' || ! config('app.debug')) {
            $this->error('Este comando solo está disponible con APP_ENV=local y APP_DEBUG=true.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Correo no válido.');

            return self::INVALID;
        }

        $password = $this->secret('Contraseña local (mínimo 12 caracteres)');
        if (! is_string($password) || strlen($password) < 12 || strlen($password) > 128) {
            $this->error('La contraseña debe tener entre 12 y 128 caracteres.');

            return self::INVALID;
        }

        if ($password !== $this->secret('Repita la contraseña local')) {
            $this->error('Las contraseñas no coinciden.');

            return self::INVALID;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $user->name ?: $this->option('name'),
            'password' => $password,
            'role' => Role::Admin,
            'is_active' => true,
            'customer_account_id' => null,
            'email_verified_at' => now(),
            'google_id' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        AuditEvent::create(['action' => 'local.admin_bootstrapped', 'subject_type' => 'user', 'subject_id' => (string) $user->id]);

        $this->info("Administrador local preparado: {$email}");
        $this->line('Inicie sesión en /login. La contraseña no se envió por correo.');

        return self::SUCCESS;
    }
}
