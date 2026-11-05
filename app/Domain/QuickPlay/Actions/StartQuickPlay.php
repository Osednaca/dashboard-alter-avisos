<?php

namespace App\Domain\QuickPlay\Actions;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Actions\IssueDeviceCommand;
use App\Domain\Devices\Enums\DeviceCommandType;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\ProcessingStatus;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use App\Domain\QuickPlay\Events\QuickPlayStatusUpdated;
use App\Domain\QuickPlay\Models\QuickPlay;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartQuickPlay
{
    public function __construct(
        protected ResolveQuickPlayTargets $targets,
        protected IssueDeviceCommand $commands,
    ) {}

    /**
     * Create a quick play and dispatch the QUICK_PLAY command to every reachable
     * screen. No campaign, playlist or layout is created or mutated: the device
     * overlays the content and restores its previous state when done.
     *
     * @param  array{device_ids?: array<int, int>, business_ids?: array<int, int>, location_ids?: array<int, int>}  $targetIds
     */
    public function handle(
        User $user,
        MediaAsset $media,
        QuickPlayDisplayMode $displayMode,
        QuickPlayScope $scope,
        ?int $duration,
        array $targetIds = [],
        ?Business $business = null,
    ): QuickPlay {
        if ($media->type === MediaType::LiveStream) {
            throw ValidationException::withMessages(['media_asset_id' => 'Programa el directo desde una campaña.']);
        }
        if ($business !== null && $displayMode !== QuickPlayDisplayMode::Business) {
            throw ValidationException::withMessages(['display_mode' => 'El Instant Play del negocio solo puede reproducirse en la zona del negocio.']);
        }
        if ($business !== null && $scope !== QuickPlayScope::Devices) {
            throw ValidationException::withMessages(['scope' => 'Selecciona las pantallas de tu negocio.']);
        }
        if ($media->processing_status !== ProcessingStatus::Ready || ($business !== null &&
            ($media->owner_type !== $business->getMorphClass() || (int) $media->owner_id !== $business->id))) {
            throw ValidationException::withMessages(['media_asset_id' => 'Selecciona un archivo disponible de tu biblioteca.']);
        }
        $devices = $this->targets->devices($scope, $targetIds, $business?->id);

        if ($devices->isEmpty()) {
            throw ValidationException::withMessages([
                'targets' => 'La selección no coincide con ninguna pantalla activa.',
            ]);
        }

        $duration = $duration ?: $media->duration;
        $expiresAt = now()->addMinutes(max(30, (int) ceil(((int) ($duration ?? 60)) / 60) + 15));

        $quickPlay = DB::transaction(function () use ($user, $media, $displayMode, $scope, $duration, $expiresAt, $devices, $business) {
            $quickPlay = QuickPlay::query()->create([
                'user_id' => $user->id,
                'business_id' => $business?->id,
                'media_asset_id' => $media->id,
                'display_mode' => $displayMode,
                'scope' => $scope,
                'duration' => $duration,
                'status' => 'sending',
                'expires_at' => $expiresAt,
                'metadata' => [
                    'media_type' => $media->type->value,
                    'media_filename' => $media->filename,
                ],
            ]);

            foreach ($devices as $device) {
                $row = $quickPlay->devices()->create([
                    'device_id' => $device->id,
                    'status' => QuickPlayDeviceStatus::Pending,
                    'display_mode' => $displayMode,
                    'duration' => $duration,
                    'previous_layout_id' => $device->current_layout_id,
                    'previous_playlist_id' => $device->current_playlist_id,
                ]);

                if (! $device->isReachable()) {
                    $row->forceFill([
                        'status' => QuickPlayDeviceStatus::Failed,
                        'error' => 'La pantalla está desconectada; el contenido no pudo entregarse.',
                        'completed_at' => now(),
                    ])->save();

                    continue;
                }

                $command = $this->commands->handle($device, DeviceCommandType::QuickPlay, [
                    'quick_play_id' => $quickPlay->id,
                    'quick_play_device_id' => $row->id,
                    'media' => [
                        'id' => $media->id,
                        'type' => $media->type->value,
                        'url' => $media->url,
                        'mime_type' => $media->mime_type,
                        'checksum' => $media->checksum,
                        'duration' => $media->duration,
                    ],
                    'display_mode' => $displayMode->value,
                    'duration' => $duration,
                    'restore_previous' => true,
                    'expires_at' => $expiresAt->toIso8601ZuluString(),
                ]);

                $row->forceFill([
                    'command_id' => $command->id,
                    'sent_at' => now(),
                ])->save();
            }

            return $quickPlay->refreshProgress();
        });

        QuickPlayStatusUpdated::dispatch($quickPlay->fresh(['mediaAsset']));

        return $quickPlay;
    }
}
