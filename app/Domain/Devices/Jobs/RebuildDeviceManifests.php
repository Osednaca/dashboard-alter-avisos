<?php

namespace App\Domain\Devices\Jobs;

use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RebuildDeviceManifests implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public array $deviceIds) {}

    public function handle(BuildDeviceManifest $builder): void
    {
        // Jobs carry IDs, never a snapshot of a campaign that may have been deleted.
        foreach (Device::query()->whereIn('id', $this->deviceIds)->where('manifest_dirty', true)->get() as $device) {
            $builder->handle($device);
        }
    }
}
