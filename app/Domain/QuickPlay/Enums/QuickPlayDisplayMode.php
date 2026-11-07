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
            self::Advertising => 'Envío histórico',
            self::Business => 'Envío histórico',
            self::Fullscreen => 'Pantalla completa',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Advertising => 'No es posible verificar cómo se mostró este envío histórico.',
            self::Business => 'No es posible verificar cómo se mostró este envío histórico.',
            self::Fullscreen => 'Se envía a pantalla completa y después se reanuda el contenido programado.',
        };
    }
}
