<?php

namespace App\Domain\Businesses\Enums;

enum BusinessCategory: string
{
    case Restaurant = 'restaurant';
    case Cafe = 'cafe';
    case Bakery = 'bakery';
    case Bar = 'bar';
    case Gym = 'gym';
    case Clinic = 'clinic';
    case Pharmacy = 'pharmacy';
    case Retail = 'retail';
    case Supermarket = 'supermarket';
    case Salon = 'salon';
    case Hotel = 'hotel';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Restaurant => 'Restaurante',
            self::Cafe => 'Cafetería',
            self::Bakery => 'Panadería',
            self::Bar => 'Bar',
            self::Gym => 'Gimnasio',
            self::Clinic => 'Clínica',
            self::Pharmacy => 'Farmacia',
            self::Retail => 'Comercio',
            self::Supermarket => 'Supermercado',
            self::Salon => 'Salón de belleza',
            self::Hotel => 'Hotel',
            self::Other => 'Otro',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
