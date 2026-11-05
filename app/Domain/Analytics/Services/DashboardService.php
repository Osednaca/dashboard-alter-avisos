<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\CampaignDailyStat;
use App\Domain\Analytics\Models\CityDailyStat;
use App\Domain\Analytics\Models\DeviceDailyStat;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DevicePreviewService;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Operations\Models\Alert;
use App\Domain\Operations\Models\AuditLog;
use App\Domain\Playback\Models\PlaybackEvent;
use App\Http\Presenters\EntityPresenter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Aggregate everything the home dashboard needs without touching raw events
     * more than once, and cache the expensive slices briefly.
     *
     * @return array<string, mixed>
     */
    public function overview(CarbonInterface $from, CarbonInterface $to): array
    {
        $ttl = config('signage.analytics.cache_ttl');

        $deviceCounts = $this->deviceCounts();
        $series = $this->playbackSeries($from, $to);
        $previous = $this->playbackTotals($from, $to, previous: false);
        $current = $this->playbackTotals($from, $to, previous: true);

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'kpis' => [
                'activeScreens' => [
                    'value' => $deviceCounts['online'] ?? 0,
                    'total' => array_sum($deviceCounts),
                    'breakdown' => $deviceCounts,
                ],
                'businesses' => [
                    'value' => Business::query()->where('status', 'active')->count(),
                    'total' => Business::query()->count(),
                ],
                'campaigns' => [
                    'value' => Campaign::query()->where('status', CampaignStatus::Active->value)->count(),
                    'total' => Campaign::query()->count(),
                ],
                'playbacksToday' => [
                    'value' => $this->playbacksToday(),
                    'trend' => $this->trend($this->playbacksToday(), $this->playbacksYesterday()),
                ],
            ],
            'totals' => [
                'current' => $current,
                'previous' => $previous,
                'playbackTrend' => $this->trend($current['playbacks'], $previous['playbacks']),
            ],
            'series' => $series,
            'deviceHealth' => [
                'total' => array_sum($deviceCounts),
                'online' => $deviceCounts['online'] ?? 0,
                'offline' => $deviceCounts['offline'] ?? 0,
                'maintenance' => $deviceCounts['maintenance'] ?? 0,
                'disabled' => $deviceCounts['disabled'] ?? 0,
            ],
            'cities' => Cache::remember("dashboard.cities.{$to->toDateString()}", $ttl, fn () => $this->cities()),
            'cityCoverage' => CityDailyStat::query()->whereBetween('stat_date', [$from, $to])->distinct()->count('city'),
            'campaignPerformance' => $this->campaignPerformance($from, $to),
            'recentActivity' => $this->recentActivity(),
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function deviceCounts(): array
    {
        return Device::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->mapWithKeys(fn ($count, $status) => [$status => (int) $count])
            ->all();
    }

    protected function playbacksToday(): int
    {
        $today = now()->toDateString();
        $fromStats = (int) DeviceDailyStat::query()->where('stat_date', $today)->sum('playbacks_count');

        if ($fromStats > 0) {
            return $fromStats;
        }

        return PlaybackEvent::query()->whereDate('started_at', $today)->count();
    }

    protected function playbacksYesterday(): int
    {
        return (int) DeviceDailyStat::query()
            ->where('stat_date', now()->subDay()->toDateString())
            ->sum('playbacks_count');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function playbackSeries(CarbonInterface $from, CarbonInterface $to): array
    {
        return DeviceDailyStat::query()
            ->whereBetween('stat_date', [$from, $to])
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->selectRaw('stat_date, SUM(playbacks_count) as playbacks, SUM(completed_count) as completed, SUM(failures) as failures')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->stat_date)->toDateString(),
                'label' => Carbon::parse($row->stat_date)->format('d M'),
                'playbacks' => (int) $row->playbacks,
                'completed' => (int) $row->completed,
                'failures' => (int) $row->failures,
            ])
            ->all();
    }

    /**
     * @return array{playbacks: int, completed: int, failures: int, duration: int}
     */
    protected function playbackTotals(CarbonInterface $from, CarbonInterface $to, bool $previous): array
    {
        if ($previous) {
            $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
            $to = Carbon::parse($from)->subDay();
            $from = Carbon::parse($to)->subDays($days);
        }

        $row = DeviceDailyStat::query()
            ->whereBetween('stat_date', [$from, $to])
            ->selectRaw('SUM(playbacks_count) as playbacks, SUM(completed_count) as completed, SUM(failures) as failures, SUM(total_duration) as duration')
            ->first();

        return [
            'playbacks' => (int) ($row->playbacks ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'failures' => (int) ($row->failures ?? 0),
            'duration' => (int) ($row->duration ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function cities(): array
    {
        return Device::query()
            ->join('locations', 'locations.id', '=', 'devices.location_id')
            ->whereNotNull('devices.location_id')
            ->selectRaw("
                locations.city as city,
                COUNT(*) as screens,
                SUM(CASE WHEN devices.status = 'online' THEN 1 ELSE 0 END) as online
            ")
            ->groupBy('locations.city')
            ->orderByDesc('screens')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'city' => $row->city,
                'screens' => (int) $row->screens,
                'online' => (int) $row->online,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function campaignPerformance(CarbonInterface $from, CarbonInterface $to): array
    {
        $stats = CampaignDailyStat::query()
            ->whereBetween('stat_date', [$from, $to])
            ->groupBy('campaign_id')
            ->selectRaw('campaign_id, SUM(playbacks_count) as playbacks, SUM(completed_count) as completed, SUM(failures) as failures')
            ->orderByDesc('playbacks')
            ->limit(6)
            ->get();

        $campaigns = Campaign::query()
            ->with('advertiser')
            ->whereIn('id', $stats->pluck('campaign_id'))
            ->get()
            ->keyBy('id');

        return $stats
            ->map(function ($stat) use ($campaigns) {
                $campaign = $campaigns->get($stat->campaign_id);
                if (! $campaign) {
                    return null;
                }

                $campaign->playbacks_count = (int) $stat->playbacks;
                $campaign->completed_count = (int) $stat->completed;

                return EntityPresenter::campaign($campaign);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function screenPreview(?int $deviceId = null): ?array
    {
        $device = Device::query()
            ->when($deviceId !== null, fn ($query) => $query->whereKey($deviceId))
            ->with(['business', 'location', 'currentLayout', 'currentPlaylist'])
            ->orderBy('id')
            ->first();

        return $device ? app(DevicePreviewService::class)->forDevice($device) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentActivity(): array
    {
        $logs = AuditLog::query()->with('user')->latest('created_at')->limit(6)->get();
        $alerts = Alert::query()->where('status', 'open')->latest('triggered_at')->limit(3)->get();

        return [
            'logs' => $logs->map(fn ($log) => EntityPresenter::auditLog($log))->all(),
            'alerts' => $alerts->map(fn ($alert) => EntityPresenter::alert($alert))->all(),
        ];
    }

    protected function trend(int|float $current, int|float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function locationsForFilter(): array
    {
        return Location::query()->orderBy('city')->get(['id', 'name', 'city'])
            ->groupBy('city')
            ->map(fn ($group, $city) => ['city' => $city, 'count' => $group->count()])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mediaForPreview(): array
    {
        return MediaAsset::query()->ready()->latest()->limit(8)->get()
            ->map(fn ($asset) => EntityPresenter::mediaAsset($asset))
            ->all();
    }

    /**
     * Unused safeguard for raw aggregation fallbacks.
     */
    protected function rawPlaybackCount(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('playback_events')->whereBetween('started_at', [$from, $to])->count();
    }
}
