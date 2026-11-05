<?php

namespace App\Http\Controllers\Business;

use App\Domain\Businesses\Services\BusinessPreviewService;
use App\Domain\Playlists\Enums\PlaylistType;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use AuthorizesBusiness;

    public function __construct(protected BusinessPreviewService $preview) {}

    public function index(Request $request): Response
    {
        $business = $this->business();

        $devices = $business->devices()
            ->with(['business:id,name', 'location', 'currentLayout', 'currentPlaylist.items.mediaAsset'])
            ->orderBy('name')
            ->get();

        $mediaQuery = fn () => $this->businessMediaQuery();

        $playlists = $business->playlists()
            ->where('type', PlaylistType::Business->value)
            ->with(['items.mediaAsset'])
            ->orderBy('name')
            ->get();

        $schedules = $business->schedules()
            ->with(['playlist', 'location', 'business'])
            ->orderBy('daily_start_time')
            ->get();

        $recentMedia = $mediaQuery()->latest()->limit(6)->get();

        $selected = $request->filled('device')
            ? $devices->firstWhere('id', $request->integer('device'))
            : null;

        $selected ??= $devices->first();

        return Inertia::render('Business/Home', [
            'business' => EntityPresenter::businessSelf($business),
            'kpis' => [
                'screens' => [
                    'online' => $devices->filter(fn ($device) => $device->isOnline())->count(),
                    'total' => $devices->count(),
                ],
                'media' => [
                    'total' => $mediaQuery()->count(),
                    'images' => $mediaQuery()->where('type', 'image')->count(),
                    'videos' => $mediaQuery()->where('type', 'video')->count(),
                ],
                'schedules' => [
                    'active' => $business->schedules()->where('status', 'active')->count(),
                    'total' => $schedules->count(),
                ],
                'last_sync_at' => $devices->max('last_sync_at'),
            ],
            'playlists' => $playlists->map(fn ($playlist) => EntityPresenter::playlistSummary($playlist))->values()->all(),
            'schedules' => $schedules->map(fn ($schedule) => EntityPresenter::contentSchedule($schedule))->values()->all(),
            'recentMedia' => $recentMedia->map(fn ($media) => EntityPresenter::mediaAsset($media))->values()->all(),
            'devices' => $devices->map(fn ($device) => EntityPresenter::device($device))->values()->all(),
            'preview' => fn () => $selected ? $this->preview->forDevice($selected) : null,
            'selectedDeviceId' => $selected?->id,
            'audioVolume' => (int) data_get($business->metadata, 'audio_volume', 70),
        ]);
    }
}
