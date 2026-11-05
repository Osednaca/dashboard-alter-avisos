<?php

namespace App\Domain\Devices\Enums;

enum DeviceStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Maintenance = 'maintenance';
    case Disabled = 'disabled';
    case PendingActivation = 'pending_activation';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'En línea',
            self::Offline => 'Desconectada',
            self::Maintenance => 'Mantenimiento',
            self::Disabled => 'Deshabilitada',
            self::PendingActivation => 'Pendiente de activación',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Online => 'positive',
            self::Offline => 'danger',
            self::Maintenance => 'warning',
            self::Disabled => 'neutral',
            self::PendingActivation => 'info',
        };
    }

    public function isHealthy(): bool
    {
        return $this === self::Online;
    }
}
