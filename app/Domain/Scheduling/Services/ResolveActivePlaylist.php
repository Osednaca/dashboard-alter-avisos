<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Devices\Models\Device;
use App\Domain\Playlists\Enums\PlaylistStatus;
use App\Domain\Playlists\Enums\PlaylistType;
use App\Domain\Playlists\Models\Playlist;
use App\Domain\Scheduling\Models\ContentSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolves which business playlist should be playing on a device right now,
 * based on the business schedules (time window, weekdays and location).
 */
class ResolveActivePlaylist
{
    public function forDevice(Device $device, ?CarbonInterface $at = null): ?Playlist
    {
        $timezone = $this->timezoneFor($device);
        $now = $at ? $at->copy()->setTimezone($timezone) : now($timezone);

        $schedule = $this->activeSchedules($device)
            ->first(fn (ContentSchedule $schedule) => $this->matches($schedule, $now));

        if ($schedule?->playlist) {
            return $schedule->playlist;
        }

        // Fall back to the device's current playlist when it is a valid business
        // playlist, otherwise the first active business playlist.
        $current = $device->currentPlaylist;

        if ($current
            && ! $current->is_schedule_managed
            && $current->type === PlaylistType::Business
            && $current->status === PlaylistStatus::Active) {
            return $current->loadMissing('items.mediaAsset');
        }

        return Playlist::query()
            ->where('business_id', $device->business_id)
            ->where('type', PlaylistType::Business->value)
            ->where('status', PlaylistStatus::Active->value)
            ->where('is_schedule_managed', false)
            ->with(['items.mediaAsset'])
            ->orderBy('id')
            ->first();
    }

    /**
     * All active schedules that can apply to the device, ordered by priority.
     *
     * @return Collection<int, ContentSchedule>
     */
    public function activeSchedules(Device $device): Collection
    {
        return ContentSchedule::query()
            ->where('business_id', $device->business_id)
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->whereNull('location_id')
                ->orWhere('location_id', $device->location_id))
            ->with(['playlist.items.mediaAsset'])
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<int, Playlist>
     */
    public function scheduledPlaylists(Device $device): array
    {
        return $this->activeSchedules($device)
            ->pluck('playlist')
            ->filter(fn ($playlist) => $playlist instanceof Playlist)
            ->unique('id')
            ->values()
            ->all();
    }

    public function timezoneFor(Device $device): string
    {
        return $device->business?->timezone
            ?? $device->location?->timezone
            ?? config('app.timezone');
    }

    protected function matches(ContentSchedule $schedule, CarbonInterface $now): bool
    {
        $days = $schedule->days_of_week ?? [];

        if ($days !== [] && ! in_array((int) $now->dayOfWeekIso, $days, true)) {
            return false;
        }

        $start = $schedule->daily_start_time;
        $end = $schedule->daily_end_time;

        if (! $start || ! $end) {
            return true;
        }

        $start = $this->normalize($start);
        $end = $this->normalize($end);
        $time = $now->format('H:i:s');

        // Overnight windows (e.g. 20:00 - 02:00) wrap around midnight.
        if ($start <= $end) {
            return $time >= $start && $time <= $end;
        }

        return $time >= $start || $time <= $end;
    }

    protected function normalize(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
