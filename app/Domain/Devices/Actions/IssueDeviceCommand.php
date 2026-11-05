<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Devices\Enums\DeviceCommandStatus;
use App\Domain\Devices\Enums\DeviceCommandType;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceCommand;
use Illuminate\Support\Collection;

class IssueDeviceCommand
{
    /**
     * Queue one or more asynchronous commands for a device.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Device $device, DeviceCommandType $command, array $payload = [], ?int $ttlMinutes = 30): DeviceCommand
    {
        return DeviceCommand::query()->create([
            'device_id' => $device->id,
            'command' => $command,
            'payload' => $payload ?: null,
            'status' => DeviceCommandStatus::Pending,
            'created_by' => auth()->id(),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }

    /**
     * @param  Collection<int, Device>  $devices
     * @param  array<string, mixed>  $payload
     * @return Collection<int, DeviceCommand>
     */
    public function handleMany(Collection $devices, DeviceCommandType $command, array $payload = []): Collection
    {
        return $devices->map(fn (Device $device) => $this->handle($device, $command, $payload));
    }
}
