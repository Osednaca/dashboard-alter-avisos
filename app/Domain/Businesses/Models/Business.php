<?php

namespace App\Domain\Businesses\Models;

use App\Domain\Analytics\Models\BusinessDailyStat;
use App\Domain\Businesses\Enums\BusinessCategory;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\City;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playlists\Models\Playlist;
use App\Domain\Scheduling\Models\ContentSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'slug', 'logo_path', 'category', 'status', 'timezone',
    'contact_name', 'contact_email', 'contact_phone', 'metadata',
])]
class Business extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'category' => BusinessCategory::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'city_business')->withTimestamps();
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_users')
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();
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
     * @return HasMany<ContentSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ContentSchedule::class);
    }

    /**
     * @return HasMany<BusinessDailyStat, $this>
     */
    public function dailyStats(): HasMany
    {
        return $this->hasMany(BusinessDailyStat::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? Storage::disk(config('signage.media_disk'))->url($this->logo_path)
            : null;
    }

    /**
     * @param  Builder<Business>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('contact_email', 'like', "%{$term}%");
        }));
    }

    public function deviceCount(): int
    {
        return $this->devices_count ?? $this->devices()->count();
    }
}
