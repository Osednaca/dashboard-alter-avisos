<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Campaigns\Actions\RefreshCampaignDates;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManifestController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        app(RefreshCampaignDates::class)->handle();
        $device->refresh();
        if ($device->manifest_dirty) {
            app(BuildDeviceManifest::class)->handle($device);
            $device->refresh();
        }

        $manifest = $device->manifests()->where('status', 'pending')->latest()->first()
            ?? $device->manifests()->where('status', 'current')->latest()->first();

        if (! $manifest) {
            return response()->json([
                'manifest' => null,
                'current_version' => $device->current_manifest_version,
            ]);
        }

        return response()->json([
            'manifest' => [
                'version' => $manifest->version,
                'status' => $manifest->status->value,
                'checksum' => $manifest->checksum,
                'generated_at' => $manifest->generated_at?->toIso8601String(),
                'payload' => $manifest->payload,
            ],
            'current_version' => $device->current_manifest_version,
        ]);
    }
}
