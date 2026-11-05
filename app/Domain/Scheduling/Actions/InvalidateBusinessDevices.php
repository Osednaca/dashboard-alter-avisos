<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Devices\Models\Device;
use App\Domain\Scheduling\Jobs\RefreshBusinessManifests;

class InvalidateBusinessDevices
{
    /** Call inside the content mutation transaction, before dispatching a rebuild. */
    public function handle(int $businessId): void
    {
        // Include every location and disabled screen: moves and later reenabling
        // must not retain content from before the edit. Polling can rebuild even
        // when the queued refresh is delayed or no worker is running.
        Device::query()->where('business_id', $businessId)->update(['manifest_dirty' => true]);
        RefreshBusinessManifests::dispatch($businessId)->afterCommit();
    }
}
