<?php

namespace App\Domain\Users\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Invited = 'invited';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Suspended => 'Suspendido',
            self::Invited => 'Invitado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'positive',
            self::Suspended => 'danger',
            self::Invited => 'info',
        };
    }
}
