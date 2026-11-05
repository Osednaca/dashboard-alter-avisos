<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\Campaigns\Enums\CreativeStatus;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'campaign_id', 'media_asset_id', 'duration', 'weight', 'position', 'status', 'configuration',
])]
class CampaignCreative extends Model
{
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'status' => CreativeStatus::class,
            'duration' => 'integer',
            'weight' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
