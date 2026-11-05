<?php

namespace App\Http\Controllers\Business;

use App\Domain\Businesses\Services\BusinessPreviewService;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScreenController extends Controller
{
    use AuthorizesBusiness;

    public function __construct(protected BusinessPreviewService $preview) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
            'location_id' => ['nullable', 'integer'],
        ]);

        $devices = $this->business()->devices()
            ->search($request->string('search')->toString())
            ->withStatus($request->string('status')->toString() ?: null)
            ->when($request->filled('location_id'), fn ($query) => $query->where('location_id', $request->integer('location_id')))
            ->with(['location', 'currentLayout', 'currentPlaylist'])
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => EntityPresenter::device($device));

        return Inertia::render('Business/Screens/Index', [
            'devices' => $devices->values()->all(),
            'filters' => $request->only('search', 'status', 'location_id'),
            'locations' => $this->business()->locations()
                ->orderBy('name')
                ->get(['id', 'name', 'city'])
                ->map(fn ($location) => ['id' => $location->id, 'name' => $location->name, 'city' => $location->city])
                ->values()
                ->all(),
            'summary' => [
                'total' => $devices->count(),
                'online' => $devices->where('is_online', true)->count(),
            ],
        ]);
    }

    public function show(Device $device): Response
    {
        $this->authorizeOwned($device);

        $device->load(['location', 'currentLayout', 'currentPlaylist.items.mediaAsset']);

        return Inertia::render('Business/Screens/Show', [
            'device' => EntityPresenter::device($device),
            'preview' => fn () => $this->preview->forDevice($device),
        ]);
    }

    public function sync(Device $device, BuildDeviceManifest $builder): RedirectResponse
    {
        $this->authorizeOwned($device);

        $manifest = $builder->handle($device);

        app(RecordAudit::class)->handle('business.device.sync.queued', $device, [], [
            'version' => $manifest->version,
            'business_id' => $this->businessId(),
        ]);

        return back()->with('success', "Contenido sincronizado para {$device->name}.");
    }
}
