<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'campaign_id', 'stat_date', 'playbacks_count', 'completed_count', 'failures',
    'unique_devices', 'unique_businesses', 'total_duration', 'completion_rate',
])]
class CampaignDailyStat extends Model
{
    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'completion_rate' => 'decimal:2',
            'playbacks_count' => 'integer',
            'completed_count' => 'integer',
            'failures' => 'integer',
            'unique_devices' => 'integer',
            'unique_businesses' => 'integer',
            'total_duration' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function advertiser(): ?Advertiser
    {
        return $this->campaign?->advertiser;
    }
}
