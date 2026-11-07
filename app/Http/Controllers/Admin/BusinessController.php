<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Businesses\Actions\ReconcileBusinessCampaigns;
use App\Domain\Businesses\Enums\BusinessCategory;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Locations\Models\City;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\BusinessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Business::class);
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'string'],
            'category' => ['nullable', 'string'], 'city' => ['nullable', 'string'],
            'sort' => ['nullable', 'in:name,devices,created_at'], 'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $sort = $request->string('sort', 'created_at')->toString();
        $businesses = Business::query()->where('slug', '!=', Business::INTERNAL_SLUG)
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('city'), fn ($q) => $q->whereHas('devices.cities', fn ($city) => $city
                ->where('cities.name', $request->string('city'))->where('cities.status', 'active')))
            ->withCount(['locations', 'devices', 'devices as online_devices_count' => fn ($q) => $q->where('status', DeviceStatus::Online->value)])
            ->orderBy($sort === 'devices' ? 'devices_count' : $sort, $request->string('direction', 'desc')->toString())
            ->paginate(12)->withQueryString()->through(fn (Business $business) => EntityPresenter::business($business));

        return Inertia::render('Admin/Businesses/Index', [
            'businesses' => $businesses,
            'filters' => $request->only('search', 'status', 'category', 'city', 'sort', 'direction'),
            'options' => $this->options(),
        ]);
    }

    public function store(BusinessRequest $request): RedirectResponse
    {
        $business = Business::query()->create($request->validated());
        app(RecordAudit::class)->handle('business.created', $business, [], ['name' => $business->name]);

        return redirect()->route('businesses.show', $business)->with('success', 'Negocio creado correctamente.');
    }

    public function show(Business $business): Response
    {
        $this->authorize('view', $business);
        abort_if($business->slug === Business::INTERNAL_SLUG, 404);
        $business->loadCount(['locations', 'devices', 'devices as online_devices_count' => fn ($q) => $q->where('status', DeviceStatus::Online->value)]);

        return Inertia::render('Admin/Businesses/Show', [
            'business' => EntityPresenter::business($business),
            'devices' => $business->devices()->with(['business', 'cities', 'currentLayout'])->latest()->get()
                ->map(fn ($device) => EntityPresenter::device($device)),
            'options' => $this->options(),
        ]);
    }

    public function update(BusinessRequest $request, Business $business, ReconcileBusinessCampaigns $reconcile): RedirectResponse
    {
        abort_if($business->slug === Business::INTERNAL_SLUG, 404);
        $data = $request->validated();
        $reconcile->handle(function () use ($business, $data) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $old = $business->only(['name', 'category', 'status', 'contact_email', 'timezone']);
            $business->update($data);
            app(RecordAudit::class)->handle('business.updated', $business, $old, $business->only(['name', 'category', 'status', 'contact_email', 'timezone']));
        }, $business->devices()->pluck('id')->all());

        return back()->with('success', 'Negocio actualizado.');
    }

    public function destroy(Business $business): RedirectResponse
    {
        $this->authorize('delete', $business);
        abort_if($business->slug === Business::INTERNAL_SLUG, 404);
        $deleted = DB::transaction(function () use ($business) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $dependent = $business->devices()->exists() || $business->locations()->exists()
                || $business->users()->exists() || $business->cities()->exists()
                || $business->playlists()->exists() || $business->schedules()->exists()
                || $business->mediaAssets()->exists() || $business->dailyStats()->exists()
                || DB::table('device_activations')->where('business_id', $business->id)->exists()
                || DB::table('quick_plays')->where('business_id', $business->id)->exists()
                || DB::table('device_playback_states')->where('business_id', $business->id)->exists()
                || DB::table('campaign_targets')->where('target_type', 'business')->where('target_id', $business->id)->exists();
            if ($dependent) {
                return false;
            }
            app(RecordAudit::class)->handle('business.deleted', $business, ['name' => $business->name]);
            $business->delete();

            return true;
        });

        return $deleted
            ? redirect()->route('businesses.index')->with('success', 'Negocio eliminado.')
            : back()->with('error', 'No se puede eliminar un negocio con avisos o datos asociados.');
    }

    private function options(): array
    {
        return [
            'statuses' => collect(BusinessStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            'categories' => BusinessCategory::options(),
            'cities' => City::query()->where('status', 'active')->distinct()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
