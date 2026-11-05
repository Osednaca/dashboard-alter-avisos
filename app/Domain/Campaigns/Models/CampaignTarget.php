<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\Campaigns\Enums\CampaignTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'campaign_id', 'target_type', 'target_id', 'target_value', 'is_exclusion',
])]
class CampaignTarget extends Model
{
    protected function casts(): array
    {
        return [
            'target_type' => CampaignTargetType::class,
            'target_id' => 'integer',
            'is_exclusion' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
