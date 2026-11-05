<?php

namespace App\Domain\QuickPlay\Actions;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use Illuminate\Database\Eloquent\Collection;

class ResolveQuickPlayTargets
{
    /**
     * Resolve the screens targeted by a quick play. Disabled and not-yet-active
     * devices are always excluded; offline devices are still kept so the UI can
     * report that delivery was not possible.
     *
     * @param  array{device_ids?: array<int, int>, business_ids?: array<int, int>, location_ids?: array<int, int>}  $targets
     * @return Collection<int, Device>
     */
    public function devices(QuickPlayScope $scope, array $targets = [], ?int $businessId = null): Collection
    {
        return Device::query()
            ->when($businessId !== null, fn ($q) => $q->where('business_id', $businessId))
            ->whereNotIn('status', [
                DeviceStatus::Disabled->value,
                DeviceStatus::PendingActivation->value,
            ])
            ->when($scope === QuickPlayScope::Devices, fn ($q) => $q->whereIn('id', $targets['device_ids'] ?? []))
            ->when($scope === QuickPlayScope::Businesses, fn ($q) => $q->whereIn('business_id', $targets['business_ids'] ?? []))
            ->when($scope === QuickPlayScope::Locations, fn ($q) => $q->whereIn('location_id', $targets['location_ids'] ?? []))
            ->with(['business:id,name', 'location:id,name,city'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $targets
     */
    public function count(QuickPlayScope $scope, array $targets = []): int
    {
        return $this->devices($scope, $targets)->count();
    }
}
