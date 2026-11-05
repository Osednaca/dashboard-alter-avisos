<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Devices\Enums\ActivationStatus;
use App\Domain\Devices\Models\DeviceActivation;
use Illuminate\Support\Str;

class RequestDeviceActivation
{
    /**
     * Return an existing pending code for the device UUID or issue a new one.
     *
     * @return array{activation: DeviceActivation, code: string, expires_at: ?string, status: string}
     */
    public function handle(?string $deviceUuid, ?string $appVersion, ?string $ip): array
    {
        $deviceUuid = $deviceUuid ?: (string) Str::uuid();

        $activation = DeviceActivation::query()
            ->where('device_uuid', $deviceUuid)
            ->where('status', ActivationStatus::Pending->value)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();

        if (! $activation) {
            $activation = DeviceActivation::query()->create([
                'code' => $this->uniqueCode(),
                'device_uuid' => $deviceUuid,
                'status' => ActivationStatus::Pending,
                'app_version' => $appVersion,
                'ip_address' => $ip,
                'expires_at' => now()->addMinutes(config('signage.device.activation_ttl_minutes')),
            ]);
        }

        return [
            'activation' => $activation,
            'code' => $activation->code,
            // Older Android Instant parsers require UTC with a literal Z suffix.
            'expires_at' => $activation->expires_at?->toIso8601ZuluString(),
            'status' => $activation->business_id ? 'awaiting_confirmation' : 'awaiting_assignment',
        ];
    }

    protected function uniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (DeviceActivation::query()->where('code', $code)->exists());

        return $code;
    }
}
