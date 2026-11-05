<?php

namespace App\Http\Controllers\Business;

use App\Domain\Playlists\Enums\PlaylistType;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    use AuthorizesBusiness;

    public function index(Request $request): Response
    {
        $business = $this->business();

        $playlists = $business->playlists()
            ->where('type', PlaylistType::Business->value)
            ->with(['items.mediaAsset'])
            ->orderBy('name')
            ->get();

        $mediaCounts = $this->businessMediaQuery()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $recentMedia = $this->businessMediaQuery()->latest()->limit(8)->get();

        $schedules = $business->schedules()
            ->with(['playlist', 'location', 'business'])
            ->orderBy('daily_start_time')
            ->get();

        return Inertia::render('Business/Content/Index', [
            'business' => EntityPresenter::businessSelf($business),
            'playlists' => $playlists->map(fn ($playlist) => EntityPresenter::playlistSummary($playlist))->values()->all(),
            'schedules' => $schedules->map(fn ($schedule) => EntityPresenter::contentSchedule($schedule))->values()->all(),
            'recentMedia' => $recentMedia->map(fn ($media) => EntityPresenter::mediaAsset($media))->values()->all(),
            'counts' => [
                'images' => (int) ($mediaCounts['image'] ?? 0),
                'videos' => (int) ($mediaCounts['video'] ?? 0),
                'playlists' => $playlists->count(),
                'scheduled' => $schedules->where('status', 'active')->count(),
            ],
        ]);
    }
}
