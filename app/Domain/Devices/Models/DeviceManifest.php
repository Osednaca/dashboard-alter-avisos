<?php

namespace App\Domain\Devices\Models;

use App\Domain\Devices\Enums\ManifestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id', 'version', 'checksum', 'payload', 'status', 'generated_at', 'activated_at',
])]
class DeviceManifest extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => ManifestStatus::class,
            'generated_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @param  Builder<DeviceManifest>  $query
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', ManifestStatus::Current->value);
    }
}
