<?php
namespace App\Console\Commands;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
class CreateAdmin extends Command
{
    protected $signature = 'portal:create-admin {email} {--name=Administrador ATD}';
    protected $description = 'Crea o promueve un administrador y envía un enlace para establecer una contraseña';
    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->error('Correo no válido.'); return self::FAILURE; }
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill(['name' => $user->name ?: $this->option('name'), 'role' => Role::Admin, 'is_active' => true, 'password' => Str::random(64), 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        AuditEvent::create(['action' => 'admin.bootstrapped', 'subject_type' => 'user', 'subject_id' => (string) $user->id]);
        $status = Password::sendResetLink(['email' => $email]);
        if ($status !== Password::ResetLinkSent) { $this->error('Cuenta preparada; no se pudo enviar el enlace. Revise el correo y use la recuperación de contraseña.'); return self::FAILURE; }
        $this->info('Administrador preparado. Enlace enviado; deberá verificar su correo y configurar 2FA.');
        return self::SUCCESS;
    }
}
