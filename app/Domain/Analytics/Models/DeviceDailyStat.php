<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Devices\Models\Device;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id', 'stat_date', 'playbacks_count', 'completed_count', 'failures',
    'total_duration', 'uptime_seconds',
])]
class DeviceDailyStat extends Model
{
    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'playbacks_count' => 'integer',
            'completed_count' => 'integer',
            'failures' => 'integer',
            'total_duration' => 'integer',
            'uptime_seconds' => 'integer',
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
