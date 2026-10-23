<?php

namespace App\Support;

class ServiceCatalog
{
    public static function all(): array
    {
        return [
            ['code' => 'polygraph', 'name' => 'Evaluaciones con Polígrafo', 'description' => 'Preempleo, rutina, permanencia e investigación específica con protocolos documentados.'],
            ['code' => 'deception-detection', 'name' => 'Detección de Engaño', 'description' => 'Entrevista estructurada, polígrafo, análisis asistido por IA y revisión técnica.'],
            ['code' => 'profile-verification', 'name' => 'Verificación de Perfiles', 'description' => 'Revisión profesional de información, trayectoria y posibles áreas de atención.'],
            ['code' => 'applied-ai', 'name' => 'Análisis con IA Aplicada', 'description' => 'Cruce de información, patrones, organización de hallazgos y apoyo analítico.'],
            ['code' => 'home-visits', 'name' => 'Visitas Domiciliarias', 'description' => 'Confirmación de entorno, residencia y coherencia de datos declarados.'],
            ['code' => 'investigations', 'name' => 'Investigaciones', 'description' => 'Apoyo a procesos internos, fraude, conducta irregular y debida diligencia.'],
            ['code' => 'drug-screening', 'name' => 'Pruebas Antidoping', 'description' => 'Detección preventiva con manejo confidencial y enfoque de cumplimiento.'],
            ['code' => 'psychometric', 'name' => 'Pruebas Psicométricas', 'description' => 'Perfil laboral, competencias, personalidad y variables de riesgo humano.'],
            ['code' => 'training-equipment', 'name' => 'Capacitación y Equipos', 'description' => 'Entrenamientos especializados, sensores, materiales y asesoría técnica.'],
            ['code' => 'university-degree', 'name' => 'Validación de Título Universitario', 'description' => 'Verificación documental del título y contraste con la institución correspondiente.'],
            ['code' => 'exequatur', 'name' => 'Validación de Exequatur', 'description' => 'Verificación de la habilitación profesional y de la información declarada.'],
        ];
    }
}
