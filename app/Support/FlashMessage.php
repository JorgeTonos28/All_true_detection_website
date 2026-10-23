<?php

namespace App\Support;

class FlashMessage
{
    public static function label(string $value): string
    {
        return match ($value) {
            'two-factor-authentication-enabled' => 'Escanee el código y confirme la activación de 2FA.',
            'two-factor-authentication-confirmed' => 'Autenticación de dos factores confirmada.',
            'two-factor-authentication-disabled' => 'Autenticación de dos factores desactivada.',
            'recovery-codes-generated' => 'Se generaron nuevos códigos de recuperación.',
            'password-updated' => 'Contraseña actualizada.',
            'verification-link-sent' => 'Se envió un nuevo enlace de verificación a su correo.',
            default => $value,
        };
    }
}
