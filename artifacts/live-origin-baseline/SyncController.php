<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Campaigns\Actions\RefreshCampaignDates;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Events\DeviceSyncCompleted;
use App\Domain\Devices\Events\DeviceSyncFailed;
use App\Domain\Devices\Models\Device;
use App\Domain\Operations\Actions\RaiseAlert;
use App\Domain\Operations\Enums\AlertSeverity;
use App\Domain\Operations\Enums\AlertType;
use App\Domain\Scheduling\Services\ResolveActivePlaylist;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * Lightweight poll for configuration changes when the device is online.
     * It also reconciles the active scheduled playlist, so a schedule change
     * takes effect on the next poll even when no manifest was pushed.
     */
    public function show(
        Request $request,
        ResolveActivePlaylist $activePlaylist,
        BuildDeviceManifest $builder,
    ): JsonResponse {
        /** @var Device $device */
        $device = $request->user();
        app(RefreshCampaignDates::class)->handle();
        $device->refresh();
        if ($device->manifest_dirty) {
            app(BuildDeviceManifest::class)->handle($device);
            $device->refresh();
        }
        $device->loadMissing(['business', 'location', 'currentPlaylist']);

        $resolved = $activePlaylist->forDevice($device);

        if ($resolved && (int) $device->current_playlist_id !== (int) $resolved->id) {
            $builder->handle($device);
            $device->refresh();
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'current_manifest_version' => $device->current_manifest_version,
            'pending_manifest_version' => $device->pending_manifest_version,
            'update_available' => $device->pending_manifest_version !== null,
            'layout_id' => $device->current_layout_id,
            'commands_pending' => $device->commands()->deliverable()->count(),
            'heartbeat_interval_seconds' => 60,
        ]);
    }

    /**
     * Device confirms it downloaded and activated a manifest version. Only then
     * does the previous manifest stop being used, so a failed download can never
     * break the live signage experience.
     */
    public function acknowledge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:40'],
            'success' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Device $device */
        $device = $request->user();

        $manifest = $device->manifests()->where('version', $data['version'])->first();

        if (! $manifest) {
            return response()->json(['message' => 'Versión de manifiesto desconocida.'], 404);
        }

        if (! $data['success']) {
            DB::transaction(function () use ($device, $manifest) {
                $locked = Device::query()->lockForUpdate()->findOrFail($device->id);
                if ($locked->current_manifest_version === $manifest->version) {
                    return;
                }
                $manifest->forceFill(['status' => 'failed'])->save();
                if ($locked->pending_manifest_version === $manifest->version) {
                    $locked->forceFill(['manifest_dirty' => true])->save();
                }
            });

            DeviceSyncFailed::dispatch($device, $data['reason'] ?? 'Error de descarga');

            app(RaiseAlert::class)->handle(
                AlertType::SynchronizationFailed,
                'Sincronización fallida',
                "La pantalla «{$device->name}» no pudo activar el manifiesto {$data['version']}.",
                AlertSeverity::Warning,
                $device,
            );

            return response()->json(['ok' => false]);
        }

        DB::transaction(function () use ($device, $manifest, $data) {
            $locked = Device::query()->lockForUpdate()->findOrFail($device->id);
            // An older download can finish after a newer settings change is published.
            // A late ACK must never roll back the active version or erase that update.
            if ($locked->current_manifest_version !== null && (int) $data['version'] < (int) $locked->current_manifest_version) {
                return;
            }
            $locked->manifests()
                ->where('status', 'current')
                ->where('id', '!=', $manifest->getKey())
                ->update(['status' => 'superseded']);

            $manifest->forceFill([
                'status' => 'current',
                'activated_at' => $manifest->activated_at ?? now(),
            ])->save();

            $locked->forceFill([
                'current_manifest_version' => $data['version'],
                'pending_manifest_version' => $locked->pending_manifest_version === $data['version'] ? null : $locked->pending_manifest_version,
                'last_sync_at' => now(),
            ])->save();
        });

        DeviceSyncCompleted::dispatch($device, $data['version']);

        app(RaiseAlert::class)
            ->resolve(AlertType::SynchronizationFailed, $device);

        return response()->json(['ok' => true]);
    }
}
