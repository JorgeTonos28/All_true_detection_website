<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'hero_subtitle'],
            [
                'label' => 'Subtítulo principal',
                'value' => 'Servicios de evaluación de perfiles, detección de engaño, verificación, investigación preventiva, capacitación especializada y análisis asistido por IA para empresas, instituciones y casos particulares.',
                'group' => 'Home',
                'type' => 'textarea',
            ],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'meta_description'],
            [
                'label' => 'Descripción SEO',
                'value' => 'All True Detection ofrece evaluación de perfiles, polígrafo, verificación, antidoping, investigación, capacitación y análisis asistido por IA para decisiones corporativas más seguras.',
                'group' => 'SEO',
                'type' => 'textarea',
            ],
        );
    }

    public function down(): void
    {
        Setting::query()
            ->where('key', 'hero_subtitle')
            ->update([
                'value' => 'Servicios de evaluación de perfiles, detección de engaño, verificación, investigación preventiva y capacitación especializada para empresas, instituciones y casos particulares.',
            ]);

        Setting::query()
            ->where('key', 'meta_description')
            ->update([
                'value' => 'All True Detection ofrece evaluación de perfiles, polígrafo, verificación, antidoping, investigación y capacitación para decisiones corporativas más seguras.',
            ]);
    }
};
