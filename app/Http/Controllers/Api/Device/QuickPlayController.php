<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Devices\Models\Device;
use App\Domain\QuickPlay\Actions\UpdateQuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Models\QuickPlayDevice;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuickPlayController extends Controller
{
    /**
     * Devices report the lifecycle of a quick play here: pending, downloading,
     * playing, completed or failed. This is intentionally separate from the
     * manifest pipeline because quick plays never create a campaign.
     */
    public function status(Request $request, UpdateQuickPlayDeviceStatus $update): JsonResponse
    {
        $data = $request->validate([
            'quick_play_device_id' => ['required', 'integer', 'exists:quick_play_devices,id'],
            'status' => ['required', Rule::enum(QuickPlayDeviceStatus::class)],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Device $device */
        $device = $request->user();

        $row = QuickPlayDevice::query()
            ->where('device_id', $device->id)
            ->findOrFail($data['quick_play_device_id']);

        $update->handle(
            $row,
            QuickPlayDeviceStatus::from($data['status']),
            $data['error'] ?? null,
        );

        return response()->json(['ok' => true]);
    }
}
