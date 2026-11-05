<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Support\Facades\DB;

class RefreshCampaignDates
{
    public function handle(): void
    {
        $ids = Campaign::query()->whereIn('status', ['active', 'scheduled'])
            ->where(fn ($q) => $q->whereDate('ends_at', '<', today())->orWhere(fn ($q) => $q->where('status', 'scheduled')->whereDate('starts_at', '<=', today())))
            ->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id) {
                $campaign = Campaign::query()->lockForUpdate()->find($id);
                if (! $campaign || ! in_array($campaign->status, [CampaignStatus::Active, CampaignStatus::Scheduled], true)) {
                    return;
                }
                $status = app(ResolveCampaignTargets::class)->recommendedStatus($campaign);
                if ($campaign->status === $status) {
                    return;
                }
                $campaign->update(['status' => $status]);
                $invalidator = app(InvalidateCampaignDevices::class);
                $invalidator->handle($invalidator->targets($campaign));
            });
        }
    }
}
