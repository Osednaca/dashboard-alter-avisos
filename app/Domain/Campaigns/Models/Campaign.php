<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Analytics\Models\CampaignDailyStat;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\CreativeStatus;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playback\Models\PlaybackEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'advertiser_id', 'name', 'description', 'status', 'starts_at', 'ends_at',
    'daily_start_time', 'daily_end_time', 'days_of_week', 'priority',
    'impressions_goal', 'playback_goal', 'budget', 'metadata',
    'target_screen_count', 'published_at', 'last_activity_at',
])]
class Campaign extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'days_of_week' => 'array',
            'metadata' => 'array',
            'priority' => 'integer',
            'impressions_goal' => 'integer',
            'playback_goal' => 'integer',
            'budget' => 'decimal:2',
            'target_screen_count' => 'integer',
            'published_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Advertiser, $this>
     */
    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }

    /**
     * @return HasMany<CampaignCreative, $this>
     */
    public function creatives(): HasMany
    {
        return $this->hasMany(CampaignCreative::class);
    }

    /**
     * @return HasMany<CampaignTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(CampaignTarget::class);
    }

    /**
     * @return BelongsToMany<MediaAsset, $this>
     */
    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'campaign_creatives')
            ->withPivot(['duration', 'weight', 'position', 'status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<PlaybackEvent, $this>
     */
    public function playbackEvents(): HasMany
    {
        return $this->hasMany(PlaybackEvent::class);
    }

    /**
     * @return HasMany<CampaignDailyStat, $this>
     */
    public function dailyStats(): HasMany
    {
        return $this->hasMany(CampaignDailyStat::class);
    }

    public function hasActiveCreatives(): bool
    {
        return $this->creatives()->where('status', CreativeStatus::Active->value)->exists();
    }

    public function isWithinSchedule(): bool
    {
        if ($this->starts_at && $this->starts_at->isAfter(today())) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isBefore(today())) {
            return false;
        }

        if (is_array($this->days_of_week) && $this->days_of_week !== []) {
            if (! in_array(now()->dayOfWeekIso, $this->days_of_week, true)) {
                return false;
            }
        }

        if ($this->daily_start_time && $this->daily_end_time) {
            $now = now()->format('H:i:s');

            return $now >= $this->daily_start_time && $now <= $this->daily_end_time;
        }

        return true;
    }

    public function scheduleLabel(): string
    {
        $parts = [];

        if ($this->starts_at) {
            $parts[] = $this->starts_at->format('d/m');
        }

        if ($this->ends_at) {
            $parts[] = $this->ends_at->format('d/m');
        }

        $range = $parts ? implode(' – ', $parts) : 'Sin fechas';

        if ($this->daily_start_time && $this->daily_end_time) {
            $range .= ' · '.substr($this->daily_start_time, 0, 5).'–'.substr($this->daily_end_time, 0, 5);
        }

        return $range;
    }

    /**
     * @param  Builder<Campaign>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where('name', 'like', "%{$term}%"));
    }

    /**
     * @param  Builder<Campaign>  $query
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Active->value);
    }
}
