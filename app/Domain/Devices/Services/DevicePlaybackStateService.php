<?php

namespace App\Domain\Devices\Services;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DevicePlaybackState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DevicePlaybackStateService
{
    public function __construct(private PlaybackSourceResolver $sources) {}

    public function accept(Device $device, array $report): bool
    {
        return DB::transaction(function () use ($device, $report): bool {
            $device = Device::query()->lockForUpdate()->findOrFail($device->id);
            abort_if($device->status === DeviceStatus::Disabled, 403);
            $this->sources->resolve($device, $report);
            $state = DevicePlaybackState::query()->where('device_id', $device->id)->first();
            if ($state && $state->session_id === $report['session_id'] && $state->sequence >= $report['sequence']) {
                // A duplicate is not a fresh sample or evidence of continued playback/contact.
                return false;
            }
            DevicePlaybackState::query()->updateOrCreate(['device_id' => $device->id], [
                'business_id' => $device->business_id,
                'session_id' => $report['session_id'],
                'sequence' => $report['sequence'],
                'payload' => $report,
                'received_at' => now(),
            ]);
            $device->forceFill(['last_seen_at' => now(), 'status' => DeviceStatus::Online])->saveQuietly();

            return true;
        });
    }

    /** Nullable absence; stale metadata never carries media URLs. GET does not mutate state. */
    public function preview(Device $device): ?array
    {
        $state = DevicePlaybackState::query()->where('device_id', $device->id)->first();
        $businessId = $device->business_id === null ? null : (int) $device->business_id;
        if (! $state || $state->business_id !== $businessId) {
            return null;
        }
        $report = $state->payload;
        $age = max(0, (int) $state->received_at->diffInMilliseconds(now()) + $report['sample_age_ms']);
        $fresh = $age <= 15000 && $device->status === DeviceStatus::Online;
        $zones = [];
        if ($fresh) {
            try {
                $zones = $this->sources->resolve($device, $report);
            } catch (ValidationException) {
                $fresh = false;
            }
        }

        return [
            'fresh' => $fresh,
            'received_at' => $state->received_at->toIso8601String(),
            'age_ms' => $age,
            'scene' => $report['scene'],
            'layout' => $report['layout'],
            'zones' => $fresh ? $zones : [],
            'session_id' => $state->session_id,
            'sequence' => $state->sequence,
        ];
    }
}
