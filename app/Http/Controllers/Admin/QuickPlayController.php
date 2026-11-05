<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\City;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Operations\Actions\RecordAudit;
use App\Domain\QuickPlay\Actions\DeleteQuickPlay;
use App\Domain\QuickPlay\Actions\RetryQuickPlay;
use App\Domain\QuickPlay\Actions\StartQuickPlay;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use App\Domain\QuickPlay\Enums\QuickPlayStatus;
use App\Domain\QuickPlay\Models\QuickPlay;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\StartQuickPlayRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuickPlayController extends Controller
{
    protected string $portal = 'admin';

    protected function quickPlaysQuery(): Builder
    {
        return QuickPlay::query()->whereNull('business_id')->whereHas('mediaAsset', fn ($query) => $query->advertising());
    }

    protected function mediaQuery(): Builder
    {
        return MediaAsset::query()->advertising()->whereIn('type', ['image', 'video']);
    }

    protected function devicesQuery(): Builder
    {
        return Device::query();
    }

    protected function businessesQuery(): Builder
    {
        return Business::query();
    }

    protected function locationsQuery(): Builder
    {
        return Location::query();
    }

    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
        ]);

        $quickPlays = $this->quickPlaysQuery()
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['mediaAsset', 'user'])
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (QuickPlay $quickPlay) => EntityPresenter::quickPlay($quickPlay));

        return Inertia::render('Admin/QuickPlay/Index', [
            'portal' => $this->portal,
            'quickPlays' => $quickPlays,
            'filters' => $request->only('search', 'status'),
            'statuses' => collect(QuickPlayStatus::cases())
                ->map(fn ($status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/QuickPlay/Create', [
            'portal' => $this->portal,
            'options' => $this->options(),
        ]);
    }

    public function store(StartQuickPlayRequest $request, StartQuickPlay $start, RecordAudit $audit): RedirectResponse
    {
        $media = MediaAsset::query()->advertising()->findOrFail($request->integer('media_asset_id'));

        $quickPlay = $start->handle(
            $request->user(),
            $media,
            QuickPlayDisplayMode::from($request->string('display_mode')->toString()),
            QuickPlayScope::from($request->string('scope')->toString()),
            $request->filled('duration') ? $request->integer('duration') : null,
            [
                'device_ids' => $request->input('device_ids', []),
                'city_ids' => $request->input('city_ids', []),
            ],
        );

        $audit->handle('quick_play.sent', $quickPlay, [], [
            'media' => $media->filename,
            'display_mode' => $quickPlay->display_mode->value,
            'scope' => $quickPlay->scope->value,
            'targets' => $quickPlay->targets_count,
        ]);

        return redirect()
            ->route('quick-play.show', $quickPlay)
            ->with('success', 'Reproducción inmediata enviada a '.$quickPlay->targets_count.' aviso(s) LED.');
    }

    public function show(QuickPlay $quickPlay): Response
    {
        $this->quickPlaysQuery()->findOrFail($quickPlay->id);
        $quickPlay->load([
            'mediaAsset',
            'user',
            'devices.device.cities:id,name',
        ]);

        return Inertia::render('Admin/QuickPlay/Show', [
            'portal' => $this->portal,
            'quickPlay' => EntityPresenter::quickPlay($quickPlay),
            'devices' => $quickPlay->devices
                ->map(fn ($row) => EntityPresenter::quickPlayDevice($row))
                ->values()
                ->all(),
        ]);
    }

    public function retry(Request $request, QuickPlay $quickPlay, RetryQuickPlay $retry, RecordAudit $audit): RedirectResponse
    {
        $source = $this->quickPlaysQuery()->findOrFail($quickPlay->id);
        $attempt = $retry->handle($source, $request->user());
        $audit->handle($this->portal.'.quick_play.retried', $source, [], ['retry_id' => $attempt->id, 'targets' => $attempt->targets_count]);

        return redirect()->route($this->portal === 'business' ? 'business.quick-play.show' : 'quick-play.show', $attempt)
            ->with('success', 'Reintento preparado para '.$attempt->targets_count.' aviso(s) LED. Revisa el estado de entrega.');
    }

    public function destroy(QuickPlay $quickPlay, DeleteQuickPlay $delete, RecordAudit $audit): RedirectResponse
    {
        $source = $this->quickPlaysQuery()->findOrFail($quickPlay->id);
        $delete->handle($source);
        $audit->handle($this->portal.'.quick_play.deleted', $source);

        return redirect()->route($this->portal === 'business' ? 'business.quick-play.index' : 'quick-play.index')
            ->with('success', 'Instant Play eliminado del historial. Se cancelaron las entregas pendientes.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function options(): array
    {
        return [
            'media' => $this->mediaQuery()
                ->ready()
                ->latest()
                ->limit(80)
                ->get()
                ->map(fn (MediaAsset $asset) => EntityPresenter::mediaAsset($asset))
                ->values()
                ->all(),
            'devices' => $this->devicesQuery()
                ->whereNotIn('status', [DeviceStatus::Disabled->value, DeviceStatus::PendingActivation->value])
                ->with('cities:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'business_id', 'location_id', 'status', 'last_seen_at'])
                ->map(fn (Device $device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'city' => $device->cities->pluck('name')->join(', '),
                    'status' => EntityPresenter::enum($device->status),
                    'is_online' => $device->isOnline(),
                ])
                ->all(),
            'cities' => City::query()->where('status', 'active')->orderBy('name')
                ->with(['devices' => fn ($q) => $q->whereNotIn('devices.status', [DeviceStatus::Disabled->value, DeviceStatus::PendingActivation->value])])
                ->get()->map(fn (City $city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                    'device_ids' => $city->devices->pluck('id')->all(),
                    'devices_count' => $city->devices->count(),
                ])->all(),
            'displayModes' => collect([QuickPlayDisplayMode::Fullscreen])
                ->map(fn (QuickPlayDisplayMode $mode) => [
                    'value' => $mode->value,
                    'label' => $mode->label(),
                    'description' => $mode->description(),
                ])
                ->all(),
            'scopes' => collect([QuickPlayScope::Devices, QuickPlayScope::Cities, QuickPlayScope::All])
                ->map(fn (QuickPlayScope $scope) => ['value' => $scope->value, 'label' => $scope->label()])
                ->all(),
            'counts' => [
                'devices' => $this->devicesQuery()
                    ->whereNotIn('status', [DeviceStatus::Disabled->value, DeviceStatus::PendingActivation->value])
                    ->count(),
            ],
        ];
    }
}
