<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\Layout;
use App\Domain\Operations\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateDeviceSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function update(UpdateDeviceSettingsRequest $request, BuildDeviceManifest $builder): JsonResponse
    {
        $manifest = DB::transaction(function () use ($request, $builder) {
            $device = Device::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_if($device->status === DeviceStatus::Disabled, 403);
            $settings = $request->validated('settings');
            $current = $device->currentLayout ?? Layout::query()->where('is_default', true)->first();
            $configuration = $current?->configuration ?? [];
            if (isset($settings['split'])) {
                $first = ! in_array($configuration['business_area'] ?? 'left', ['right', 'bottom'], true);
                $vertical = $settings['split'] === 'top_bottom';
                $configuration['split'] = $settings['split'];
                $configuration['business_area'] = $vertical ? ($first ? 'top' : 'bottom') : ($first ? 'left' : 'right');
                $configuration['advertising_area'] = $vertical ? ($first ? 'bottom' : 'top') : ($first ? 'right' : 'left');
            }
            if (isset($settings['audio_mode'])) {
                $configuration['audio_mode'] = $settings['audio_mode'];
            }
            $ratio = (int) ($settings['business_percentage'] ?? $current?->business_percentage ?? 70);
            $orientation = $settings['orientation'] ?? $current?->orientation?->value ?? 'landscape';
            if (isset($settings['rotation'])) {
                $configuration['rotation'] = (int) $settings['rotation'];
                $orientation = $configuration['rotation'] % 180 === 0 ? 'landscape' : 'portrait';
            } elseif (isset($settings['orientation'])) {
                // Older players still send only orientation. Reset the explicit rotation too.
                $configuration['rotation'] = $orientation === 'portrait' ? 90 : 0;
            }
            if (isset($settings['transition'])) {
                $configuration['transition'] = $settings['transition'];
            }
            // Layouts can be shared by many TVs. Never mutate another TV's layout.
            $layout = $current;
            if (! $current || $current->business_percentage !== $ratio || $current->orientation?->value !== $orientation || $current->configuration !== $configuration) {
                $layout = Layout::query()->create([
                    'name' => mb_substr('TV '.$device->name.' · '.(($configuration['split'] ?? '') === 'top_bottom' ? 'Superior / inferior' : 'Lateral').' · '.$ratio.'/'.(100 - $ratio).' · '.($orientation === 'portrait' ? 'Vertical' : 'Horizontal'), 0, 255),
                    'orientation' => $orientation,
                    'business_percentage' => $ratio,
                    'advertising_percentage' => 100 - $ratio,
                    'configuration' => $configuration,
                    'is_default' => false,
                ]);
            }
            $oldLayoutId = $device->current_layout_id;
            $device->forceFill(['current_layout_id' => $layout->id])->save();
            $manifest = $builder->handle($device);
            AuditLog::query()->create([
                'user_id' => null, 'action' => 'device.settings.updated',
                'entity_type' => Device::class, 'entity_id' => $device->id,
                'old_values' => ['current_layout_id' => $oldLayoutId],
                'new_values' => ['current_layout_id' => $layout->id, 'settings' => $settings, 'source' => 'tv'],
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);

            return $manifest;
        });

        return response()->json(['manifest' => [
            'version' => $manifest->version, 'status' => $manifest->status->value,
            'checksum' => $manifest->checksum, 'generated_at' => $manifest->generated_at?->toIso8601ZuluString(),
            'payload' => $manifest->payload,
        ]]);
    }
}
