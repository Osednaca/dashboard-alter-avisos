<?php

namespace App\Domain\QuickPlay\Models;

use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use App\Domain\QuickPlay\Enums\QuickPlayStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'business_id', 'media_asset_id', 'display_mode', 'scope', 'duration', 'status',
    'targets_count', 'delivered_count', 'failed_count', 'metadata', 'expires_at',
])]
class QuickPlay extends Model
{
    use SoftDeletes;

    public function canRetry(): bool
    {
        return ($this->business_id === null || $this->display_mode === QuickPlayDisplayMode::Business)
            && ! $this->trashed() && $this->isTerminal() && $this->failed_count > 0 && empty($this->metadata['retry_id']);
    }

    protected function casts(): array
    {
        return [
            'display_mode' => QuickPlayDisplayMode::class,
            'scope' => QuickPlayScope::class,
            'status' => QuickPlayStatus::class,
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'duration' => 'integer',
            'targets_count' => 'integer',
            'delivered_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * @return HasMany<QuickPlayDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(QuickPlayDevice::class);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Recompute the aggregate counters and overall status from the device rows.
     */
    public function refreshProgress(): self
    {
        $this->loadMissing('devices');

        $targets = $this->devices->count();
        $delivered = $this->devices
            ->where('status', QuickPlayDeviceStatus::Completed)
            ->count();
        $failed = $this->devices
            ->where('status', QuickPlayDeviceStatus::Failed)
            ->count();
        $inFlight = $this->devices->filter(fn (QuickPlayDevice $row) => ! $row->status->isTerminal())->count();

        $status = match (true) {
            $inFlight > 0 => QuickPlayStatus::Sending,
            $delivered === $targets && $targets > 0 => QuickPlayStatus::Completed,
            $delivered > 0 => QuickPlayStatus::Partial,
            $failed > 0 => QuickPlayStatus::Failed,
            default => QuickPlayStatus::Sending,
        };

        $this->forceFill([
            'targets_count' => $targets,
            'delivered_count' => $delivered,
            'failed_count' => $failed,
            'status' => $status,
        ])->save();

        return $this;
    }

    /**
     * @param  Builder<QuickPlay>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->whereHas('mediaAsset', fn (Builder $qq) => $qq->where('filename', 'like', "%{$term}%")));
    }
}
