<?php

namespace App\Domain\Devices\Enums;

enum ActivationStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Claimed => 'Reclamada',
            self::Expired => 'Expirada',
            self::Revoked => 'Revocada',
        };
    }
}
