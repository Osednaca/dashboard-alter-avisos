<?php

namespace App\Domain\Devices\Events;

use App\Domain\Devices\Models\Device;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceConnected implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Device $device) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('network')];
    }

    public function broadcastAs(): string
    {
        return 'DeviceConnected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->device->id,
            'name' => $this->device->name,
            'business_id' => $this->device->business_id,
            'at' => now()->toIso8601String(),
        ];
    }
}
