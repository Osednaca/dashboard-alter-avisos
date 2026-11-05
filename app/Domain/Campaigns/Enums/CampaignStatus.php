<?php

namespace App\Domain\Campaigns\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Scheduled => 'Programada',
            self::Active => 'Activa',
            self::Paused => 'Pausada',
            self::Completed => 'Finalizada',
            self::Archived => 'Archivada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Scheduled => 'info',
            self::Active => 'positive',
            self::Paused => 'warning',
            self::Completed => 'neutral',
            self::Archived => 'neutral',
        };
    }

    public function isLive(): bool
    {
        return $this === self::Active;
    }
}
