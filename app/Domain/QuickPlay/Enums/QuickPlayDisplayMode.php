<?php

namespace App\Domain\QuickPlay\Enums;

enum QuickPlayDisplayMode: string
{
    case Advertising = 'advertising';
    case Business = 'business';
    case Fullscreen = 'fullscreen';

    public function label(): string
    {
        return match ($this) {
            self::Advertising => 'Solo zona publicitaria',
            self::Business => 'Zona de contenido del negocio',
            self::Fullscreen => 'Pantalla completa',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Advertising => 'Se muestra en la franja de publicidad respetando el layout actual.',
            self::Business => 'Reemplaza el contenido propio del negocio sin tocar la publicidad.',
            self::Fullscreen => 'Toma el control de ambas zonas de forma temporal y restaura el layout al terminar.',
        };
    }
}
