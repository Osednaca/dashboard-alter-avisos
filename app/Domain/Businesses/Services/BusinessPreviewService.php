<?php

namespace App\Domain\Businesses\Services;

use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DevicePreviewService;

class BusinessPreviewService
{
    public function __construct(protected DevicePreviewService $preview) {}

    public function forDevice(Device $device): array
    {
        return $this->preview->forDevice($device);
    }
}
