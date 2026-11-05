<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\CreativeStatus;
use App\Domain\Campaigns\Events\CampaignPublished;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Operations\Actions\RaiseAlert;
use App\Domain\Operations\Enums\AlertSeverity;
use App\Domain\Operations\Enums\AlertType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishCampaign
{
    public function __construct(
        protected ResolveCampaignTargets $targets,
        protected RaiseAlert $alerts,
    ) {}

    public function handle(Campaign $campaign): Campaign
    {
        $campaign->load(['creatives', 'targets']);
        app(ValidateLiveCampaign::class)->handle($campaign);

        if ($campaign->creatives->isEmpty()) {
            $this->alerts->handle(
                AlertType::CampaignWithoutCreatives,
                'Campaña sin creatividades',
                "La campaña «{$campaign->name}» no tiene creatividades asignadas.",
                AlertSeverity::Critical,
                $campaign,
            );

            throw ValidationException::withMessages([
                'creatives' => 'La campaña necesita al menos una creatividad activa.',
            ]);
        }

        if ($campaign->targets->isEmpty()) {
            $this->alerts->handle(
                AlertType::CampaignWithoutTargets,
                'Campaña sin pantallas objetivo',
                "La campaña «{$campaign->name}» no tiene segmentación definida.",
                AlertSeverity::Critical,
                $campaign,
            );

            throw ValidationException::withMessages([
                'targets' => 'La campaña necesita al menos una regla de segmentación.',
            ]);
        }

        $mediaIds = $campaign->creatives->where('status', CreativeStatus::Active)->pluck('media_asset_id')->unique();
        if ($mediaIds->isEmpty() || MediaAsset::query()->advertising()->ready()->whereIn('id', $mediaIds)->count() !== $mediaIds->count()) {
            throw ValidationException::withMessages(['creatives' => 'Usa creatividades publicitarias activas y procesadas.']);
        }

        $summary = $this->targets->summary($campaign);

        if ($summary['screens'] === 0) {
            $this->alerts->handle(
                AlertType::CampaignWithoutTargets,
                'Campaña sin pantallas activas',
                "La segmentación de «{$campaign->name}» no coincide con ninguna pantalla activa.",
                AlertSeverity::Critical,
                $campaign,
            );

            throw ValidationException::withMessages([
                'targets' => 'La segmentación no coincide con ninguna pantalla activa.',
            ]);
        }

        return DB::transaction(function () use ($campaign, $summary) {
            $campaign->forceFill([
                'status' => $this->targets->recommendedStatus($campaign),
                'target_screen_count' => $summary['screens'],
                'published_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            $this->alerts->resolve(AlertType::CampaignWithoutCreatives, $campaign);
            $this->alerts->resolve(AlertType::CampaignWithoutTargets, $campaign);

            $invalidator = app(InvalidateCampaignDevices::class);
            $invalidator->handle($invalidator->targets($campaign));

            CampaignPublished::dispatch($campaign);

            return $campaign->refresh();
        });
    }

    public function pause(Campaign $campaign): Campaign
    {
        return $this->changeStatus($campaign, CampaignStatus::Paused);
    }

    public function resume(Campaign $campaign): Campaign
    {
        return $this->handle($campaign);
    }

    public function archive(Campaign $campaign): Campaign
    {
        return $this->changeStatus($campaign, CampaignStatus::Archived);
    }

    private function changeStatus(Campaign $campaign, CampaignStatus $status): Campaign
    {
        return DB::transaction(function () use ($campaign, $status) {
            $campaign->forceFill(['status' => $status, 'last_activity_at' => now()])->save();
            $invalidator = app(InvalidateCampaignDevices::class);
            $invalidator->handle($invalidator->targets($campaign));

            return $campaign;
        });
    }
}
