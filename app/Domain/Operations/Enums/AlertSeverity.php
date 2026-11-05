<?php

namespace App\Domain\Operations\Enums;

enum AlertSeverity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Crítica',
            self::Warning => 'Advertencia',
            self::Info => 'Informativa',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Warning => 'warning',
            self::Info => 'info',
        };
    }
}
