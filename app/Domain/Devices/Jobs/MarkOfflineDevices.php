<?php

namespace App\Domain\Devices\Jobs;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Events\DeviceDisconnected;
use App\Domain\Devices\Models\Device;
use App\Domain\Operations\Actions\RaiseAlert;
use App\Domain\Operations\Enums\AlertSeverity;
use App\Domain\Operations\Enums\AlertType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkOfflineDevices implements ShouldQueue
{
    use Queueable;

    public function handle(RaiseAlert $alerts): void
    {
        $threshold = now()->subMinutes(config('signage.device.offline_after_minutes'));

        Device::query()
            ->where('status', DeviceStatus::Online->value)
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $threshold))
            ->with('business')
            ->chunkById(200, function ($devices) use ($alerts) {
                foreach ($devices as $device) {
                    $device->forceFill(['status' => DeviceStatus::Offline])->saveQuietly();
                    DeviceDisconnected::dispatch($device);

                    $minutes = $device->last_seen_at?->diffInMinutes(now()) ?? 0;

                    $alerts->handle(
                        AlertType::DeviceOffline,
                        'Pantalla desconectada',
                        "«{$device->name}» de {$device->business?->name} lleva {$minutes} minutos sin reportar.",
                        AlertSeverity::Critical,
                        $device,
                        ['minutes_offline' => $minutes],
                    );
                }
            });
    }
}
