<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Frozen input: publish a new dated file for future catalog changes.
        $catalog = json_decode(file_get_contents(database_path('data/service-catalog-2026-11.json')), true, 32, JSON_THROW_ON_ERROR);
        foreach ($catalog as $position => $service) {
            $code = $service['code'];
            unset($service['code']);
            $service['options'] = json_encode($service['options'], JSON_THROW_ON_ERROR);
            $values = $service + ['sort_order' => $position, 'is_active' => true, 'updated_at' => now()];
            if (DB::table('services')->where('code', $code)->exists()) {
                DB::table('services')->where('code', $code)->update($values);
            } else {
                DB::table('services')->insert($values + ['code' => $code, 'created_at' => now()]);
            }
        }
        DB::table('services')->whereIn('code', ['deception-detection', 'applied-ai', 'investigations'])->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        throw new RuntimeException('El catálogo comercial se restaura con una migración revisada; no se borran servicios ni expedientes.');
    }
};
