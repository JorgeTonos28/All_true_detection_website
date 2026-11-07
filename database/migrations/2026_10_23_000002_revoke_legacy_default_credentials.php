<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // The old seeder used this publicly known credential. Invalidate only matching legacy accounts.
        DB::table('users')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                if (Hash::check('Admin123!', $user->password)) {
                    DB::table('users')->where('id', $user->id)->update(['password' => Hash::make(Str::random(64)), 'remember_token' => Str::random(60), 'is_active' => false]);
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                    DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->delete();
                }
            }
        });
    }

    public function down(): void
    {
        // Never restore a known default password or revoked credentials.
    }
};
