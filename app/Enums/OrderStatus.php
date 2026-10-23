<?php
namespace App\Enums;
enum OrderStatus: string
{
    case Submitted = 'submitted';
    case Reviewing = 'reviewing';
    case Quoted = 'quoted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    public function label(): string
    {
        return match ($this) { self::Submitted => 'Recibida', self::Reviewing => 'En revisión', self::Quoted => 'Cotizada', self::InProgress => 'En proceso', self::Completed => 'Completada', self::Cancelled => 'Cancelada' };
    }
}
