<?php

namespace App\Domain\Devices\Services;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DevicePlaybackState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playlists\Models\Playlist;
use App\Http\Presenters\EntityPresenter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Read the snapshot acknowledged by this TV; never rebuild on a preview GET. */
class DevicePreviewService
{
    public function __construct(private DevicePlaybackStateService $playback) {}

    public function forDevice(Device $device): array
    {
        $device->loadMissing(['business', 'location', 'currentLayout', 'currentPlaylist']);
        $manifest = $device->current_manifest_version === null ? null
            : $device->manifests()->where('version', $device->current_manifest_version)->first();
        $payload = $manifest?->payload ?? [];
        if ($manifest && ! $this->snapshotBelongsToCurrentBusiness($device, $payload)) {
            $manifest = null;
            $payload = [];
        }
        $at = now(data_get($payload, 'device.timezone', config('app.timezone')));
        $assets = collect($payload['assets'] ?? [])->keyBy('id');
        $playlist = $payload['business_playlist'] ?? null;
        foreach (collect($payload['schedules'] ?? [])->sortByDesc('priority') as $schedule) {
            if ($this->matches($schedule, $at)) {
                $scheduled = collect($payload['scheduled_playlists'] ?? [])->firstWhere('id', $schedule['playlist_id']);
                if ($scheduled) {
                    $playlist = $scheduled;
                    break;
                }
            }
        }
        $item = collect($playlist['items'] ?? [])->sortBy('order')->first(
            fn ($item) => $assets->has($item['media_asset_id'] ?? null)
        );
        $advertising = null;
        foreach ($payload['advertising_playlist']['campaigns'] ?? [] as $campaign) {
            if (! $this->matches($campaign, $at)) {
                continue;
            }
            $creative = collect($campaign['creatives'] ?? [])->first(
                fn ($creative) => $assets->has($creative['media_asset_id'] ?? null)
                    && $this->liveIsEligible($creative, $assets->get($creative['media_asset_id']), $at)
            );
            if ($creative) {
                $advertising = ['campaign_name' => $campaign['name'], 'media' => $assets->get($creative['media_asset_id'])];
                break;
            }
        }

        $preview = [
            'device' => EntityPresenter::device($device),
            'layout' => $this->layout($payload['layout'] ?? null),
            'business_media' => $item ? $assets->get($item['media_asset_id']) : null,
            'advertising' => $advertising,
            'playlist' => $playlist ? ['id' => $playlist['id'], 'name' => $playlist['name']] : null,
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            'manifest_version' => $manifest?->version,
            'pending_manifest_version' => $device->pending_manifest_version,
            'status' => $manifest ? 'approximate' : 'unconfirmed',
            'playback_reported' => false,
            'checked_at' => now()->toIso8601String(),
            'playback' => null,
        ];

        $report = $this->playback->preview($device);
        if ($report === null) {
            return $preview;
        }
        $preview['playback'] = $report;
        $preview['playback_reported'] = true;
        $preview['status'] = $report['fresh'] ? 'reported' : 'stale';
        // A recorded state never falls back to an inferred playlist item, even
        // when stale or when an administrative screen replaces playback.
        $preview['business_media'] = $report['zones']['business']['media'] ?? null;
        $advertising = $report['zones']['advertising'] ?? null;
        $preview['advertising'] = $advertising && $advertising['media']
            ? ['media' => $advertising['media'], 'campaign_name' => $advertising['campaign_name'] ?? 'Contenido reportado'] : null;
        $preview['playlist'] = null;
        $reportedLayout = $report['layout'] ?? null;
        $preview['manifest_version'] = $reportedLayout['manifest_version'] ?? null;
        $preview['layout'] = $reportedLayout ? $reportedLayout + [
            'id' => null,
            'name' => 'Diseño reportado por la TV',
            'orientation' => $reportedLayout['rotation'] % 180 ? 'portrait' : 'landscape',
            'advertising_percentage' => 100 - $reportedLayout['business_percentage'],
            'ratio' => $reportedLayout['business_percentage'].'/'.(100 - $reportedLayout['business_percentage']),
        ] : null;

        return $preview;
    }

    /** A retained pointer after reassignment cannot reveal the previous tenant's snapshot. */
    private function snapshotBelongsToCurrentBusiness(Device $device, array $payload): bool
    {
        $previous = DevicePlaybackState::query()->where('device_id', $device->id)->first();
        $businessId = $device->business_id === null ? null : (int) $device->business_id;
        if ($previous && $previous->business_id !== $businessId) {
            return false;
        }
        $ids = collect($payload['assets'] ?? [])->pluck('id');
        $assets = MediaAsset::query()->whereIn('id', $ids)->get();
        if ($assets->count() !== $ids->unique()->count() || $assets->contains(fn (MediaAsset $asset) => $asset->owner_type === (new Business)->getMorphClass() && (int) $asset->owner_id !== (int) $device->business_id)) {
            return false;
        }
        $playlistIds = collect($payload['scheduled_playlists'] ?? [])->pluck('id');
        $playlistIds->push(data_get($payload, 'business_playlist.id'));

        return ! Playlist::query()->whereIn('id', $playlistIds->filter())->whereNotNull('business_id')
            ->where('business_id', '!=', $device->business_id)->exists();
    }

    private function layout(?array $layout): ?array
    {
        if (! $layout) {
            return null;
        }
        $configuration = $layout['configuration'] ?? [];
        $area = strtolower($configuration['business_area'] ?? 'left');
        $rotation = $configuration['rotation'] ?? (in_array($layout['orientation'] ?? '', ['portrait', 'vertical']) ? 90 : 0);
        $split = strtolower($configuration['split'] ?? (in_array($area, ['top', 'bottom']) ? 'top_bottom' : 'side_by_side'));

        return $layout + [
            'ratio' => $layout['business_percentage'].'/'.$layout['advertising_percentage'],
            'rotation' => (int) $rotation,
            'split' => in_array($split, ['top_bottom', 'vertical']) ? 'top_bottom' : 'side_by_side',
            'business_first' => ! in_array($area, ['right', 'bottom']),
        ];
    }

    private function matches(array $window, CarbonInterface $at): bool
    {
        $date = $at->toDateString();
        if (($window['starts_on'] ?? null) && $date < $window['starts_on']) {
            return false;
        }
        if (($window['ends_on'] ?? null) && $date > $window['ends_on']) {
            return false;
        }
        $days = $window['days_of_week'] ?? [];
        if ($days && ! in_array($at->dayOfWeekIso, $days, true)) {
            return false;
        }
        $start = $window['daily_start_time'] ?? null;
        $end = $window['daily_end_time'] ?? null;
        if (! $start || ! $end) {
            return true;
        }
        $time = $at->format('H:i:s');
        $start = strlen($start) === 5 ? $start.':00' : $start;
        $end = strlen($end) === 5 ? $end.':00' : $end;

        return $start <= $end ? $time >= $start && $time <= $end : $time >= $start || $time <= $end;
    }

    private function liveIsEligible(array $creative, array $asset, CarbonInterface $at): bool
    {
        if (($asset['type'] ?? null) !== 'live_stream') {
            return true;
        }
        $configuration = $creative['live_configuration'] ?? [];
        foreach (['starts_at' => true, 'ends_at' => false] as $key => $start) {
            if (! empty($configuration[$key])) {
                $boundary = Carbon::parse($configuration[$key]);
                if ($start ? $at->lt($boundary) : $at->gte($boundary)) {
                    return false;
                }
            }
        }

        return true;
    }
}
