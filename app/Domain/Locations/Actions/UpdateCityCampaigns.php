<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Campaigns\Actions\InvalidateCampaignDevices;
use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Support\Facades\DB;

class UpdateCityCampaigns
{
    public function handle(callable $change, array $names, ?int $cityId = null): void
    {
        DB::transaction(function () use ($change, $names, $cityId) {
            $campaigns = Campaign::query()->whereHas('targets', fn ($q) => $q->where('target_type', 'city')
                ->where(fn ($q) => $q->whereIn('target_value', $names)->when($cityId, fn ($q) => $q->orWhere('target_id', $cityId))))->get();
            $resolver = app(ResolveCampaignTargets::class);
            $ids = $campaigns->flatMap(fn ($campaign) => $resolver->devicesFor($campaign)->pluck('devices.id'))->all();
            $change();
            foreach ($campaigns as $campaign) {
                $next = $resolver->devicesFor($campaign)->pluck('devices.id')->all();
                $ids = [...$ids, ...$next];
                $campaign->forceFill(['target_screen_count' => count($next)])->save();
            }
            app(InvalidateCampaignDevices::class)->handle($ids);
        });
    }
}
