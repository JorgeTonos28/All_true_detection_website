<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only replace our original defaults; preserve any customer-authored copy.
        foreach ([
            'hero_subtitle' => ['Servicios de evaluación de perfiles, detección de engaño, verificación, investigación preventiva, capacitación especializada y análisis asistido por IA para empresas, instituciones y casos particulares.', 'Evaluaciones de personas, verificación de identidad y credenciales, capacitación y equipos para empresas, instituciones y clientes individuales.'],
            'meta_description' => ['All True Detection ofrece evaluación de perfiles, polígrafo, verificación, antidoping, investigación, capacitación y análisis asistido por IA para decisiones corporativas más seguras.', 'All True Detection ofrece polígrafo, evaluación de perfiles, antidoping, psicometría, verificación de identidad y credenciales, visitas domiciliarias, capacitación y equipos.'],
        ] as $key => [$old, $new]) {
            DB::table('settings')->where('key', $key)->where('value', $old)->update(['value' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Preserve editorial content; restore copy explicitly from a reviewed backup if necessary.
    }
};
