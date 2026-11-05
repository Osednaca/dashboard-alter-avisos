<?php

namespace App\Domain\QuickPlay\Enums;

enum QuickPlayStatus: string
{
    case Sending = 'sending';
    case Partial = 'partial';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Sending => 'Enviando',
            self::Partial => 'Entrega parcial',
            self::Completed => 'Completado',
            self::Failed => 'Fallido',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Sending => 'info',
            self::Partial => 'warning',
            self::Completed => 'positive',
            self::Failed => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Partial, self::Failed], true);
    }
}
