<?php

namespace App\Domain\Playlists\Models;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playlists\Enums\PlaylistStatus;
use App\Domain\Playlists\Enums\PlaylistType;
use App\Domain\Scheduling\Models\ContentSchedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'advertiser_id', 'name', 'type', 'status', 'is_schedule_managed'])]
class Playlist extends Model
{
    protected function casts(): array
    {
        return [
            'type' => PlaylistType::class,
            'status' => PlaylistStatus::class,
            'is_schedule_managed' => 'boolean',
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
     * @return BelongsTo<Advertiser, $this>
     */
    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }

    /**
     * @return HasMany<PlaylistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ContentSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ContentSchedule::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'current_playlist_id');
    }

    /**
     * @return BelongsToMany<MediaAsset, $this>
     */
    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'playlist_items')
            ->withPivot(['sort_order', 'duration', 'transition', 'configuration'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<Playlist>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where('name', 'like', "%{$term}%"));
    }
}
