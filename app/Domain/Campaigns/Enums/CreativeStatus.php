<?php

namespace App\Domain\Campaigns\Enums;

enum CreativeStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Paused => 'Pausada',
            self::Archived => 'Archivada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'positive',
            self::Paused => 'warning',
            self::Archived => 'neutral',
        };
    }
}
