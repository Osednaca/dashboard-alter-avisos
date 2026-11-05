<?php

namespace App\Domain\Devices\Enums;

enum ManifestStatus: string
{
    case Pending = 'pending';
    case Current = 'current';
    case Superseded = 'superseded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Current => 'Activo',
            self::Superseded => 'Reemplazado',
            self::Failed => 'Fallido',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Current => 'positive',
            self::Superseded => 'neutral',
            self::Failed => 'danger',
        };
    }
}
