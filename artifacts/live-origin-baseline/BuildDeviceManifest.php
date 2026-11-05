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
use App\Domain\Playlists\Models\Playlist;
use App\Domain\Scheduling\Services\ResolveActivePlaylist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildDeviceManifest
{
    public function __construct(
        protected ResolveCampaignTargets $targets,
        protected ResolveActivePlaylist $activePlaylist,
    ) {}

    /**
     * Compose a versioned content manifest. Assets are referenced with checksums
     * so the player only downloads what changed, and the previous manifest stays
     * active until the new one is fully downloaded.
     *
     * The business playlist is resolved from the schedules (time window,
     * weekdays and location). All scheduled playlists are embedded so a player
     * can also switch locally between schedule boundaries.
     */
    public function handle(Device $device): DeviceManifest
    {
        return DB::transaction(fn () => $this->build(Device::query()->lockForUpdate()->findOrFail($device->id)));
    }

    private function build(Device $device): DeviceManifest
    {
        $device->loadMissing(['business', 'location', 'currentLayout', 'currentPlaylist.items.mediaAsset']);

        $layout = $device->currentLayout
            ?? Layout::query()->where('is_default', true)->first()
            ?? Layout::query()->first();

        $activePlaylist = $this->activePlaylist->forDevice($device);
        $scheduledPlaylists = $this->activePlaylist->scheduledPlaylists($device);
        $schedules = $this->activePlaylist->activeSchedules($device);

        $campaigns = $this->activeCampaigns($device);

        $assets = collect()
            ->merge($activePlaylist?->items->pluck('mediaAsset') ?? collect())
            ->merge(collect($scheduledPlaylists)->flatMap(fn (Playlist $playlist) => $playlist->items->pluck('mediaAsset')))
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
            'layout' => $layout ? [
                'id' => $layout->id,
                'name' => $layout->name,
                'orientation' => $layout->orientation?->value,
                'business_percentage' => $layout->business_percentage,
                'advertising_percentage' => $layout->advertising_percentage,
                'configuration' => $layout->configuration,
            ] : null,
            // The playlist that should be playing right now (server decision).
            'business_playlist' => $activePlaylist ? $this->playlistPayload($activePlaylist) : null,
            // Every playlist referenced by a schedule, so the player can switch
            // locally at each boundary without waiting for a new manifest.
            'scheduled_playlists' => collect($scheduledPlaylists)
                ->map(fn (Playlist $playlist) => $this->playlistPayload($playlist))
                ->values(),
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
                        'live_configuration' => $creative->configuration,
                    ])->values(),
                ])->values(),
            ],
            'schedules' => $schedules->map(fn ($schedule) => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'playlist_id' => $schedule->playlist_id,
                'location_id' => $schedule->location_id,
                'daily_start_time' => $schedule->daily_start_time,
                'daily_end_time' => $schedule->daily_end_time,
                'days_of_week' => $schedule->days_of_week,
                'priority' => $schedule->priority,
            ])->values(),
            'active_playlist_id' => $activePlaylist?->id,
            'assets' => $assets->map(fn ($asset) => [
                'id' => $asset->id,
                'type' => $asset->type?->value,
                'url' => $asset->url,
                'checksum' => $asset->checksum,
                'mime_type' => $asset->mime_type,
                'duration' => $asset->duration,
                'filesize' => $asset->filesize,
                'live' => app(LiveSourcePayload::class)->forAsset($asset),
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
            'current_playlist_id' => $activePlaylist?->id ?? $device->current_playlist_id,
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

    /**
     * @return array<string, mixed>
     */
    protected function playlistPayload(Playlist $playlist): array
    {
        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'items' => $playlist->items->map(fn ($item) => [
                'id' => $item->id,
                'media_asset_id' => $item->media_asset_id,
                'order' => $item->sort_order,
                'duration' => $item->duration,
                'transition' => $item->transition,
            ])->values(),
        ];
    }
}
