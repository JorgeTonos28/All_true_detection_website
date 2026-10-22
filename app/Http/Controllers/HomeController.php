<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    public function __invoke()
    {
        $settings = array_merge($this->defaults(), Setting::values());

        $logoUrl = filled($settings['logo_path'] ?? null)
            ? Storage::url($settings['logo_path'])
            : asset('assets/images/brand-lockup-light.png');

        return view('home', [
            'settings' => $settings,
            'brand' => [
                'logo_url' => $logoUrl,
                'mark_url' => asset('assets/images/brand-mark.png'),
                'dark_logo_url' => asset('assets/images/brand-lockup-dark.png'),
                'whatsapp_url' => $this->whatsappUrl($settings['whatsapp'] ?? ''),
            ],
            'services' => $this->services(),
            'sectors' => $this->sectors(),
            'socialLinks' => $this->socialLinks($settings),
            'process' => $this->process(),
            'faqs' => $this->faqs(),
        ]);
    }

    private function defaults(): array
    {
        return [
            'company_name' => 'All True Detection',
            'slogan' => 'Evaluamos. Detectamos. Prevenimos.',
            'hero_title' => 'Confianza verificable para decisiones',
            'hero_accent' => 'más seguras',
            'hero_subtitle' => 'Servicios de evaluación de perfiles, detección de engaño, verificación, investigación preventiva, capacitación especializada y análisis asistido por IA.',
            'primary_cta_label' => 'Solicitar evaluación',
            'phone' => '+1 (829) 534 5678',
            'whatsapp' => '18295345678',
            'email' => 'info@alltruedetection.com',
            'address' => 'Ciudad de México, MX',
            'schedule' => 'Lun - Vie 9:00 - 18:00',
            'meta_description' => 'Evaluación de perfiles, polígrafo, verificación, investigación preventiva y análisis asistido por IA para decisiones más seguras.',
            'instagram_url' => '',
            'tiktok_url' => '',
            'facebook_url' => '',
            'youtube_url' => '',
            'x_url' => '',
        ];
    }

    private function whatsappUrl(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        return $digits
            ? "https://wa.me/{$digits}"
            : '#contacto';
    }

    private function services(): array
    {
        return [
            ['title' => 'Evaluaciones con Polígrafo', 'text' => 'Preempleo, rutina, permanencia e investigación específica con protocolos documentados.', 'icon' => 'pulse'],
            ['title' => 'Detección de Engaño', 'text' => 'Paquete integral que combina entrevista estructurada, evaluación con polígrafo, análisis asistido por IA y revisión técnica para escenarios donde se requiere mayor claridad.', 'icon' => 'scan'],
            ['title' => 'Verificación de Perfiles', 'text' => 'Revisión profesional de información relevante para comprender mejor el perfil, su trayectoria y posibles áreas de atención.', 'icon' => 'file'],
            ['title' => 'Análisis con IA Aplicada', 'text' => 'Paquete complementario para cruzar información, detectar patrones, organizar hallazgos y fortalecer reportes con inteligencia analítica.', 'icon' => 'ai'],
            ['title' => 'Visitas Domiciliarias', 'text' => 'Confirmación de entorno, residencia y coherencia de datos declarados.', 'icon' => 'home'],
            ['title' => 'Investigaciones', 'text' => 'Apoyo a procesos internos, fraude, conducta irregular y debida diligencia.', 'icon' => 'search'],
            ['title' => 'Pruebas Antidoping', 'text' => 'Detección preventiva con manejo confidencial y enfoque de cumplimiento.', 'icon' => 'lab'],
            ['title' => 'Pruebas Psicométricas', 'text' => 'Perfil laboral, competencias, personalidad y variables de riesgo humano.', 'icon' => 'mind'],
            ['title' => 'Capacitación y Equipos', 'text' => 'Entrenamientos especializados, sensores, materiales y asesoría técnica.', 'icon' => 'training'],
        ];
    }

    private function sectors(): array
    {
        return [
            'Corporativo y empresarial',
            'Recursos humanos',
            'Seguridad privada',
            'Gobierno e instituciones',
            'Educación',
            'Salud',
            'Sector financiero',
            'Hotelería y turismo',
        ];
    }

    private function socialLinks(array $settings): array
    {
        $platforms = [
            'instagram_url' => ['label' => 'Instagram', 'slug' => 'instagram'],
            'tiktok_url' => ['label' => 'TikTok', 'slug' => 'tiktok'],
            'facebook_url' => ['label' => 'Facebook', 'slug' => 'facebook'],
            'youtube_url' => ['label' => 'YouTube', 'slug' => 'youtube'],
            'x_url' => ['label' => 'X', 'slug' => 'x'],
        ];

        return collect($platforms)
            ->map(function (array $platform, string $key) use ($settings): ?array {
                $url = trim((string) ($settings[$key] ?? ''));

                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    return null;
                }

                return [
                    'label' => $platform['label'],
                    'slug' => $platform['slug'],
                    'url' => $url,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function process(): array
    {
        return [
            ['title' => 'Contacto inicial', 'text' => 'Escuchamos el caso y el objetivo de la evaluación.'],
            ['title' => 'Análisis de necesidad', 'text' => 'Definimos alcance, riesgos, ruta técnica y uso de IA cuando aporte valor.'],
            ['title' => 'Autorización', 'text' => 'Validamos consentimientos, datos y condiciones.'],
            ['title' => 'Aplicación técnica', 'text' => 'Ejecutamos pruebas, verificaciones, investigaciones o análisis asistido.'],
            ['title' => 'Informe claro', 'text' => 'Entregamos resultados accionables, confidenciales y mejor estructurados.'],
        ];
    }

    private function faqs(): array
    {
        return [
            ['q' => '¿Qué tan confiables son las evaluaciones?', 'a' => 'La confiabilidad depende del servicio, el contexto y la información disponible. Trabajamos con protocolos, análisis asistido por IA cuando aplica y reportes prudentes para apoyar decisiones mejor fundamentadas.'],
            ['q' => '¿El análisis con IA sustituye la evaluación profesional?', 'a' => 'No. La IA funciona como apoyo analítico para organizar información, identificar señales y fortalecer hallazgos; la interpretación final se mantiene bajo criterio técnico profesional.'],
            ['q' => '¿La información se maneja de forma confidencial?', 'a' => 'Sí. Cada caso se trata con acceso restringido, discreción operativa y criterios de protección de datos sensibles.'],
            ['q' => '¿Cuánto tarda un proceso de evaluación?', 'a' => 'Depende del tipo de servicio, volumen y ubicación. En la consulta inicial se define una ruta y un plazo estimado antes de iniciar.'],
            ['q' => '¿Pueden atender empresas y personas?', 'a' => 'La oferta puede adaptarse a organizaciones, instituciones y casos particulares, siempre que el alcance sea legal, ético y documentado.'],
        ];
    }
}
