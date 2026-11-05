<?php

namespace App\Http\Controllers\Business;

use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playlists\Models\Playlist;
use App\Domain\Playlists\Models\PlaylistItem;
use App\Domain\Scheduling\Actions\InvalidateBusinessDevices;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\ReorderPlaylistRequest;
use App\Http\Requests\Business\StorePlaylistItemRequest;
use App\Http\Requests\Business\UpdatePlaylistItemRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PlaylistItemController extends Controller
{
    use AuthorizesBusiness;

    public function store(StorePlaylistItemRequest $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwned($playlist);
        abort_unless(! $playlist->is_schedule_managed && $playlist->type->value === 'business', 404);

        $media = MediaAsset::query()->findOrFail($request->integer('media_asset_id'));
        $this->authorizeOwnedMedia($media);

        DB::transaction(function () use ($playlist, $media, $request) {
            $playlist->items()->create([
                'media_asset_id' => $media->id,
                'duration' => $request->integer('duration'),
                'transition' => $request->string('transition')->toString(),
                'sort_order' => (int) $playlist->items()->max('sort_order') + 1,
            ]);
            $this->refreshDevices();
        });

        return back()->with('success', 'Contenido agregado a la lista.');
    }

    public function update(UpdatePlaylistItemRequest $request, Playlist $playlist, PlaylistItem $item): RedirectResponse
    {
        $this->authorizeOwned($playlist);
        abort_unless(! $playlist->is_schedule_managed && $playlist->type->value === 'business', 404);
        $this->authorizeItem($playlist, $item);

        DB::transaction(function () use ($item, $request) {
            $item->update([
                'duration' => $request->integer('duration'),
                'transition' => $request->string('transition')->toString(),
            ]);
            $this->refreshDevices();
        });

        return back()->with('success', 'Elemento actualizado.');
    }

    public function destroy(Playlist $playlist, PlaylistItem $item): RedirectResponse
    {
        $this->authorizeOwned($playlist);
        abort_unless(! $playlist->is_schedule_managed && $playlist->type->value === 'business', 404);
        $this->authorizeItem($playlist, $item);

        DB::transaction(function () use ($item) {
            $item->delete();
            $this->refreshDevices();
        });

        return back()->with('success', 'Elemento eliminado.');
    }

    public function reorder(ReorderPlaylistRequest $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwned($playlist);
        abort_unless(! $playlist->is_schedule_managed && $playlist->type->value === 'business', 404);

        $order = collect($request->input('order', []))->map(fn ($id) => (int) $id);
        $validIds = $playlist->items()->pluck('id');

        abort_unless($order->diff($validIds)->isEmpty() && $validIds->diff($order)->isEmpty(), 422);

        DB::transaction(function () use ($order) {
            foreach ($order->values() as $index => $itemId) {
                PlaylistItem::query()->whereKey($itemId)->update(['sort_order' => $index]);
            }
            $this->refreshDevices();
        });

        return back()->with('success', 'Orden actualizado.');
    }

    protected function refreshDevices(): void
    {
        app(InvalidateBusinessDevices::class)->handle($this->businessId());
    }

    protected function authorizeItem(Playlist $playlist, PlaylistItem $item): void
    {
        abort_unless((int) $item->playlist_id === (int) $playlist->id, 404);
    }
}
