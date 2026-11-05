<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Analytics\Models\CampaignDailyStat;
use App\Domain\Analytics\Models\CityDailyStat;
use App\Domain\Analytics\Models\DeviceDailyStat;
use App\Domain\Businesses\Enums\BusinessCategory;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('analytics.view'), 403);

        [$from, $to, $filters] = $this->filters($request);

        return Inertia::render('Admin/Analytics/Index', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filters' => $filters,
            'metrics' => $this->metrics($from, $to, $filters),
            'series' => $this->series($from, $to, $filters),
            'breakdowns' => $this->breakdowns($from, $to),
            'campaigns' => $this->campaignTable($from, $to, $filters),
            'options' => [
                'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
                'advertisers' => Advertiser::query()->orderBy('name')->get(['id', 'name']),
                'businesses' => Business::query()->orderBy('name')->get(['id', 'name']),
                'cities' => Location::query()->distinct()->orderBy('city')->pluck('city'),
                'devices' => Device::query()->orderBy('name')->get(['id', 'name']),
                'categories' => BusinessCategory::options(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('analytics.view'), 403);

        [$from, $to, $filters] = $this->filters($request);
        $rows = $this->campaignTable($from, $to, $filters, paginate: false);

        $filename = "analitica-{$from->toDateString()}-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Campaña', 'Anunciante', 'Estado', 'Reproducciones', 'Completadas', 'Fallos', 'Tasa de finalización']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['advertiser'],
                    $row['status'],
                    $row['playbacks'],
                    $row['completed'],
                    $row['failures'],
                    $row['completion_rate'].'%',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: array<string, mixed>}
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'campaign_id' => ['nullable', 'integer'],
            'advertiser_id' => ['nullable', 'integer'],
            'business_id' => ['nullable', 'integer'],
            'city' => ['nullable', 'string'],
            'device_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string'],
        ]);

        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : today();
        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : today()->subDays(29);

        return [$from, $to, $validated];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function metrics(Carbon $from, Carbon $to, array $filters): array
    {
        $base = fn () => DeviceDailyStat::query()
            ->join('devices', 'devices.id', '=', 'device_daily_stats.device_id')
            ->whereBetween('stat_date', [$from, $to])
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('devices.id', $v))
            ->when($filters['business_id'] ?? null, fn ($q, $v) => $q->where('devices.business_id', $v))
            ->when($filters['city'] ?? null, fn ($q, $v) => $q->where('devices.location_id', Location::query()->where('city', $v)->select('id')))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('devices.business_id', Business::query()->where('category', $v)->select('id')));

        $row = $base()->selectRaw('
            SUM(playbacks_count) as playbacks,
            SUM(completed_count) as completed,
            SUM(failures) as failures,
            SUM(total_duration) as duration,
            COUNT(DISTINCT device_daily_stats.device_id) as screens,
            SUM(uptime_seconds) as uptime
        ')->first();

        $playbacks = (int) ($row->playbacks ?? 0);
        $completed = (int) ($row->completed ?? 0);

        $businesses = $base()->distinct()->count('devices.business_id');

        return [
            'playbacks' => $playbacks,
            'completed' => $completed,
            'completionRate' => $playbacks > 0 ? round(($completed / $playbacks) * 100, 1) : 0,
            'failures' => (int) ($row->failures ?? 0),
            'duration' => (int) ($row->duration ?? 0),
            'screens' => (int) ($row->screens ?? 0),
            'businesses' => $businesses,
            'uptimeHours' => round(((int) ($row->uptime ?? 0)) / 3600, 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    protected function series(Carbon $from, Carbon $to, array $filters): array
    {
        $query = DeviceDailyStat::query()
            ->join('devices', 'devices.id', '=', 'device_daily_stats.device_id')
            ->whereBetween('stat_date', [$from, $to])
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('devices.id', $v))
            ->when($filters['business_id'] ?? null, fn ($q, $v) => $q->where('devices.business_id', $v))
            ->when($filters['city'] ?? null, fn ($q, $v) => $q->where('devices.location_id', Location::query()->where('city', $v)->select('id')));

        return $query
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
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function breakdowns(Carbon $from, Carbon $to): array
    {
        return [
            'cities' => CityDailyStat::query()
                ->whereBetween('stat_date', [$from, $to])
                ->groupBy('city')
                ->selectRaw('city, SUM(playbacks_count) as playbacks, SUM(unique_devices) as devices')
                ->orderByDesc('playbacks')
                ->limit(10)
                ->get()
                ->map(fn ($row) => ['label' => $row->city, 'playbacks' => (int) $row->playbacks, 'devices' => (int) $row->devices])
                ->all(),
            'categories' => DB::table('business_daily_stats')
                ->join('businesses', 'businesses.id', '=', 'business_daily_stats.business_id')
                ->whereBetween('stat_date', [$from, $to])
                ->groupBy('businesses.category')
                ->selectRaw('businesses.category as category, SUM(playbacks_count) as playbacks')
                ->orderByDesc('playbacks')
                ->get()
                ->map(fn ($row) => [
                    'label' => BusinessCategory::tryFrom($row->category)?->label() ?? $row->category,
                    'playbacks' => (int) $row->playbacks,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function campaignTable(Carbon $from, Carbon $to, array $filters, bool $paginate = true)
    {
        $stats = CampaignDailyStat::query()
            ->whereBetween('stat_date', [$from, $to])
            ->when($filters['campaign_id'] ?? null, fn ($q, $v) => $q->where('campaign_id', $v))
            ->groupBy('campaign_id')
            ->selectRaw('campaign_id, SUM(playbacks_count) as playbacks, SUM(completed_count) as completed, SUM(failures) as failures')
            ->orderByDesc('playbacks')
            ->get();

        $campaigns = Campaign::query()
            ->with('advertiser')
            ->when($filters['advertiser_id'] ?? null, fn ($q, $v) => $q->where('advertiser_id', $v))
            ->whereIn('id', $stats->pluck('campaign_id'))
            ->get()
            ->keyBy('id');

        $rows = $stats
            ->map(function ($stat) use ($campaigns) {
                $campaign = $campaigns->get($stat->campaign_id);
                if (! $campaign) {
                    return null;
                }

                $playbacks = (int) $stat->playbacks;

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'advertiser' => $campaign->advertiser?->name ?? '—',
                    'status' => $campaign->status->label(),
                    'playbacks' => $playbacks,
                    'completed' => (int) $stat->completed,
                    'failures' => (int) $stat->failures,
                    'completion_rate' => $playbacks > 0 ? round(((int) $stat->completed / $playbacks) * 100, 1) : 0,
                ];
            })
            ->filter()
            ->values();

        if (! $paginate) {
            return $rows->all();
        }

        $currentPage = (int) request('page', 1);
        $perPage = 15;

        return new LengthAwarePaginator(
            $rows->forPage($currentPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}
