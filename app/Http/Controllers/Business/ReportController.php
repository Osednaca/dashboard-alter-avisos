<?php

namespace App\Http\Controllers\Business;

use App\Domain\Devices\Models\Device;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use AuthorizesBusiness;

    public function index(Request $request): Response
    {
        $business = $this->business();

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'device_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'playlist_id' => ['nullable', 'integer'],
        ]);

        $from = $request->date('from') ?? today()->subDays(29);
        $to = $request->date('to') ?? today();

        $devices = $business->devices()->with('location')->orderBy('name')->get();
        $deviceIds = $devices->pluck('id');

        if ($request->filled('location_id')) {
            $deviceIds = $devices->where('location_id', $request->integer('location_id'))->pluck('id');
        }
        if ($request->filled('device_id')) {
            $deviceIds = collect([$request->integer('device_id')])->intersect($deviceIds);
        }

        $mediaQuery = $this->businessMediaQuery();
        if ($request->filled('playlist_id')) {
            $playlist = $business->playlists()->where('type', 'business')->findOrFail($request->integer('playlist_id'));
            $mediaQuery->whereIn('id', $playlist->items()->pluck('media_asset_id'));
        }
        $mediaIds = $mediaQuery->pluck('id');

        $rangeStart = Carbon::parse($from)->startOfDay();
        $rangeEnd = Carbon::parse($to)->endOfDay();

        $events = fn () => DB::table('playback_events')
            ->whereIn('media_asset_id', $mediaIds)
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->when($deviceIds->isNotEmpty(), fn ($query) => $query->whereIn('device_id', $deviceIds));

        $totalsRow = $mediaIds->isEmpty() ? null : $events()
            ->selectRaw('
                COUNT(*) as playbacks,
                SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN error_code IS NOT NULL THEN 1 ELSE 0 END) as failures,
                SUM(duration_played) as duration
            ')
            ->first();

        $playbacks = (int) ($totalsRow->playbacks ?? 0);
        $completed = (int) ($totalsRow->completed ?? 0);

        $series = $mediaIds->isEmpty() ? collect() : $events()
            ->groupBy(DB::raw('DATE(started_at)'))
            ->orderBy('day')
            ->selectRaw('DATE(started_at) as day, COUNT(*) as playbacks, SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->day)->toDateString(),
                'label' => Carbon::parse($row->day)->format('d M'),
                'playbacks' => (int) $row->playbacks,
                'completed' => (int) $row->completed,
            ]);

        $topMedia = $mediaIds->isEmpty() ? collect() : $events()
            ->join('media_assets', 'media_assets.id', '=', 'playback_events.media_asset_id')
            ->groupBy('playback_events.media_asset_id', 'media_assets.filename', 'media_assets.type')
            ->orderByDesc('playbacks')
            ->limit(8)
            ->selectRaw('media_assets.filename, media_assets.type, COUNT(*) as playbacks, SUM(playback_events.duration_played) as duration')
            ->get()
            ->map(fn ($row) => [
                'filename' => $row->filename,
                'type' => $row->type,
                'playbacks' => (int) $row->playbacks,
                'duration' => (int) $row->duration,
            ]);

        $perScreen = $mediaIds->isEmpty() ? collect() : $events()
            ->join('devices', 'devices.id', '=', 'playback_events.device_id')
            ->groupBy('devices.id', 'devices.name')
            ->orderByDesc('playbacks')
            ->selectRaw('devices.name, COUNT(*) as playbacks, SUM(CASE WHEN playback_events.completed = 1 THEN 1 ELSE 0 END) as completed')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'playbacks' => (int) $row->playbacks,
                'completed' => (int) $row->completed,
            ]);

        $uptime = DB::table('device_daily_stats')
            ->join('devices', 'devices.id', '=', 'device_daily_stats.device_id')
            ->whereIn('device_daily_stats.device_id', $deviceIds)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('devices.id', 'devices.name')
            ->selectRaw('devices.name, SUM(uptime_seconds) as uptime_seconds, SUM(playbacks_count) as playbacks')
            ->orderByDesc('uptime_seconds')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'uptime_hours' => round(((int) $row->uptime_seconds) / 3600, 1),
                'playbacks' => (int) $row->playbacks,
            ]);

        return Inertia::render('Business/Reports/Index', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filters' => $request->only('from', 'to', 'device_id', 'location_id', 'playlist_id'),
            'metrics' => [
                'playbacks' => $playbacks,
                'completed' => $completed,
                'completionRate' => $playbacks > 0 ? round(($completed / $playbacks) * 100, 1) : 0,
                'failures' => (int) ($totalsRow->failures ?? 0),
                'durationHours' => round(((int) ($totalsRow->duration ?? 0)) / 3600, 1),
                'activeScreens' => $devices->filter(fn (Device $device) => $device->isOnline())->count(),
                'totalScreens' => $devices->count(),
            ],
            'series' => $series->values()->all(),
            'topMedia' => $topMedia->values()->all(),
            'perScreen' => $perScreen->values()->all(),
            'uptime' => $uptime->values()->all(),
            'syncHealth' => $devices->map(fn (Device $device) => [
                'name' => $device->name,
                'location' => $device->location?->name,
                'status' => EntityPresenter::enum($device->status),
                'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            ])->values()->all(),
            'options' => [
                'devices' => $devices->map(fn (Device $device) => ['id' => $device->id, 'name' => $device->name])->values()->all(),
                'locations' => $business->locations()->orderBy('name')->get(['id', 'name'])->map(fn ($location) => ['id' => $location->id, 'name' => $location->name])->values()->all(),
                'playlists' => $business->playlists()->where('type', 'business')->orderBy('name')->get(['id', 'name'])->map(fn ($playlist) => ['id' => $playlist->id, 'name' => $playlist->name])->values()->all(),
            ],
        ]);
    }
}
