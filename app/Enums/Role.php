<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Collaborator = 'collaborator';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador general', self::Collaborator => 'Colaborador', self::Client => 'Cliente'
        };
    }
}
