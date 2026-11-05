<?php

namespace App\Domain\Devices\Jobs;

use App\Domain\Devices\Models\DeviceHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneDeviceHeartbeats implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $cutoff = now()->subDays(config('signage.device.heartbeat_retention_days'));

        DeviceHeartbeat::query()
            ->where('recorded_at', '<', $cutoff)
            ->delete();
    }
}
