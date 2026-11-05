<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Campaigns\Models\CampaignTarget;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Actions\UpdateCityCampaigns;
use App\Domain\Locations\Enums\LocationStatus;
use App\Domain\Locations\Models\City;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\CityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('locations.view');
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
        ]);

        $locations = City::query()->with('devices:id,name')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('state', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')->paginate(15)->withQueryString()->through(fn ($city) => $this->present($city));

        return Inertia::render('Admin/Locations/Index', [
            'locations' => $locations,
            'filters' => $request->only('search', 'status'),
            'options' => [
                'statuses' => collect(LocationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
                'devices' => Device::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(CityRequest $request, UpdateCityCampaigns $campaigns): RedirectResponse
    {
        $campaigns->handle(function () use ($request) {
            $city = City::query()->create($request->safe()->except('device_ids'));
            $city->devices()->sync($request->validated('device_ids'));
            app(RecordAudit::class)->handle('city.created', $city);
        }, [$request->validated('name')]);

        return back()->with('success', 'Ciudad creada.');
    }

    public function show(City $location): Response
    {
        $this->authorize('locations.view');
        $location->load('devices:id,name');

        return Inertia::render('Admin/Locations/Show', [
            'location' => $this->present($location),
            'devices' => $location->devices()->with(['cities', 'currentLayout', 'currentPlaylist'])->latest()->get()
                ->map(fn ($device) => EntityPresenter::device($device)),
        ]);
    }

    public function update(CityRequest $request, City $location, UpdateCityCampaigns $campaigns): RedirectResponse
    {
        $campaigns->handle(function () use ($request, $location) {
            // Give unambiguous legacy name targets the same stable identity before a rename.
            if ($location->name !== $request->validated('name') && City::withTrashed()->where('name', $location->name)->count() === 1) {
                CampaignTarget::query()->where('target_type', 'city')->whereNull('target_id')
                    ->where('target_value', $location->name)->update(['target_id' => $location->id, 'target_value' => null]);
            }
            $location->update($request->safe()->except('device_ids'));
            $location->devices()->sync($request->validated('device_ids'));
            app(RecordAudit::class)->handle('city.updated', $location);
        }, [$location->name, $request->validated('name')], $location->id);

        return back()->with('success', 'Ciudad actualizada.');
    }

    public function destroy(City $location, UpdateCityCampaigns $campaigns): RedirectResponse
    {
        $this->authorize('locations.manage');
        $campaigns->handle(function () use ($location) {
            $location->delete();
            app(RecordAudit::class)->handle('city.deleted', $location);
        }, [$location->name], $location->id);

        return redirect()->route('locations.index')->with('success', 'Ciudad eliminada.');
    }

    private function present(City $city): array
    {
        return [
            'id' => $city->id, 'name' => $city->name, 'state' => $city->state,
            'country' => $city->country, 'timezone' => $city->timezone,
            'status' => EntityPresenter::enum($city->status),
            'devices' => $city->devices->map(fn ($device) => ['id' => $device->id, 'name' => $device->name]),
            'devices_count' => $city->devices()->count(),
            'online_devices_count' => $city->devices()->where('status', 'online')->count(),
        ];
    }
}
