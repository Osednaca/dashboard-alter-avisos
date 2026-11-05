<?php

namespace App\Http\Controllers\Business;

use App\Domain\Businesses\Services\BusinessPreviewService;
use App\Domain\Devices\Models\Device;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreviewController extends Controller
{
    use AuthorizesBusiness;

    public function __construct(protected BusinessPreviewService $preview) {}

    public function index(Request $request): Response
    {
        $devices = $this->business()->devices()
            ->with(['business:id,name', 'location', 'currentLayout', 'currentPlaylist.items.mediaAsset'])
            ->orderBy('name')
            ->get();

        $selected = $request->filled('device')
            ? $devices->firstWhere('id', $request->integer('device'))
            : null;

        $selected ??= $devices->first();

        return Inertia::render('Business/Preview/Index', [
            'devices' => $devices->map(fn (Device $device) => EntityPresenter::device($device))->values()->all(),
            'preview' => fn () => $selected ? $this->preview->forDevice($selected) : null,
            'selectedDeviceId' => $selected?->id,
        ]);
    }
}
