<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Operations\Actions\RaiseAlert;
use App\Domain\Operations\Enums\AlertSeverity;
use App\Domain\Operations\Enums\AlertType;
use App\Domain\Playback\Models\PlaybackEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IngestPlaybackBatch
{
    public function __construct(protected RaiseAlert $alerts) {}

    /**
     * Ingest a batch of proof-of-play events from a device.
     *
     * Events are inserted in a single statement and never trigger analytics
     * work inline; aggregation happens on a schedule.
     *
     * @param  array<int, array<string, mixed>>  $events
     */
    public function handle(Device $device, array $events): int
    {
        if ($events === []) {
            return 0;
        }

        $now = now();

        $rows = array_map(fn (array $event) => [
            'device_id' => $device->id,
            'campaign_id' => $event['campaign_id'] ?? null,
            'creative_id' => $event['creative_id'] ?? null,
            'playlist_id' => $event['playlist_id'] ?? null,
            'media_asset_id' => $event['media_asset_id'],
            'started_at' => $this->toCarbon($event['started_at']),
            'completed_at' => isset($event['completed_at']) ? $this->toCarbon($event['completed_at']) : null,
            'duration_played' => (int) ($event['duration_played'] ?? 0),
            'completed' => (bool) ($event['completed'] ?? false),
            'error_code' => $event['error_code'] ?? null,
            'manifest_version' => isset($event['manifest_version']) ? (int) $event['manifest_version'] : null,
            'metadata' => isset($event['metadata']) ? json_encode($event['metadata']) : null,
            'created_at' => $now,
        ], $events);

        DB::transaction(function () use ($rows, $device) {
            PlaybackEvent::query()->insert($rows);

            $device->forceFill([
                'last_seen_at' => now(),
                'status' => DeviceStatus::Online,
            ])->save();
        });

        $this->guardAgainstRepeatedFailures($device);

        return count($rows);
    }

    protected function guardAgainstRepeatedFailures(Device $device): void
    {
        $failures = PlaybackEvent::query()
            ->where('device_id', $device->id)
            ->whereNotNull('error_code')
            ->where('started_at', '>=', now()->subHour())
            ->count();

        if ($failures >= 5) {
            $this->alerts->handle(
                AlertType::RepeatedPlaybackErrors,
                'Errores de reproducción repetidos',
                "La pantalla «{$device->name}» reportó {$failures} errores en la última hora.",
                AlertSeverity::Warning,
                $device,
                ['failures_last_hour' => $failures],
            );
        }
    }

    protected function toCarbon(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
