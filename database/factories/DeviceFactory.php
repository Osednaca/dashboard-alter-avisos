<?php

namespace Database\Factories;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement([
            DeviceStatus::Online,
            DeviceStatus::Online,
            DeviceStatus::Online,
            DeviceStatus::Online,
            DeviceStatus::Offline,
            DeviceStatus::Maintenance,
            DeviceStatus::Disabled,
            DeviceStatus::PendingActivation,
        ]);

        $total = 32 * 1024 * 1024 * 1024;

        return [
            'business_id' => Business::factory(),
            'location_id' => null,
            'uuid' => (string) Str::uuid(),
            'name' => 'Pantalla '.fake()->randomElement(['Principal', 'Entrada', 'Mostrador', 'Caja', 'Ventana', 'Salón', 'Pasillo']).' '.fake()->numberBetween(1, 40),
            'activation_code' => strtoupper(Str::random(6)),
            'status' => $status->value,
            'app_version' => fake()->randomElement(['1.4.', '1.5.', '1.6.']).fake()->numberBetween(0, 9),
            'last_seen_at' => $status === DeviceStatus::Online
                ? now()->subSeconds(fake()->numberBetween(5, 300))
                : now()->subMinutes(fake()->numberBetween(30, 4000)),
            'last_ip' => fake()->ipv4(),
            'storage_total' => $total,
            'storage_free' => fake()->numberBetween(2_000_000_000, 28_000_000_000),
            'current_manifest_version' => now()->subDay()->format('YmdHis'),
            'last_sync_at' => now()->subMinutes(fake()->numberBetween(10, 2000)),
        ];
    }

    public function online(): static
    {
        return $this->state(fn () => [
            'status' => DeviceStatus::Online->value,
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(0, 5)),
        ]);
    }
}
