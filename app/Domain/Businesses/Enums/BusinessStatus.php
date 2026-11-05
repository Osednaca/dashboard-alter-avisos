<?php

namespace App\Domain\Businesses\Enums;

enum BusinessStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Onboarding = 'onboarding';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Suspended => 'Suspendido',
            self::Onboarding => 'En incorporación',
            self::Inactive => 'Inactivo',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'positive',
            self::Suspended => 'danger',
            self::Onboarding => 'info',
            self::Inactive => 'neutral',
        };
    }
}
