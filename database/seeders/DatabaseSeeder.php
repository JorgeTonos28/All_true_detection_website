<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ServiceSeeder::class);

        $settings = [
            ['key' => 'company_name', 'label' => 'Nombre de la empresa', 'value' => 'All True Detection', 'group' => 'Marca'],
            ['key' => 'slogan', 'label' => 'Eslogan', 'value' => 'Evaluamos. Detectamos. Prevenimos.', 'group' => 'Marca'],
            ['key' => 'hero_title', 'label' => 'Título principal', 'value' => 'Confianza verificable para decisiones', 'group' => 'Home'],
            ['key' => 'hero_accent', 'label' => 'Texto destacado del título', 'value' => 'más seguras', 'group' => 'Home'],
            ['key' => 'hero_subtitle', 'label' => 'Subtítulo principal', 'value' => 'Servicios de evaluación de perfiles, detección de engaño, verificación, investigación preventiva, capacitación especializada y análisis asistido por IA para empresas, instituciones y casos particulares.', 'group' => 'Home', 'type' => 'textarea'],
            ['key' => 'primary_cta_label', 'label' => 'Texto del botón principal', 'value' => 'Solicitar evaluación', 'group' => 'Home'],
            ['key' => 'phone', 'label' => 'Teléfono', 'value' => '+1 (829) 534 5678', 'group' => 'Contacto'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'value' => '18295345678', 'group' => 'Contacto'],
            ['key' => 'email', 'label' => 'Correo', 'value' => 'info@alltruedetection.com', 'group' => 'Contacto'],
            ['key' => 'address', 'label' => 'Dirección', 'value' => 'Ciudad de México, MX', 'group' => 'Contacto'],
            ['key' => 'schedule', 'label' => 'Horario', 'value' => 'Lun - Vie 9:00 - 18:00', 'group' => 'Contacto'],
            ['key' => 'instagram_url', 'label' => 'Instagram', 'value' => '', 'group' => 'Redes'],
            ['key' => 'tiktok_url', 'label' => 'TikTok', 'value' => '', 'group' => 'Redes'],
            ['key' => 'facebook_url', 'label' => 'Facebook', 'value' => '', 'group' => 'Redes'],
            ['key' => 'youtube_url', 'label' => 'YouTube', 'value' => '', 'group' => 'Redes'],
            ['key' => 'x_url', 'label' => 'X', 'value' => '', 'group' => 'Redes'],
            ['key' => 'meta_description', 'label' => 'Descripción SEO', 'value' => 'All True Detection ofrece evaluación de perfiles, polígrafo, verificación, antidoping, investigación, capacitación y análisis asistido por IA para decisiones corporativas más seguras.', 'group' => 'SEO', 'type' => 'textarea'],
            ['key' => 'logo_path', 'label' => 'Logo cargado', 'value' => null, 'group' => 'Marca', 'type' => 'file'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->firstOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }

        Setting::query()->firstOrCreate(['key' => 'favicon_path'], ['label' => 'Favicon', 'group' => 'Marca', 'type' => 'file']);
    }
}
