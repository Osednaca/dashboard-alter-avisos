<?php

namespace App\Domain\Devices\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id', 'recorded_at', 'app_version', 'available_storage',
    'manifest_version', 'player_status', 'network_status', 'diagnostics',
])]
class DeviceHeartbeat extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
            'available_storage' => 'integer',
            'diagnostics' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
