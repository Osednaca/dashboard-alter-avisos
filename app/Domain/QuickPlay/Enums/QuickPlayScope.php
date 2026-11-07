<?php

namespace App\Domain\QuickPlay\Enums;

enum QuickPlayScope: string
{
    case Devices = 'devices';
    case Cities = 'cities';
    case Businesses = 'businesses';
    case Locations = 'locations';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Devices => 'Avisos LED específicos',
            self::Cities => 'Por ciudad',
            self::Businesses => 'Por negocio',
            self::Locations => 'Destino anterior',
            self::All => 'Todos los avisos LED',
        };
    }
}
