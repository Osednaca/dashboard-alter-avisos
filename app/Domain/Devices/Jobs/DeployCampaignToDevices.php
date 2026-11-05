<?php

namespace App\Domain\Devices\Jobs;

use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeployCampaignToDevices implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Campaign $campaign) {}

    public function handle(ResolveCampaignTargets $targets, BuildDeviceManifest $manifestBuilder): void
    {
        $deviceIds = $targets->devicesFor($this->campaign)->pluck('id');
        foreach (Device::query()->whereIn('id', $deviceIds)->get() as $device) {
            // Rebuild from current DB state; propagate failures so the queue retries.
            $manifestBuilder->handle($device);
        }
    }
}
