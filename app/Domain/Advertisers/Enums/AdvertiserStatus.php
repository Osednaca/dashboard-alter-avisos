<?php

namespace App\Domain\Advertisers\Enums;

enum AdvertiserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Prospect = 'prospect';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Prospect => 'Prospecto',
            self::Suspended => 'Suspendido',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'positive',
            self::Inactive => 'neutral',
            self::Prospect => 'info',
            self::Suspended => 'danger',
        };
    }
}
