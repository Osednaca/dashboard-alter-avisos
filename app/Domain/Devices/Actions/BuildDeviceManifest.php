<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Enums\ManifestStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceManifest;
use App\Domain\Media\Models\Layout;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Services\LiveSourcePayload;
use App\Domain\Scheduling\Services\ResolveActivePlaylist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildDeviceManifest
{
    public function __construct(
        protected ResolveCampaignTargets $targets,
        protected ResolveActivePlaylist $activePlaylist,
        protected LiveSourcePayload $liveSourcePayload,
    ) {}

    /**
     * Compose a versioned content manifest. Assets are referenced with checksums
     * so the player only downloads what changed, and the previous manifest stays
     * active until the new one is fully downloaded.
     *
     * Advertising uses the complete display while the legacy playlist keys
     * remain present for clients that expect them.
     */
    public function handle(Device $device): DeviceManifest
    {
        return DB::transaction(fn () => $this->build(Device::query()->lockForUpdate()->findOrFail($device->id)));
    }

    /**
     * Repair cached manifests whose device pointer is missing or whose live embed URLs
     * were signed for a noncanonical origin. Re-read the pointer under the device lock
     * so a good pending version is preferred over an older current version.
     */
    public function rebuildIfSnapshotIsOutdated(Device $device): bool
    {
        return DB::transaction(function () use ($device): bool {
            $locked = Device::query()->lockForUpdate()->findOrFail($device->id);

            if ($locked->pending_manifest_version !== null) {
                $candidate = $locked->manifests()->where('version', $locked->pending_manifest_version)->first();
                $expectedStatus = ManifestStatus::Pending;
            } elseif ($locked->current_manifest_version !== null) {
                $candidate = $locked->manifests()->where('version', $locked->current_manifest_version)->first();
                $expectedStatus = ManifestStatus::Current;
            } else {
                return false;
            }

            $pointerIsInconsistent = ! $candidate || $candidate->status !== $expectedStatus;
            $originIsNoncanonical = $candidate
                && $this->liveSourcePayload->hasNoncanonicalEmbedUrls($candidate->payload ?? []);
            // Existing snapshots must also be replaced when this dashboard is
            // upgraded from a two-zone installation.
            $layoutIsSplit = $candidate && (
                data_get($candidate->payload, 'layout.business_percentage') !== 0
                || data_get($candidate->payload, 'layout.advertising_percentage') !== 100
                || data_get($candidate->payload, 'business_playlist') !== null
                || ! empty($candidate->payload['scheduled_playlists'])
                || collect(data_get($candidate->payload, 'advertising_playlist.campaigns', []))
                    ->flatMap(fn ($campaign) => $campaign['creatives'] ?? [])
                    ->contains(fn ($creative) => isset($creative['live_configuration'])
                        && ($creative['live_configuration']['display_mode'] ?? null) !== 'fullscreen')
            );

            if (! $pointerIsInconsistent && ! $originIsNoncanonical && ! $layoutIsSplit) {
                return false;
            }

            $this->build($locked);

            return true;
        });
    }

    private function build(Device $device): DeviceManifest
    {
        $device->loadMissing(['business', 'location', 'currentLayout', 'currentPlaylist.items.mediaAsset']);

        $layout = $device->currentLayout
            ?? Layout::query()->where('is_default', true)->first()
            ?? Layout::query()->first();

        $campaigns = $this->activeCampaigns($device);

        $assets = collect()
            ->merge($campaigns->flatMap(fn (Campaign $c) => $c->creatives->pluck('mediaAsset')))
            ->merge(MediaAsset::query()->whereIn('id', $campaigns->flatMap(fn ($c) => $c->creatives->map(fn ($creative) => $creative->configuration['fallback_media_id'] ?? null))->filter())->get())
            ->filter()
            ->unique('id')
            ->values();

        // Settings can change several times in one second. A version must
        // remain unique and increase for the player's atomic installer.
        $version = (string) max((int) now()->format('YmdHis'), (int) $device->manifests()->max('version') + 1);

        $payload = [
            'manifest_version' => $version,
            'generated_at' => now()->toIso8601String(),
            'device' => [
                'id' => $device->id,
                'uuid' => $device->uuid,
                'name' => $device->name,
                'timezone' => $this->activePlaylist->timezoneFor($device),
            ],
            'layout' => [
                'id' => $layout?->id,
                'name' => $layout?->name ?? 'Publicidad',
                'orientation' => $layout?->orientation?->value ?? 'landscape',
                'business_percentage' => 0,
                'advertising_percentage' => 100,
                'configuration' => array_replace($layout?->configuration ?? [], [
                    'audio_mode' => ($layout?->configuration['audio_mode'] ?? null) === 'none' ? 'none' : 'advertising',
                ]),
            ],
            // Retain the existing protocol keys without distributing business content.
            'business_playlist' => null,
            'scheduled_playlists' => [],
            'advertising_playlist' => [
                'campaigns' => $campaigns->map(fn (Campaign $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'priority' => $c->priority,
                    'starts_on' => $c->starts_at?->toDateString(),
                    'ends_on' => $c->ends_at?->toDateString(),
                    'daily_start_time' => $c->daily_start_time,
                    'daily_end_time' => $c->daily_end_time,
                    'days_of_week' => $c->days_of_week,
                    'creatives' => $c->creatives->map(fn ($creative) => [
                        'creative_id' => $creative->id,
                        'media_asset_id' => $creative->media_asset_id,
                        'duration' => $creative->duration,
                        'weight' => $creative->weight,
                        'live_configuration' => $creative->configuration === null ? null
                            : array_replace($creative->configuration, ['display_mode' => 'fullscreen']),
                    ])->values(),
                ])->values(),
            ],
            'schedules' => [],
            'active_playlist_id' => null,
            'assets' => $assets->map(fn ($asset) => [
                'id' => $asset->id,
                'type' => $asset->type?->value,
                'url' => $asset->url,
                'checksum' => $asset->checksum,
                'mime_type' => $asset->mime_type,
                'duration' => $asset->duration,
                'filesize' => $asset->filesize,
                'live' => $this->liveSourcePayload->forAsset($asset),
            ])->values(),
            'configuration' => [
                'timezone' => $this->activePlaylist->timezoneFor($device),
                'heartbeat_interval_seconds' => 60,
                'sync_interval_seconds' => 300,
                'offline_after_minutes' => config('signage.device.offline_after_minutes'),
            ],
        ];

        $checksum = hash('sha256', json_encode($payload));

        $device->manifests()
            ->where('status', ManifestStatus::Pending->value)
            ->update(['status' => ManifestStatus::Superseded->value]);

        $manifest = $device->manifests()->create([
            'version' => $version,
            'checksum' => $checksum,
            'payload' => $payload,
            'status' => ManifestStatus::Pending,
            'generated_at' => now(),
        ]);

        $device->forceFill([
            'pending_manifest_version' => $version,
            'manifest_dirty' => false,
        ])->save();

        return $manifest;
    }

    /**
     * @return Collection<int, Campaign>
     */
    protected function activeCampaigns(Device $device): Collection
    {
        return Campaign::query()
            ->whereIn('status', [CampaignStatus::Active->value, CampaignStatus::Scheduled->value])
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', today())->orWhereHas('creatives.mediaAsset', fn ($a) => $a->where('type', 'live_stream')))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
            ->with(['creatives' => fn ($q) => $q->where('status', 'active')->orderBy('position')->orderBy('id'), 'creatives.mediaAsset'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Campaign $campaign) => $this->targets->devicesFor($campaign)->whereKey($device->id)->exists())
            ->sortByDesc('priority')
            ->values();
    }

}
