<?php

namespace App\Domain\Locations\Enums;

enum LocationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Inactive => 'Inactiva',
            self::Suspended => 'Suspendida',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'positive',
            self::Inactive => 'neutral',
            self::Suspended => 'danger',
        };
    }
}
