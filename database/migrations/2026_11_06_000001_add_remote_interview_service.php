<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $service = $this->service();
        $service['options'] = json_encode($service['options'], JSON_THROW_ON_ERROR);
        DB::table('services')->upsert(
            [$service + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]],
            ['code'],
            ['is_active', 'updated_at'],
        );
    }

    public function down(): void
    {
        DB::table('services')->where('code', $this->service()['code'])->update(['is_active' => false, 'updated_at' => now()]);
    }

    private function service(): array
    {
        return json_decode(file_get_contents(database_path('data/service-remote-interview-2026-11-06.json')), true, 32, JSON_THROW_ON_ERROR);
    }
};
