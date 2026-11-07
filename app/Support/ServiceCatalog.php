<?php

namespace App\Support;

class ServiceCatalog
{
    public const REMOTE_INTERVIEW = 'remote-interview-rppg';

    public static function all(): array
    {
        $catalog = json_decode(file_get_contents(database_path('data/service-catalog-2026-11.json')), true, 32, JSON_THROW_ON_ERROR);
        $catalog[] = json_decode(file_get_contents(database_path('data/service-remote-interview-2026-11-06.json')), true, 32, JSON_THROW_ON_ERROR);

        return $catalog;
    }

    public static function categories(): array
    {
        return ['assessments' => 'Evaluaciones a personas', 'verifications' => 'Verificación de personas', 'additional' => 'Capacitación y equipos'];
    }

    public static function retiredCodes(): array
    {
        return ['deception-detection', 'applied-ai', 'investigations'];
    }
}
