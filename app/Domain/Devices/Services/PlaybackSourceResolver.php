<?php

namespace App\Domain\Devices\Services;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Enums\DeviceCommandType;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Models\QuickPlayDevice;
use Illuminate\Validation\ValidationException;

/** Client identities select only sources already delivered by this server to this TV. */
class PlaybackSourceResolver
{
    public function resolve(Device $device, array $report): array
    {
        $zones = $report['zones'];
        if ($zones && ! $report['layout']) {
            $this->invalid('layout');
        }
        if (! empty($report['layout']['manifest_version'])) {
            $this->manifest($device, $report['layout']['manifest_version']);
        }
        $resolved = [];
        foreach ($zones as $name => $zone) {
            $key = 'zones.'.$name;
            if ($zone['source'] === 'empty') {
                if ($zone['state'] !== 'empty' || array_intersect(array_keys(array_filter($zone, fn ($value) => $value !== null)), [
                    'media_asset_id', 'manifest_version', 'item_id', 'quick_play_device_id', 'command_id', 'live_creative_id',
                ])) {
                    $this->invalid($key);
                }
                $resolved[$name] = $zone + ['media' => null];

                continue;
            }
            if (empty($zone['media_asset_id']) || $zone['state'] === 'empty') {
                $this->invalid($key);
            }
            if ($zone['source'] === 'quick_play') {
                $resolved[$name] = $zone + ['media' => $this->quick($device, $name, $zone, $key)];

                continue;
            }
            if (empty($zone['manifest_version'])) {
                $this->invalid($key);
            }
            $payload = $this->manifest($device, $zone['manifest_version']);
            $asset = collect($payload['assets'] ?? [])->firstWhere('id', $zone['media_asset_id']);
            if (! $asset || ! $this->visibleAsset($device, $asset['id'])) {
                $this->invalid($key);
            }
            $campaignName = null;
            if ($zone['source'] === 'manifest') {
                if ($name === 'fullscreen' || ($asset['type'] ?? '') === 'live_stream'
                    || ! $this->normalItem($payload, $name, $zone)) {
                    $this->invalid($key);
                }
            } else {
                $live = $this->liveCreative($payload, $zone['live_creative_id'] ?? null);
                if (! $live || ($live['creative']['live_configuration']['display_mode'] ?? null) !== match ($name) {
                    'business' => 'business_zone', 'advertising' => 'advertising_zone', 'fullscreen' => 'fullscreen',
                }) {
                    $this->invalid($key);
                }
                $source = collect($payload['assets'] ?? [])->firstWhere('id', $live['creative']['media_asset_id']);
                if (($source['type'] ?? null) !== 'live_stream' || ! $this->visibleAsset($device, $source['id'])) {
                    $this->invalid($key);
                }
                $campaignName = $live['campaign']['name'] ?? null;
                if ($zone['source'] === 'live') {
                    if ($asset['id'] !== $source['id'] || (isset($zone['item_id']) && $zone['item_id'] !== 'creative-'.$zone['live_creative_id'])) {
                        $this->invalid($key);
                    }
                } elseif ($zone['source'] === 'live_fallback') {
                    $configured = $live['creative']['live_configuration']['fallback_media_id'] ?? null;
                    if (($asset['type'] ?? '') === 'live_stream'
                        || ((int) $configured !== (int) $asset['id']
                            && ! $this->normalItem($payload, 'advertising', $zone)
                            && ! $this->normalItem($payload, 'business', $zone))) {
                        $this->invalid($key);
                    }
                }
            }
            $resolved[$name] = $zone + ['media' => $this->media($asset), 'campaign_name' => $campaignName];
        }

        return $resolved;
    }

    private function manifest(Device $device, string $version): array
    {
        $manifest = $device->manifests()->where('version', $version)->first();
        if (! $manifest || ! in_array($manifest->status->value, ['current', 'superseded'], true)
            || ($device->current_manifest_version !== $version && $manifest->activated_at === null)) {
            $this->invalid('manifest_version');
        }

        return $manifest->payload;
    }

    private function normalItem(array $payload, string $name, array $zone): bool
    {
        $itemId = $zone['item_id'] ?? '';
        if ($name === 'business') {
            $playlists = array_merge([$payload['business_playlist'] ?? []], $payload['scheduled_playlists'] ?? []);
            foreach ($playlists as $playlist) {
                foreach ($playlist['items'] ?? [] as $item) {
                    if ('business-'.$item['id'] === $itemId && (int) $item['media_asset_id'] === (int) $zone['media_asset_id']) {
                        return true;
                    }
                }
            }
        } elseif ($name === 'advertising') {
            foreach ($payload['advertising_playlist']['campaigns'] ?? [] as $campaign) {
                foreach ($campaign['creatives'] ?? [] as $creative) {
                    if ('creative-'.$creative['creative_id'] === $itemId && (int) $creative['media_asset_id'] === (int) $zone['media_asset_id']
                        && empty($creative['live_configuration'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function liveCreative(array $payload, ?int $id): ?array
    {
        foreach ($payload['advertising_playlist']['campaigns'] ?? [] as $campaign) {
            foreach ($campaign['creatives'] ?? [] as $creative) {
                if ((int) $creative['creative_id'] === $id && ! empty($creative['live_configuration'])) {
                    return ['creative' => $creative, 'campaign' => $campaign];
                }
            }
        }

        return null;
    }

    private function quick(Device $device, string $name, array $zone, string $key): array
    {
        $delivery = QuickPlayDevice::query()->where('device_id', $device->id)
            ->with(['quickPlay', 'command'])->find($zone['quick_play_device_id'] ?? 0);
        $play = $delivery?->quickPlay;
        $command = $delivery?->command;
        $asset = $command?->payload['media'] ?? null;
        if (! $delivery || $delivery->status->isTerminal() || ! $play || $play->trashed()
            || ! $play->expires_at || $play->expires_at->isPast()
            || ($play->business_id !== null && (int) $play->business_id !== (int) $device->business_id)
            || $delivery->display_mode->value !== $name
            || ! $command || (int) $command->device_id !== (int) $device->id || $command->command !== DeviceCommandType::QuickPlay
            || (int) $command->id !== (int) ($zone['command_id'] ?? 0)
            || ($command->expires_at !== null && $command->expires_at->isPast())
            || ! in_array($command->status->value, ['pending', 'sent'], true)
            || ! $asset || (int) $asset['id'] !== (int) $zone['media_asset_id']
            || (int) $play->media_asset_id !== (int) $asset['id'] || ! $this->visibleAsset($device, $asset['id'])) {
            $this->invalid($key);
        }

        return $this->media($asset);
    }

    private function visibleAsset(Device $device, int $id): bool
    {
        $asset = MediaAsset::query()->find($id);

        return $asset !== null && ($asset->owner_type !== (new Business)->getMorphClass()
            || ($device->business_id !== null && (int) $asset->owner_id === (int) $device->business_id));
    }

    private function media(array $asset): array
    {
        return array_intersect_key($asset, array_flip(['id', 'type', 'url', 'mime_type', 'live']));
    }

    private function invalid(string $key): never
    {
        throw ValidationException::withMessages([$key => 'La fuente reportada no pertenece a una entrega autorizada de esta pantalla.']);
    }
}
