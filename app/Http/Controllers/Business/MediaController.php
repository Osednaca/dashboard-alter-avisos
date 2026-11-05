<?php

namespace App\Http\Controllers\Business;

use App\Domain\Media\Actions\StoreMediaAsset;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Operations\Actions\RecordAudit;
use App\Domain\Playlists\Models\PlaylistItem;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Business\BusinessMediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    use AuthorizesBusiness;

    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:image,video'],
        ]);

        $media = $this->businessMediaQuery()
            ->search($request->string('search')->toString())
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->latest()
            ->paginate(24)
            ->withQueryString()
            ->through(fn (MediaAsset $asset) => EntityPresenter::mediaAsset($asset));

        $counts = $this->businessMediaQuery()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return Inertia::render('Business/Library/Index', [
            'media' => $media,
            'filters' => $request->only('search', 'type'),
            'counts' => [
                'total' => array_sum($counts->all()),
                'images' => (int) ($counts['image'] ?? 0),
                'videos' => (int) ($counts['video'] ?? 0),
            ],
            'totalSize' => (int) $this->businessMediaQuery()->sum('filesize'),
        ]);
    }

    public function store(BusinessMediaRequest $request, StoreMediaAsset $store): RedirectResponse|JsonResponse
    {
        $file = $request->file('file');
        $type = str_starts_with((string) $file->getMimeType(), 'image/') ? MediaType::Image : MediaType::Video;

        $asset = $store->handle($file, $type, $this->business());

        app(RecordAudit::class)->handle('business.media.uploaded', $asset, [], [
            'filename' => $asset->filename,
            'business_id' => $this->businessId(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['media' => EntityPresenter::mediaAsset($asset)], 201);
        }

        return back()->with('success', 'Contenido subido. Se está procesando.');
    }

    public function update(Request $request, MediaAsset $media): RedirectResponse
    {
        $this->authorizeOwnedMedia($media);

        $data = $request->validate([
            'filename' => ['required', 'string', 'max:160'],
        ]);

        $media->forceFill(['filename' => $data['filename']])->save();

        return back()->with('success', 'Contenido renombrado.');
    }

    public function destroy(MediaAsset $media): RedirectResponse
    {
        $this->authorizeOwnedMedia($media);

        $inUse = PlaylistItem::query()
            ->where('media_asset_id', $media->id)
            ->whereHas('playlist', fn ($query) => $query->where('business_id', $this->businessId()))
            ->exists();

        if ($inUse) {
            return back()->with('error', 'No se puede eliminar: el contenido está en una lista de reproducción.');
        }

        Storage::disk(config('signage.media_disk'))->delete(array_filter([
            $media->storage_path,
            $media->thumbnail_path,
        ]));

        app(RecordAudit::class)->handle('business.media.deleted', $media, [
            'filename' => $media->filename,
        ]);

        $media->delete();

        return back()->with('success', 'Contenido eliminado.');
    }
}
