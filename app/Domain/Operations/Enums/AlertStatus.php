<?php

namespace App\Domain\Operations\Enums;

enum AlertStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Acknowledged => 'Reconocida',
            self::Resolved => 'Resuelta',
        };
    }
}
