<?php

namespace App\Enums;

enum AccountType: string
{
    case Individual = 'individual';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Persona individual',
            self::Business => 'Empresa',
        };
    }
}
