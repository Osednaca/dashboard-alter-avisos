<?php

namespace App\Domain\Devices\Enums;

enum DeviceCommandStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Sent => 'Enviado',
            self::Completed => 'Completado',
            self::Failed => 'Fallido',
            self::Expired => 'Expirado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'info',
            self::Completed => 'positive',
            self::Failed => 'danger',
            self::Expired => 'neutral',
        };
    }
}
