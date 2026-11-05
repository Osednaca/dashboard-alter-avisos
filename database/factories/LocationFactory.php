<?php

namespace Database\Factories;

use App\Domain\Businesses\Models\Business;
use App\Domain\Locations\Enums\LocationStatus;
use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * Colombian cities with approximate coordinates for realistic geodata.
     *
     * @var array<int, array{0: string, 1: string, 2: float, 3: float}>
     */
    public const CITIES = [
        ['Bogotá', 'Cundinamarca', 4.7110, -74.0721],
        ['Medellín', 'Antioquia', 6.2442, -75.5812],
        ['Cali', 'Valle del Cauca', 3.4516, -76.5320],
        ['Barranquilla', 'Atlántico', 10.9685, -74.7813],
        ['Cartagena', 'Bolívar', 10.3910, -75.4794],
        ['Bucaramanga', 'Santander', 7.1193, -73.1227],
        ['Pereira', 'Risaralda', 4.8133, -75.6961],
        ['Manizales', 'Caldas', 5.0703, -75.5138],
        ['Santa Marta', 'Magdalena', 11.2408, -74.1990],
        ['Cúcuta', 'Norte de Santander', 7.8939, -72.5078],
        ['Ibagué', 'Tolima', 4.4389, -75.2322],
        ['Villavicencio', 'Meta', 4.1420, -73.6266],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$city, $state, $lat, $lng] = fake()->randomElement(self::CITIES);

        return [
            'business_id' => Business::factory(),
            'name' => fake()->randomElement(['Sede', 'Local', 'Sucursal', 'Punto']).' '.fake()->streetName(),
            'city' => $city,
            'state' => $state,
            'country' => 'Colombia',
            'address' => fake()->streetAddress(),
            'latitude' => $lat + fake()->randomFloat(4, -0.02, 0.02),
            'longitude' => $lng + fake()->randomFloat(4, -0.02, 0.02),
            'timezone' => 'America/Bogota',
            'status' => fake()->randomElement([
                LocationStatus::Active,
                LocationStatus::Active,
                LocationStatus::Active,
                LocationStatus::Inactive,
            ])->value,
        ];
    }
}
