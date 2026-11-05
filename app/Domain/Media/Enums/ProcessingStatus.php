<?php

namespace App\Domain\Media\Enums;

enum ProcessingStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En cola',
            self::Processing => 'Procesando',
            self::Ready => 'Listo',
            self::Failed => 'Fallido',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Processing => 'info',
            self::Ready => 'positive',
            self::Failed => 'danger',
        };
    }
}
