<?php

namespace App\Domain\Playlists\Enums;

enum PlaylistType: string
{
    case Business = 'business';
    case Advertising = 'advertising';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Negocio',
            self::Advertising => 'Publicidad',
        };
    }
}
