<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Businesses\Models\Business;
use App\Domain\Locations\Models\Location;
use App\Domain\Playlists\Models\Playlist;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'location_id', 'playlist_id', 'name', 'daily_start_time',
    'daily_end_time', 'days_of_week', 'priority', 'status',
])]
class ContentSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'priority' => 'integer',
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
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }
}
