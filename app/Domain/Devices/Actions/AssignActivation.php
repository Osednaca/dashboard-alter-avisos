<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Devices\Enums\ActivationStatus;
use App\Domain\Devices\Models\DeviceActivation;

class AssignActivation
{
    /**
     * Assign a pending activation code to a business and location.
     */
    public function handle(DeviceActivation $activation, int $businessId, ?int $locationId, ?string $deviceName): DeviceActivation
    {
        $activation->forceFill([
            'business_id' => $businessId,
            'location_id' => $locationId,
            'device_name' => $deviceName,
            'status' => ActivationStatus::Pending,
        ])->save();

        return $activation;
    }

    public function revoke(DeviceActivation $activation): DeviceActivation
    {
        $activation->forceFill(['status' => ActivationStatus::Revoked])->save();

        return $activation;
    }
}
