<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Advertisers\Enums\AdvertiserStatus;
use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\AdvertiserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdvertiserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Advertiser::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
        ]);

        $advertisers = Advertiser::query()
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->withCount([
                'campaigns',
                'campaigns as active_campaigns_count' => fn ($q) => $q->where('status', CampaignStatus::Active->value),
            ])
            ->addSelect([
                'total_playbacks' => DB::table('campaign_daily_stats')
                    ->join('campaigns', 'campaigns.id', '=', 'campaign_daily_stats.campaign_id')
                    ->whereColumn('campaigns.advertiser_id', 'advertisers.id')
                    ->selectRaw('COALESCE(SUM(playbacks_count), 0)'),
                'total_screens' => DB::table('campaigns')
                    ->whereColumn('campaigns.advertiser_id', 'advertisers.id')
                    ->selectRaw('COALESCE(SUM(target_screen_count), 0)'),
            ])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Advertiser $advertiser) => EntityPresenter::advertiser($advertiser));

        return Inertia::render('Admin/Advertisers/Index', [
            'advertisers' => $advertisers,
            'filters' => $request->only('search', 'status'),
            'options' => [
                'statuses' => collect(AdvertiserStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            ],
        ]);
    }

    public function store(AdvertiserRequest $request): RedirectResponse
    {
        $advertiser = Advertiser::query()->create($request->validated());

        app(RecordAudit::class)->handle('advertiser.created', $advertiser, [], $advertiser->only('name', 'status'));

        return back()->with('success', 'Anunciante creado.');
    }

    public function show(Advertiser $advertiser): Response
    {
        $this->authorize('view', $advertiser);

        $advertiser->loadCount([
            'campaigns',
            'campaigns as active_campaigns_count' => fn ($q) => $q->where('status', CampaignStatus::Active->value),
        ]);

        $campaigns = $advertiser->campaigns()->withCount('creatives')->withSum(
            ['dailyStats as playbacks_count' => fn ($q) => $q->where('stat_date', '>=', today()->subDays(89))],
            'playbacks_count',
        )->withSum(
            ['dailyStats as completed_count' => fn ($q) => $q->where('stat_date', '>=', today()->subDays(89))],
            'completed_count',
        )->latest()->get()->map(fn ($campaign) => EntityPresenter::campaign($campaign));

        $creatives = MediaAsset::query()
            ->where('owner_type', $advertiser->getMorphClass())
            ->where('owner_id', $advertiser->id)
            ->withCount('campaignCreatives as usage_count')
            ->latest()
            ->get()
            ->map(fn ($asset) => EntityPresenter::mediaAsset($asset));

        $series = DB::table('campaign_daily_stats')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_daily_stats.campaign_id')
            ->where('campaigns.advertiser_id', $advertiser->id)
            ->where('stat_date', '>=', today()->subDays(29))
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->selectRaw('stat_date, SUM(playbacks_count) as playbacks, SUM(completed_count) as completed')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->stat_date,
                'label' => Carbon::parse($row->stat_date)->format('d M'),
                'playbacks' => (int) $row->playbacks,
                'completed' => (int) $row->completed,
            ]);

        return Inertia::render('Admin/Advertisers/Show', [
            'advertiser' => EntityPresenter::advertiser($advertiser),
            'campaigns' => $campaigns,
            'creatives' => $creatives,
            'analytics' => ['series' => $series],
        ]);
    }

    public function update(AdvertiserRequest $request, Advertiser $advertiser): RedirectResponse
    {
        $advertiser->update($request->validated());

        app(RecordAudit::class)->handle('advertiser.updated', $advertiser);

        return back()->with('success', 'Anunciante actualizado.');
    }

    public function destroy(Advertiser $advertiser): RedirectResponse
    {
        $this->authorize('delete', $advertiser);

        $advertiser->delete();

        return redirect()->route('advertisers.index')->with('success', 'Anunciante eliminado.');
    }
}
