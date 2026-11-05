<?php

namespace App\Domain\QuickPlay\Enums;

enum QuickPlayDeviceStatus: string
{
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Playing = 'playing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Downloading => 'Descargando',
            self::Playing => 'Reproduciendo',
            self::Completed => 'Completado',
            self::Failed => 'Fallido',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Downloading => 'info',
            self::Playing => 'accent',
            self::Completed => 'positive',
            self::Failed => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }

    public function isDelivered(): bool
    {
        return $this->isTerminal();
    }
}
