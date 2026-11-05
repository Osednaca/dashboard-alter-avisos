<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Devices\Enums\DeviceCommandStatus;
use App\Domain\Devices\Enums\DeviceCommandType;
use App\Domain\Devices\Events\DeviceCommandCompleted;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceCommand;
use App\Domain\QuickPlay\Actions\UpdateQuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Models\QuickPlayDevice;
use App\Http\Controllers\Controller;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CommandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        // Unsent commands first, then the least recently offered commands.
        $commands = $device->commands()->deliverable()->orderBy('sent_at')->oldest('id')->limit(20)->get();

        $payload = $commands->map(fn (DeviceCommand $command) => [
            'id' => $command->id,
            'command' => $command->command->value,
            'payload' => $this->devicePayload($command),
            'expires_at' => $command->expires_at?->toIso8601ZuluString(),
        ])->all();

        // A result may arrive while this poll is being prepared. Never turn
        // a completed/failed command back into a sent command in that race.
        $device->commands()->whereKey($commands->modelKeys())
            ->whereIn('status', [DeviceCommandStatus::Pending->value, DeviceCommandStatus::Sent->value])
            ->update([
                'status' => DeviceCommandStatus::Sent->value,
                'sent_at' => now(),
            ]);

        return response()->json(['commands' => $payload]);
    }

    private function devicePayload(DeviceCommand $command): ?array
    {
        $payload = $command->payload;

        if ($command->command === DeviceCommandType::QuickPlay && is_string($payload['expires_at'] ?? null)) {
            try {
                // Also normalize commands queued before the UTC-Z compatibility fix.
                $payload['expires_at'] = Carbon::parse($payload['expires_at'])->toIso8601ZuluString();
            } catch (InvalidFormatException) {
                // Let the device reject malformed data without blocking other commands.
            }
        }

        return $payload;
    }

    public function result(Request $request, DeviceCommand $command): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        if ($command->device_id !== $device->id) {
            return response()->json(['message' => 'Comando no encontrado.'], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'in:completed,failed'],
            'result' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        $command->forceFill([
            'status' => $data['status'] === 'completed' ? DeviceCommandStatus::Completed : DeviceCommandStatus::Failed,
            'result' => $data['result'] ?? null,
            'error' => $data['error'] ?? null,
            'executed_at' => now(),
        ])->save();

        DeviceCommandCompleted::dispatch($command);

        // A quick play is delivered as a device command; forward the final
        // result to the quick play ledger so the dashboard reflects it.
        if ($command->command === DeviceCommandType::QuickPlay) {
            $row = QuickPlayDevice::query()->where('command_id', $command->id)->first();

            if ($row) {
                app(UpdateQuickPlayDeviceStatus::class)->handle(
                    $row,
                    $data['status'] === 'completed' ? QuickPlayDeviceStatus::Completed : QuickPlayDeviceStatus::Failed,
                    $data['error'] ?? null,
                );
            }
        }

        return response()->json(['ok' => true]);
    }
}
