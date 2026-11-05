<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\Models\BusinessDailyStat;
use App\Domain\Businesses\Enums\BusinessCategory;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Locations\Enums\LocationStatus;
use App\Domain\Locations\Models\Location;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\BusinessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Business::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'sort' => ['nullable', 'in:name,devices,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $sort = $request->string('sort', 'created_at')->toString();
        $direction = $request->string('direction', 'desc')->toString();

        $businesses = Business::query()
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('city'), fn ($q) => $q->whereHas('locations', fn ($qq) => $qq->where('city', $request->string('city'))))
            ->withCount([
                'locations',
                'devices',
                'devices as online_devices_count' => fn ($q) => $q->where('status', DeviceStatus::Online->value),
            ])
            ->with('devices.currentLayout')
            ->orderBy($sort === 'devices' ? 'devices_count' : $sort, $direction)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Business $business) => EntityPresenter::business($business));

        return Inertia::render('Admin/Businesses/Index', [
            'businesses' => $businesses,
            'filters' => $request->only('search', 'status', 'category', 'city', 'sort', 'direction'),
            'options' => [
                'statuses' => collect(BusinessStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
                'categories' => BusinessCategory::options(),
                'cities' => Location::query()->distinct()->orderBy('city')->pluck('city')->all(),
            ],
        ]);
    }

    public function store(BusinessRequest $request): RedirectResponse
    {
        $business = Business::query()->create($request->validated());

        return redirect()->route('businesses.show', $business)->with('success', 'Negocio creado correctamente.');
    }

    public function show(Business $business): Response
    {
        $this->authorize('view', $business);

        $business->loadCount([
            'locations',
            'devices',
            'devices as online_devices_count' => fn ($q) => $q->where('status', DeviceStatus::Online->value),
        ]);

        $locations = $business->locations()->withCount([
            'devices',
            'devices as online_devices_count' => fn ($q) => $q->where('status', DeviceStatus::Online->value),
        ])->get()->map(fn ($location) => EntityPresenter::location($location));

        $devices = $business->devices()->with(['location', 'currentLayout'])->latest()->limit(50)->get()
            ->map(fn ($device) => EntityPresenter::device($device));

        $playlists = $business->playlists()->withCount('items')->latest()->get()
            ->map(fn ($playlist) => EntityPresenter::playlist($playlist));

        $campaigns = Campaign::query()
            ->whereHas('targets', fn ($q) => $q->where('target_type', 'business')->where('target_id', $business->id))
            ->orWhereHas('targets', fn ($q) => $q->where('target_type', 'business_category')->where('target_value', $business->category->value))
            ->with('advertiser')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($campaign) => EntityPresenter::campaign($campaign));

        $stats = BusinessDailyStat::query()
            ->where('business_id', $business->id)
            ->where('stat_date', '>=', today()->subDays(29))
            ->orderBy('stat_date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->stat_date->toDateString(),
                'label' => $row->stat_date->format('d M'),
                'playbacks' => $row->playbacks_count,
                'completed' => $row->completed_count,
                'failures' => $row->failures,
            ]);

        return Inertia::render('Admin/Businesses/Show', [
            'business' => EntityPresenter::business($business),
            'locations' => $locations,
            'devices' => $devices,
            'playlists' => $playlists,
            'campaigns' => $campaigns,
            'analytics' => [
                'series' => $stats,
                'totals' => [
                    'playbacks' => $stats->sum('playbacks'),
                    'completed' => $stats->sum('completed'),
                    'failures' => $stats->sum('failures'),
                ],
            ],
            'users' => $business->users()->get()->map(fn ($user) => EntityPresenter::user($user)),
            'options' => [
                'statuses' => collect(BusinessStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
                'categories' => BusinessCategory::options(),
                'locationStatuses' => collect(LocationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            ],
        ]);
    }

    public function update(BusinessRequest $request, Business $business): RedirectResponse
    {
        $old = $business->only(['name', 'category', 'status', 'contact_email', 'timezone']);
        $business->update($request->validated());

        app(RecordAudit::class)->handle(
            'business.updated', $business, $old, $business->only(['name', 'category', 'status', 'contact_email', 'timezone'])
        );

        return back()->with('success', 'Negocio actualizado.');
    }

    public function destroy(Business $business): RedirectResponse
    {
        $this->authorize('delete', $business);

        $business->delete();

        return redirect()->route('businesses.index')->with('success', 'Negocio eliminado.');
    }
}
