<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

class IdentitySettings
{
    public static function ready(): bool
    {
        $config = config('identity.didit');

        return filled($config['api_key']) && is_string($config['webhook_secret']) && strlen($config['webhook_secret']) >= 16
            && Str::isUuid($config['application_id'] ?? '') && Str::isUuid($config['workflow_id'] ?? '')
            && $config['privacy_approved'] && in_array($config['environment'], ['sandbox', 'live'], true)
            && (! app()->isProduction() || ($config['environment'] === 'live' && $config['production_approved']));
    }

    public static function mode(): string
    {
        return (Setting::values()['identity_verification_mode'] ?? 'manual') === 'didit' && self::ready() ? 'didit' : 'manual';
    }

    public static function description(): string
    {
        return self::mode() === 'didit'
            ? 'Verificación de identidad con Didit: captura guiada del documento, comprobación facial y prueba de vida. Los casos que lo requieran pasan a revisión; la entrega mantiene los controles de ATD.'
            : 'Verificación de identidad mediante revisión humana de datos, selfies y documentos aportados. No incluye prueba de vida ni cotejo biométrico automático.';
    }
}
