<?php

namespace App\Domain\Devices\Events;

use App\Domain\Devices\Models\DeviceCommand;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceCommandCompleted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public DeviceCommand $command) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('network'), new PrivateChannel('devices.'.$this->command->device_id)];
    }

    public function broadcastAs(): string
    {
        return 'DeviceCommandCompleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'command_id' => $this->command->id,
            'device_id' => $this->command->device_id,
            'command' => $this->command->command->value,
            'status' => $this->command->status->value,
            'at' => now()->toIso8601String(),
        ];
    }
}
