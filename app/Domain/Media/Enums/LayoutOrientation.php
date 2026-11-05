<?php

namespace App\Domain\Media\Enums;

enum LayoutOrientation: string
{
    case Landscape = 'landscape';
    case Portrait = 'portrait';

    public function label(): string
    {
        return match ($this) {
            self::Landscape => 'Horizontal',
            self::Portrait => 'Vertical',
        };
    }
}
