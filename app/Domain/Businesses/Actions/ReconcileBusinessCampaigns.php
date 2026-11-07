<?php

namespace App\Domain\Businesses\Actions;

use App\Domain\Campaigns\Actions\InvalidateCampaignDevices;
use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use Illuminate\Support\Facades\DB;

/** Reconcile historical business rules whenever an assignment or category changes. */
class ReconcileBusinessCampaigns
{
    /** @param array<int, int> $affectedDevices */
    public function handle(callable $change, array $affectedDevices = []): mixed
    {
        return DB::transaction(function () use ($change, $affectedDevices) {
            $campaigns = Campaign::query()->whereHas('targets', fn ($q) => $q
                ->whereIn('target_type', ['business', 'business_category']))->get();
            $resolver = app(ResolveCampaignTargets::class);
            $before = $campaigns->mapWithKeys(fn ($campaign) => [$campaign->id => $resolver->devicesFor($campaign)->pluck('devices.id')->all()]);
            $ids = [];
            $result = $change();
            if ($result instanceof Device) {
                $affectedDevices[] = $result->id;
            }
            foreach ($campaigns as $campaign) {
                $next = $resolver->devicesFor($campaign)->pluck('devices.id')->all();
                $campaign->forceFill(['target_screen_count' => count($next)])->save();
                $previous = $before->get($campaign->id, []);
                $ids = [...$ids, ...array_diff($previous, $next), ...array_diff($next, $previous)];
            }
            app(InvalidateCampaignDevices::class)->handle([...$ids, ...$affectedDevices]);

            return $result;
        });
    }
}
