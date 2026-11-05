<?php

namespace App\Domain\Devices\Models;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\Layout;
use App\Domain\Playback\Models\PlaybackEvent;
use App\Domain\Playlists\Models\Playlist;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'business_id', 'location_id', 'uuid', 'name', 'activation_code', 'status',
    'app_version', 'storage_total', 'storage_free', 'current_manifest_version',
    'pending_manifest_version', 'current_layout_id', 'current_playlist_id', 'metadata',
])]
#[Hidden(['device_token_hash', 'admin_pin_hash', 'recovery_key_hash'])]
class Device extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'manifest_dirty' => 'boolean',
            'metadata' => 'array',
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'token_revoked_at' => 'datetime',
            'storage_total' => 'integer',
            'storage_free' => 'integer',
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
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Layout, $this>
     */
    public function currentLayout(): BelongsTo
    {
        return $this->belongsTo(Layout::class, 'current_layout_id');
    }

    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function currentPlaylist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'current_playlist_id');
    }

    /**
     * @return HasMany<DeviceHeartbeat, $this>
     */
    public function heartbeats(): HasMany
    {
        return $this->hasMany(DeviceHeartbeat::class);
    }

    /**
     * @return HasMany<DeviceManifest, $this>
     */
    public function manifests(): HasMany
    {
        return $this->hasMany(DeviceManifest::class);
    }

    /**
     * @return HasMany<DeviceCommand, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    /**
     * @return HasMany<PlaybackEvent, $this>
     */
    public function playbackEvents(): HasMany
    {
        return $this->hasMany(PlaybackEvent::class);
    }

    public function isOnline(): bool
    {
        return $this->status === DeviceStatus::Online;
    }

    public function isReachable(): bool
    {
        if ($this->status === DeviceStatus::Disabled) {
            return false;
        }

        $threshold = now()->subMinutes(config('signage.device.offline_after_minutes'));

        return $this->last_seen_at !== null && $this->last_seen_at->greaterThan($threshold);
    }

    public function storageUsagePercentage(): ?float
    {
        if (! $this->storage_total) {
            return null;
        }

        return round((($this->storage_total - (int) $this->storage_free) / $this->storage_total) * 100, 1);
    }

    /**
     * Issue a new plaintext device token, storing only its hash.
     */
    public function issueToken(?int $ttlDays = null, bool $preserveRecovery = false): string
    {
        // Other requests may have enrolled or revoked this persisted device.
        // Refresh first so clearing fields cannot be skipped as unchanged.
        $this->refresh();
        $plain = Str::random(64);

        $this->forceFill([
            'device_token_hash' => hash('sha256', $plain),
            'recovery_key_hash' => $preserveRecovery ? $this->recovery_key_hash : null,
            'token_revoked_at' => null,
            'token_expires_at' => now()->addDays($ttlDays ?? config('signage.device.token_ttl_days')),
        ])->save();

        return $plain;
    }

    public function revokeToken(): void
    {
        $this->refresh();
        $this->forceFill([
            'device_token_hash' => null,
            'recovery_key_hash' => null,
            'token_revoked_at' => now(),
        ])->save();
    }

    public function markSeen(): void
    {
        $this->forceFill([
            'last_seen_at' => now(),
            'status' => DeviceStatus::Online,
        ])->save();
    }

    public static function generateActivationCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
            $code = preg_replace('/[^A-Z0-9]/', 'X', $code);
        } while (static::query()->where('activation_code', $code)->exists());

        return $code;
    }

    /**
     * @param  Builder<Device>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('uuid', 'like', "%{$term}%")
                ->orWhere('activation_code', 'like', "%{$term}%");
        }));
    }

    /**
     * @param  Builder<Device>  $query
     */
    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where('status', $status));
    }
}
