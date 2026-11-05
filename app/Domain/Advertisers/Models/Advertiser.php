<?php

namespace App\Domain\Advertisers\Models;

use App\Domain\Advertisers\Enums\AdvertiserStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playlists\Models\Playlist;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'name', 'slug', 'status', 'contact_name', 'contact_email', 'contact_phone',
    'billing_name', 'billing_tax_id', 'billing_email', 'billing_address', 'metadata',
])]
class Advertiser extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AdvertiserStatus::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return HasMany<Playlist, $this>
     */
    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    /**
     * @return MorphMany<MediaAsset, $this>
     */
    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'owner');
    }

    /**
     * @param  Builder<Advertiser>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('contact_email', 'like', "%{$term}%");
        }));
    }
}
