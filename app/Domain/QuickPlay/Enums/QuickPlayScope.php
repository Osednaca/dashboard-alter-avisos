<?php

namespace App\Domain\QuickPlay\Enums;

enum QuickPlayScope: string
{
    case Devices = 'devices';
    case Businesses = 'businesses';
    case Locations = 'locations';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Devices => 'Pantallas específicas',
            self::Businesses => 'Por negocio',
            self::Locations => 'Por ubicación',
            self::All => 'Todas las pantallas',
        };
    }
}
