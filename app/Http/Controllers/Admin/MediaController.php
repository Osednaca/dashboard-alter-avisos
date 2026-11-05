<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Media\Actions\StoreMediaAsset;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\ProcessingStatus;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\MediaUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MediaAsset::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:image,video,live_stream'],
            'processing_status' => ['nullable', 'string'],
        ]);

        $assets = MediaAsset::query()->advertising()
            ->search($request->string('search')->toString())
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('processing_status'), fn ($q) => $q->where('processing_status', $request->string('processing_status')))
            ->withCount('campaignCreatives as usage_count')
            ->latest()
            ->paginate(24)
            ->withQueryString()
            ->through(fn (MediaAsset $asset) => EntityPresenter::mediaAsset($asset));

        return Inertia::render('Admin/Creatives/Index', [
            'assets' => $assets,
            'filters' => $request->only('search', 'type', 'processing_status'),
            'options' => [
                'types' => collect(MediaType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all(),
                'statuses' => collect(ProcessingStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
                'advertisers' => Advertiser::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(MediaUploadRequest $request, StoreMediaAsset $store): RedirectResponse|JsonResponse
    {
        $this->authorize('create', MediaAsset::class);

        $file = $request->file('file');
        $type = str_starts_with((string) $file->getMimeType(), 'image/') ? MediaType::Image : MediaType::Video;

        $owner = null;
        if ($request->filled('advertiser_id')) {
            $owner = Advertiser::query()->find($request->integer('advertiser_id'));

        }

        $asset = $store->handle($file, $type, $owner);

        app(RecordAudit::class)->handle('media.uploaded', $asset, [], ['filename' => $asset->filename]);

        if ($request->expectsJson()) {
            return response()->json(['media' => EntityPresenter::mediaAsset($asset)], 201);
        }

        return back()->with('success', 'Archivo subido. Se está procesando.');
    }

    public function destroy(MediaAsset $media): RedirectResponse
    {
        $this->authorize('delete', $media);
        abort_unless(MediaAsset::query()->advertising()->whereKey($media->id)->exists(), 404);

        if ($media->isLockedByActiveCampaign()) {
            return back()->with('error', 'No se puede eliminar: la creatividad está en una campaña activa.');
        }

        $disk = config('signage.media_disk');
        Storage::disk($disk)->delete(array_filter([$media->storage_path, $media->thumbnail_path]));

        app(RecordAudit::class)->handle('media.deleted', $media, ['filename' => $media->filename]);

        $media->delete();

        return back()->with('success', 'Creatividad eliminada.');
    }
}
