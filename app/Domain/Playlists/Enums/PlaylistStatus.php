<?php

namespace App\Domain\Playlists\Enums;

enum PlaylistStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Activa',
            self::Archived => 'Archivada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Active => 'positive',
            self::Archived => 'neutral',
        };
    }
}
