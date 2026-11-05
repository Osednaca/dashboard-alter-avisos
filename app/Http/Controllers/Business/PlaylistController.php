<?php

namespace App\Http\Controllers\Business;

use App\Domain\Playlists\Enums\PlaylistStatus;
use App\Domain\Playlists\Enums\PlaylistType;
use App\Domain\Playlists\Models\Playlist;
use App\Domain\Scheduling\Actions\InvalidateBusinessDevices;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\PlaylistRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaylistController extends Controller
{
    use AuthorizesBusiness;

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('business.schedule.index');
    }

    public function store(PlaylistRequest $request): RedirectResponse
    {
        $playlist = $this->business()->playlists()->create([
            'name' => $request->string('name')->toString(),
            'type' => PlaylistType::Business,
            'status' => PlaylistStatus::Draft,
        ]);

        return redirect()
            ->route('business.playlists.show', $playlist)
            ->with('success', 'Lista de reproducción creada.');
    }

    public function show(Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwned($playlist);
        abort_unless($playlist->type === PlaylistType::Business, 404);
        $scheduleId = $playlist->is_schedule_managed ? $playlist->schedules()->value('id') : null;

        return redirect()->route('business.schedule.index', $scheduleId
            ? ['edit' => $scheduleId]
            : ($playlist->is_schedule_managed ? [] : ['import' => $playlist->id]));
    }

    public function update(PlaylistRequest $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwned($playlist);
        abort_unless(! $playlist->is_schedule_managed && $playlist->type === PlaylistType::Business, 404);

        DB::transaction(function () use ($request, $playlist) {
            $playlist->update([
                'name' => $request->string('name')->toString(),
                'status' => $request->filled('status') ? $request->string('status')->toString() : $playlist->status,
            ]);
            app(InvalidateBusinessDevices::class)->handle($this->businessId());
        });

        return back()->with('success', 'Lista actualizada.');
    }

    public function duplicate(Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwned($playlist);
        abort_unless(! $playlist->is_schedule_managed && $playlist->type === PlaylistType::Business, 404);

        $copy = DB::transaction(function () use ($playlist) {
            $copy = $this->business()->playlists()->create([
                'name' => $playlist->name.' (copia)',
                'type' => PlaylistType::Business,
                'status' => PlaylistStatus::Draft,
            ]);

            foreach ($playlist->items as $item) {
                $copy->items()->create([
                    'media_asset_id' => $item->media_asset_id,
                    'sort_order' => $item->sort_order,
                    'duration' => $item->duration,
                    'transition' => $item->transition,
                    'configuration' => $item->configuration,
                ]);
            }

            return $copy;
        });

        return redirect()
            ->route('business.playlists.show', $copy)
            ->with('success', 'Lista duplicada.');
    }

    public function destroy(Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwned($playlist);
        abort_unless(! $playlist->is_schedule_managed && $playlist->type === PlaylistType::Business, 404);

        DB::transaction(function () use ($playlist) {
            $playlist->items()->delete();
            $playlist->schedules()->delete();
            $playlist->delete();
            app(InvalidateBusinessDevices::class)->handle($this->businessId());
        });

        return redirect()
            ->route('business.playlists.index')
            ->with('success', 'Lista eliminada.');
    }
}
