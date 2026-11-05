<?php

namespace App\Domain\QuickPlay\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceCommand;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quick_play_id', 'device_id', 'command_id', 'status', 'display_mode', 'duration',
    'previous_layout_id', 'previous_playlist_id', 'error', 'sent_at', 'started_at',
    'completed_at',
])]
class QuickPlayDevice extends Model
{
    protected function casts(): array
    {
        return [
            'status' => QuickPlayDeviceStatus::class,
            'display_mode' => QuickPlayDisplayMode::class,
            'duration' => 'integer',
            'sent_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<QuickPlay, $this>
     */
    public function quickPlay(): BelongsTo
    {
        return $this->belongsTo(QuickPlay::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<DeviceCommand, $this>
     */
    public function command(): BelongsTo
    {
        return $this->belongsTo(DeviceCommand::class, 'command_id');
    }

    /**
     * @param  Builder<QuickPlayDevice>  $query
     */
    public function scopeInFlight(Builder $query): Builder
    {
        return $query->whereIn('status', [
            QuickPlayDeviceStatus::Pending->value,
            QuickPlayDeviceStatus::Downloading->value,
            QuickPlayDeviceStatus::Playing->value,
        ]);
    }
}
