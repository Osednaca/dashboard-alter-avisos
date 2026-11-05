<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Businesses\Models\Business;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'stat_date', 'playbacks_count', 'completed_count', 'failures',
    'unique_devices', 'total_duration',
])]
class BusinessDailyStat extends Model
{
    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'playbacks_count' => 'integer',
            'completed_count' => 'integer',
            'failures' => 'integer',
            'unique_devices' => 'integer',
            'total_duration' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
