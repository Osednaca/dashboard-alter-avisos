<?php

namespace App\Domain\Playback\Models;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignCreative;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playlists\Models\Playlist;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id', 'campaign_id', 'creative_id', 'playlist_id', 'media_asset_id',
    'started_at', 'completed_at', 'duration_played', 'completed', 'error_code',
    'manifest_version', 'created_at', 'metadata',
])]
class PlaybackEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'duration_played' => 'integer',
            'completed' => 'boolean',
            'manifest_version' => 'integer',
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
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<CampaignCreative, $this>
     */
    public function creative(): BelongsTo
    {
        return $this->belongsTo(CampaignCreative::class, 'creative_id');
    }

    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * @param  Builder<PlaybackEvent>  $query
     */
    public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween('started_at', [$from, $to]);
    }
}
