<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetTwoFactor extends Command
{
    protected $signature = 'portal:reset-two-factor {email} {--confirmed-identity : Operaciones ya verificó la identidad del titular}';

    protected $description = 'Recuperación excepcional de 2FA, con revocación de sesiones y tokens';

    public function handle(): int
    {
        if (! $this->option('confirmed-identity')) {
            $this->error('Verifique la identidad por un canal independiente antes de usar --confirmed-identity.');

            return self::FAILURE;
        }
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('Usuario no encontrado.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record(null, 'auth.two_factor_operator_reset', $user);
        });
        $this->info('2FA reiniciada. Se revocaron sesiones y tokens; el personal deberá configurarla de nuevo antes de operar.');

        return self::SUCCESS;
    }
}
