<?php

namespace App\Domain\Devices\Models;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Enums\ActivationStatus;
use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code', 'device_uuid', 'status', 'business_id', 'location_id', 'device_id',
    'device_name', 'app_version', 'ip_address', 'expires_at', 'claimed_at',
])]
class DeviceActivation extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ActivationStatus::class,
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @param  Builder<DeviceActivation>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ActivationStatus::Pending->value);
    }
}
