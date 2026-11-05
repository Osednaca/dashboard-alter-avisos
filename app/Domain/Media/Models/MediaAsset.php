<?php

namespace App\Domain\Media\Models;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Campaigns\Models\CampaignCreative;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\ProcessingStatus;
use App\Domain\Playback\Models\PlaybackEvent;
use App\Domain\Playlists\Models\PlaylistItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'owner_type', 'owner_id', 'type', 'filename', 'original_name', 'storage_path',
    'thumbnail_path', 'mime_type', 'width', 'height', 'duration', 'filesize',
    'checksum', 'processing_status', 'metadata',
])]
class MediaAsset extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'processing_status' => ProcessingStatus::class,
            'metadata' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'filesize' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<PlaylistItem, $this>
     */
    public function playlistItems(): HasMany
    {
        return $this->hasMany(PlaylistItem::class);
    }

    /**
     * @return HasMany<CampaignCreative, $this>
     */
    public function campaignCreatives(): HasMany
    {
        return $this->hasMany(CampaignCreative::class);
    }

    /**
     * @return HasMany<PlaybackEvent, $this>
     */
    public function playbackEvents(): HasMany
    {
        return $this->hasMany(PlaybackEvent::class);
    }

    public function scopeAdvertising(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('owner_type')
            ->orWhere('owner_type', (new Advertiser)->getMorphClass()));
    }

    public function getUrlAttribute(): string
    {
        if ($this->type === MediaType::LiveStream) {
            return $this->metadata['live']['original_url'];
        }

        return Storage::disk(config('signage.media_disk'))->url($this->storage_path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        if ($this->type === MediaType::LiveStream) {
            return '';
        }
        $path = $this->thumbnail_path ?: $this->storage_path;

        return Storage::disk(config('signage.media_disk'))->url($path);
    }

    public function getHumanFilesizeAttribute(): string
    {
        $bytes = (int) $this->filesize;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $i === 0 ? 0 : 1).' '.$units[$i];
    }

    public function getResolutionAttribute(): ?string
    {
        return $this->width && $this->height ? "{$this->width}×{$this->height}" : null;
    }

    public function getFormattedDurationAttribute(): ?string
    {
        if ($this->duration === null) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($this->duration, 60), $this->duration % 60);
    }

    /**
     * A creative attached to an active campaign must not be hard deleted.
     */
    public function isLockedByActiveCampaign(): bool
    {
        if (CampaignCreative::query()->where('configuration->fallback_media_id', $this->id)
            ->whereHas('campaign', fn (Builder $q) => $q->whereIn('status', ['active', 'scheduled']))->exists()) {
            return true;
        }

        return $this->campaignCreatives()
            ->whereHas('campaign', fn (Builder $q) => $q->where('status', 'active'))
            ->exists();
    }

    /**
     * @param  Builder<MediaAsset>  $query
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('processing_status', ProcessingStatus::Ready->value);
    }

    /**
     * @param  Builder<MediaAsset>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where('filename', 'like', "%{$term}%"));
    }
}
