<?php

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\BusinessDailyStat;
use App\Domain\Analytics\Models\CampaignDailyStat;
use App\Domain\Analytics\Models\CityDailyStat;
use App\Domain\Analytics\Models\DeviceDailyStat;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AggregateDailyAnalytics
{
    /**
     * Recompute the daily aggregate tables for a given date.
     *
     * @return array<string, int>
     */
    public function handle(CarbonInterface $date): array
    {
        $day = $date->toDateString();

        return [
            'campaigns' => $this->aggregateCampaigns($day),
            'devices' => $this->aggregateDevices($day),
            'businesses' => $this->aggregateBusinesses($day),
            'cities' => $this->aggregateCities($day),
        ];
    }

    protected function aggregateCampaigns(string $day): int
    {
        $rows = DB::table('playback_events')
            ->join('devices', 'devices.id', '=', 'playback_events.device_id')
            ->whereNotNull('playback_events.campaign_id')
            ->whereDate('playback_events.started_at', $day)
            ->groupBy('playback_events.campaign_id')
            ->selectRaw('
                playback_events.campaign_id as campaign_id,
                COUNT(*) as playbacks_count,
                SUM(CASE WHEN playback_events.completed = 1 THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN playback_events.error_code IS NOT NULL THEN 1 ELSE 0 END) as failures,
                COUNT(DISTINCT playback_events.device_id) as unique_devices,
                COUNT(DISTINCT devices.business_id) as unique_businesses,
                SUM(playback_events.duration_played) as total_duration
            ')
            ->get();

        $payload = $rows->map(function ($row) use ($day) {
            $count = (int) $row->playbacks_count;

            return [
                'campaign_id' => $row->campaign_id,
                'stat_date' => $day,
                'playbacks_count' => $count,
                'completed_count' => (int) $row->completed_count,
                'failures' => (int) $row->failures,
                'unique_devices' => (int) $row->unique_devices,
                'unique_businesses' => (int) $row->unique_businesses,
                'total_duration' => (int) $row->total_duration,
                'completion_rate' => $count > 0 ? round(((int) $row->completed_count / $count) * 100, 2) : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        if ($payload) {
            CampaignDailyStat::query()->upsert(
                $payload,
                ['campaign_id', 'stat_date'],
                ['playbacks_count', 'completed_count', 'failures', 'unique_devices', 'unique_businesses', 'total_duration', 'completion_rate', 'updated_at'],
            );
        }

        return count($payload);
    }

    protected function aggregateDevices(string $day): int
    {
        $rows = DB::table('playback_events')
            ->whereDate('started_at', $day)
            ->groupBy('device_id')
            ->selectRaw('
                device_id,
                COUNT(*) as playbacks_count,
                SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN error_code IS NOT NULL THEN 1 ELSE 0 END) as failures,
                SUM(duration_played) as total_duration
            ')
            ->get();

        $heartbeats = DB::table('device_heartbeats')
            ->whereDate('recorded_at', $day)
            ->groupBy('device_id')
            ->selectRaw('device_id, COUNT(*) as heartbeats')
            ->pluck('heartbeats', 'device_id');

        $payload = $rows->map(fn ($row) => [
            'device_id' => $row->device_id,
            'stat_date' => $day,
            'playbacks_count' => (int) $row->playbacks_count,
            'completed_count' => (int) $row->completed_count,
            'failures' => (int) $row->failures,
            'total_duration' => (int) $row->total_duration,
            'uptime_seconds' => (int) ($heartbeats[$row->device_id] ?? 0) * 60,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($payload) {
            DeviceDailyStat::query()->upsert(
                $payload,
                ['device_id', 'stat_date'],
                ['playbacks_count', 'completed_count', 'failures', 'total_duration', 'uptime_seconds', 'updated_at'],
            );
        }

        return count($payload);
    }

    protected function aggregateBusinesses(string $day): int
    {
        $rows = DB::table('playback_events')
            ->join('devices', 'devices.id', '=', 'playback_events.device_id')
            ->whereDate('playback_events.started_at', $day)
            ->groupBy('devices.business_id')
            ->selectRaw('
                devices.business_id as business_id,
                COUNT(*) as playbacks_count,
                SUM(CASE WHEN playback_events.completed = 1 THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN playback_events.error_code IS NOT NULL THEN 1 ELSE 0 END) as failures,
                COUNT(DISTINCT playback_events.device_id) as unique_devices,
                SUM(playback_events.duration_played) as total_duration
            ')
            ->get();

        $payload = $rows->map(fn ($row) => [
            'business_id' => $row->business_id,
            'stat_date' => $day,
            'playbacks_count' => (int) $row->playbacks_count,
            'completed_count' => (int) $row->completed_count,
            'failures' => (int) $row->failures,
            'unique_devices' => (int) $row->unique_devices,
            'total_duration' => (int) $row->total_duration,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($payload) {
            BusinessDailyStat::query()->upsert(
                $payload,
                ['business_id', 'stat_date'],
                ['playbacks_count', 'completed_count', 'failures', 'unique_devices', 'total_duration', 'updated_at'],
            );
        }

        return count($payload);
    }

    protected function aggregateCities(string $day): int
    {
        $rows = DB::table('playback_events')
            ->join('devices', 'devices.id', '=', 'playback_events.device_id')
            ->join('locations', 'locations.id', '=', 'devices.location_id')
            ->whereDate('playback_events.started_at', $day)
            ->groupBy('locations.city')
            ->selectRaw('
                locations.city as city,
                COUNT(*) as playbacks_count,
                COUNT(DISTINCT playback_events.device_id) as unique_devices,
                COUNT(DISTINCT devices.business_id) as unique_businesses,
                SUM(playback_events.duration_played) as total_duration
            ')
            ->get();

        $payload = $rows->map(fn ($row) => [
            'city' => $row->city,
            'stat_date' => $day,
            'playbacks_count' => (int) $row->playbacks_count,
            'unique_devices' => (int) $row->unique_devices,
            'unique_businesses' => (int) $row->unique_businesses,
            'total_duration' => (int) $row->total_duration,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($payload) {
            CityDailyStat::query()->upsert(
                $payload,
                ['city', 'stat_date'],
                ['playbacks_count', 'unique_devices', 'unique_businesses', 'total_duration', 'updated_at'],
            );
        }

        return count($payload);
    }
}
