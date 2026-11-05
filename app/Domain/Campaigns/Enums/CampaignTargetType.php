<?php

namespace App\Domain\Campaigns\Enums;

enum CampaignTargetType: string
{
    case Business = 'business';
    case BusinessCategory = 'business_category';
    case Location = 'location';
    case City = 'city';
    case State = 'state';
    case Country = 'country';
    case Device = 'device';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Negocio',
            self::BusinessCategory => 'Categoría de negocio',
            self::Location => 'Ubicación',
            self::City => 'Ciudad',
            self::State => 'Departamento',
            self::Country => 'País',
            self::Device => 'Pantalla',
        };
    }

    /**
     * Whether the target stores a foreign id in `target_id`.
     */
    public function isEntity(): bool
    {
        return in_array($this, [
            self::Business,
            self::Location,
            self::Device,
        ], true);
    }
}
