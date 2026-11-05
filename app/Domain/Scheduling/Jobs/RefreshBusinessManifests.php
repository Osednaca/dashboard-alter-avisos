<?php

namespace App\Domain\Scheduling\Jobs;

use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Actions\IssueDeviceCommand;
use App\Domain\Devices\Enums\DeviceCommandType;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rebuild the manifest for every screen affected by a schedule or content
 * change, and nudge reachable screens to sync immediately.
 */
class RefreshBusinessManifests implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $businessId,
        public ?int $locationId = null,
    ) {}

    public function handle(BuildDeviceManifest $builder, IssueDeviceCommand $commands): void
    {
        Device::query()
            ->where('business_id', $this->businessId)
            ->when($this->locationId, fn ($query) => $query->where('location_id', $this->locationId))
            ->whereNotIn('status', [DeviceStatus::Disabled->value, DeviceStatus::PendingActivation->value])
            ->with(['business', 'location', 'currentLayout', 'currentPlaylist.items.mediaAsset'])
            ->chunkById(100, function ($devices) use ($builder, $commands) {
                foreach ($devices as $device) {
                    try {
                        $builder->handle($device);

                        if ($device->isReachable()) {
                            $commands->handle($device, DeviceCommandType::SyncContent);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Manifest refresh failed', [
                            'device_id' => $device->id,
                            'business_id' => $this->businessId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
