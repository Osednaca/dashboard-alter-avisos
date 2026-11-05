<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Businesses\Enums\BusinessCategory;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Actions\InvalidateCampaignDevices;
use App\Domain\Campaigns\Actions\PublishCampaign;
use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\CampaignTargetType;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignTarget;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\City;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\CampaignRequest;
use App\Http\Requests\Admin\PreviewCampaignTargetsRequest;
use App\Http\Requests\LibrarySelectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
            'advertiser_id' => ['nullable', 'integer'],
            'city' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
        ]);

        $from = today()->subDays(89);

        $campaigns = Campaign::query()
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('advertiser_id'), fn ($q) => $q->where('advertiser_id', $request->integer('advertiser_id')))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('targets', fn ($qq) => $qq->where('target_type', 'business_category')->where('target_value', $request->string('category'))))
            ->when($request->filled('city'), fn ($q) => $q->whereHas('targets', fn ($qq) => $qq->where('target_type', 'city')->where(fn ($q) => $q
                ->where('target_value', $request->string('city'))
                ->orWhereIn('target_id', City::query()->where('name', $request->string('city'))->select('id')))))
            ->with('advertiser')
            ->withCount('creatives')
            ->withSum(['dailyStats as playbacks_count' => fn ($q) => $q->where('stat_date', '>=', $from)], 'playbacks_count')
            ->withSum(['dailyStats as completed_count' => fn ($q) => $q->where('stat_date', '>=', $from)], 'completed_count')
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Campaign $campaign) => EntityPresenter::campaign($campaign));

        return Inertia::render('Admin/Campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => $request->only('search', 'status', 'advertiser_id', 'city', 'category'),
            'options' => $this->filterOptions(),
        ]);
    }

    public function create(LibrarySelectionRequest $request): Response
    {
        $this->authorize('create', Campaign::class);

        $ids = (array) $request->validated('media_ids', []);
        $media = MediaAsset::query()->advertising()->ready()->whereIn('type', ['image', 'video'])->whereIn('id', $ids)->get()->keyBy('id');
        if ($media->count() !== count($ids)) {
            throw ValidationException::withMessages(['media_ids' => 'La selección contiene archivos no disponibles. Selecciona imágenes o videos listos de la biblioteca de creatividades.']);
        }

        return Inertia::render('Admin/Campaigns/Form', [
            'campaign' => null,
            'selectedMedia' => collect($ids)->map(fn ($id) => EntityPresenter::mediaAsset($media->get($id)))->values()->all(),
            'options' => $this->wizardOptions(),
        ]);
    }

    public function store(CampaignRequest $request, PublishCampaign $publisher): RedirectResponse
    {
        $data = $request->validated();

        $campaign = DB::transaction(function () use ($data) {
            $campaign = Campaign::query()->create([
                'advertiser_id' => $data['advertiser_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => CampaignStatus::Draft,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'daily_start_time' => $data['daily_start_time'] ?? null,
                'daily_end_time' => $data['daily_end_time'] ?? null,
                'days_of_week' => $data['days_of_week'] ?? null,
                'priority' => $data['priority'],
                'playback_goal' => $data['playback_goal'] ?? null,
                'impressions_goal' => $data['impressions_goal'] ?? null,
                'budget' => $data['budget'] ?? null,
            ]);

            $this->syncCreatives($campaign, $data['creatives']);
            $this->syncTargets($campaign, $data['targets']);

            return $campaign;
        });

        app(RecordAudit::class)->handle('campaign.created', $campaign, [], ['name' => $campaign->name]);

        if ($request->boolean('publish')) {
            $publisher->handle($campaign);

            return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaña publicada en la red.');
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaña guardada como borrador.');
    }

    public function show(Campaign $campaign, ResolveCampaignTargets $targets): Response
    {
        $this->authorize('view', $campaign);

        $campaign->load(['advertiser', 'creatives.mediaAsset', 'targets']);

        $statsQuery = $campaign->dailyStats()->where('stat_date', '>=', today()->subDays(29));

        $series = (clone $statsQuery)->orderBy('stat_date')->get()->map(fn ($row) => [
            'date' => $row->stat_date->toDateString(),
            'label' => $row->stat_date->format('d M'),
            'playbacks' => $row->playbacks_count,
            'completed' => $row->completed_count,
            'failures' => $row->failures,
        ]);

        $totals = [
            'playbacks' => (clone $statsQuery)->sum('playbacks_count'),
            'completed' => (clone $statsQuery)->sum('completed_count'),
            'failures' => (clone $statsQuery)->sum('failures'),
            'devices' => (clone $statsQuery)->sum('unique_devices'),
            'businesses' => (clone $statsQuery)->sum('unique_businesses'),
            'duration' => (clone $statsQuery)->sum('total_duration'),
        ];

        $targetDevices = $targets->devicesFor($campaign)
            ->with(['business', 'location'])
            ->limit(24)
            ->get()
            ->map(fn (Device $device) => EntityPresenter::device($device));

        return Inertia::render('Admin/Campaigns/Show', [
            'campaign' => EntityPresenter::campaign($campaign),
            'creatives' => $campaign->creatives->map(fn ($creative) => [
                'id' => $creative->id,
                'duration' => $creative->duration,
                'weight' => $creative->weight,
                'status' => EntityPresenter::enum($creative->status),
                'media' => $creative->mediaAsset ? EntityPresenter::mediaAsset($creative->mediaAsset) : null,
            ]),
            'targets' => $campaign->targets->map(fn ($target) => EntityPresenter::campaignTarget($target)),
            'targetSummary' => $targets->summary($campaign),
            'targetDevices' => $targetDevices,
            'analytics' => ['series' => $series, 'totals' => $totals],
        ]);
    }

    public function edit(Campaign $campaign): Response
    {
        $this->authorize('update', $campaign);

        $campaign->load(['advertiser', 'creatives' => fn ($query) => $query->orderBy('position')->orderBy('id'), 'targets']);

        return Inertia::render('Admin/Campaigns/Form', [
            'campaign' => [
                ...EntityPresenter::campaign($campaign),
                'creatives' => $campaign->creatives->map(fn ($c) => [
                    'media_asset_id' => $c->media_asset_id,
                    'configuration' => $c->configuration,
                    'duration' => $c->duration,
                    'weight' => $c->weight,
                ]),
                'targets' => $campaign->targets->map(fn (CampaignTarget $target) => [
                    'target_type' => $target->target_type->value,
                    'target_id' => $target->target_id,
                    'target_value' => $target->target_value,
                    'is_exclusion' => $target->is_exclusion,
                ]),
            ],
            'options' => $this->wizardOptions(),
        ]);
    }

    public function update(CampaignRequest $request, Campaign $campaign, PublishCampaign $publisher): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($campaign, $data) {
            $invalidator = app(InvalidateCampaignDevices::class);
            $previousDevices = $invalidator->targets($campaign);
            $campaign->update([
                'advertiser_id' => $data['advertiser_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'daily_start_time' => $data['daily_start_time'] ?? null,
                'daily_end_time' => $data['daily_end_time'] ?? null,
                'days_of_week' => $data['days_of_week'] ?? null,
                'priority' => $data['priority'],
                'playback_goal' => $data['playback_goal'] ?? null,
                'impressions_goal' => $data['impressions_goal'] ?? null,
                'budget' => $data['budget'] ?? null,
            ]);

            $this->syncCreatives($campaign, $data['creatives']);
            $this->syncTargets($campaign, $data['targets']);
            if (in_array($campaign->status, [CampaignStatus::Active, CampaignStatus::Scheduled], true)) {
                $campaign->update(['status' => app(ResolveCampaignTargets::class)->recommendedStatus($campaign)]);
            }
            $invalidator->handle(array_merge($previousDevices, $invalidator->targets($campaign)));
        });

        app(RecordAudit::class)->handle('campaign.updated', $campaign);

        if ($request->boolean('publish')) {
            $publisher->handle($campaign);

            return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaña actualizada y publicada.');
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaña actualizada.');
    }

    public function publish(Campaign $campaign, PublishCampaign $publisher): RedirectResponse
    {
        $this->authorize('publish', $campaign);

        $publisher->handle($campaign);

        app(RecordAudit::class)->handle('campaign.published', $campaign, [], ['status' => $campaign->status->value]);

        return back()->with('success', 'Campaña publicada en la red.');
    }

    public function pause(Campaign $campaign, PublishCampaign $publisher): RedirectResponse
    {
        $this->authorize('publish', $campaign);

        $publisher->pause($campaign);

        app(RecordAudit::class)->handle('campaign.paused', $campaign);

        return back()->with('success', 'Campaña pausada.');
    }

    public function resume(Campaign $campaign, PublishCampaign $publisher): RedirectResponse
    {
        $this->authorize('publish', $campaign);

        $publisher->resume($campaign);

        app(RecordAudit::class)->handle('campaign.resumed', $campaign);

        return back()->with('success', 'Campaña reactivada.');
    }

    public function archive(Campaign $campaign, PublishCampaign $publisher): RedirectResponse
    {
        $this->authorize('publish', $campaign);

        $publisher->archive($campaign);

        app(RecordAudit::class)->handle('campaign.archived', $campaign);

        return back()->with('success', 'Campaña archivada.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        DB::transaction(function () use ($campaign) {
            $invalidator = app(InvalidateCampaignDevices::class);
            $ids = $invalidator->targets($campaign);
            $campaign->delete();
            $invalidator->handle($ids);
        });

        return redirect()->route('campaigns.index')->with('success', 'Campaña eliminada.');
    }

    public function previewTargets(PreviewCampaignTargetsRequest $request, ResolveCampaignTargets $targets): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        $payload = $request->validated();

        $campaign = new Campaign;
        $campaign->exists = true;
        $campaign->setRelation('targets', collect($payload['targets'] ?? [])->map(function ($target) {
            $model = new CampaignTarget($target);
            $model->target_type = CampaignTargetType::from($target['target_type']);

            return $model;
        }));

        return response()->json(['summary' => $targets->summary($campaign)]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $creatives
     */
    protected function syncCreatives(Campaign $campaign, array $creatives): void
    {
        $campaign->creatives()->delete();

        foreach (array_values($creatives) as $index => $creative) {
            $campaign->creatives()->create([
                'media_asset_id' => $creative['media_asset_id'],
                'configuration' => empty($creative['configuration']) ? null : [
                    ...$creative['configuration'],
                    'starts_at' => Carbon::parse($creative['configuration']['starts_at'])->toIso8601String(),
                    'ends_at' => Carbon::parse($creative['configuration']['ends_at'])->toIso8601String(),
                ],
                'duration' => $creative['duration'],
                'weight' => $creative['weight'],
                'position' => $index,
                'status' => 'active',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $targets
     */
    protected function syncTargets(Campaign $campaign, array $targets): void
    {
        $campaign->targets()->delete();

        foreach ($targets as $target) {
            $campaign->targets()->create([
                'target_type' => $target['target_type'],
                'target_id' => $target['target_id'] ?? null,
                'target_value' => $target['target_value'] ?? null,
                'is_exclusion' => (bool) ($target['is_exclusion'] ?? false),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterOptions(): array
    {
        return [
            'statuses' => collect(CampaignStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            'advertisers' => Advertiser::query()->orderBy('name')->get(['id', 'name']),
            'cities' => City::query()->orderBy('name')->distinct()->pluck('name'),
            'categories' => BusinessCategory::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function wizardOptions(): array
    {
        return [
            'advertisers' => Advertiser::query()->orderBy('name')->get(['id', 'name', 'status'])->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'status' => EntityPresenter::enum($a->status),
            ]),
            'creatives' => MediaAsset::query()->advertising()->ready()->latest()->get()->map(fn ($asset) => EntityPresenter::mediaAsset($asset)),
            'cities' => City::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'state', 'country']),
            'categories' => BusinessCategory::options(),
            'businesses' => Business::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->with('business:id,name')->orderBy('name')->get(['id', 'name', 'city', 'business_id'])->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'city' => $l->city,
                'business' => $l->business?->name,
            ]),
            'devices' => Device::query()->with('business:id,name')->orderBy('name')->get(['id', 'name', 'business_id'])->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'business' => $d->business?->name,
            ]),
            'counts' => [
                'screens' => Device::query()->count(),
                'businesses' => Business::query()->count(),
                'locations' => Location::query()->count(),
                'cities' => City::query()->where('status', 'active')->count(),
            ],
        ];
    }
}
