<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Jobs\RebuildDeviceManifests;
use App\Domain\Devices\Models\Device;

class InvalidateCampaignDevices
{
    public function targets(Campaign $campaign): array
    {
        return app(ResolveCampaignTargets::class)->devicesFor($campaign)->pluck('devices.id')->all();
    }

    public function handle(array $deviceIds): void
    {
        foreach (array_chunk(array_values(array_unique($deviceIds)), 100) as $ids) {
            Device::query()->whereIn('id', $ids)->update(['manifest_dirty' => true]);
            RebuildDeviceManifests::dispatch($ids)->afterCommit();
        }
    }
}
