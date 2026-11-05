<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RecoverDeviceActivation
{
    public function enroll(int $deviceId, string $bearer, string $key): void
    {
        try {
            DB::transaction(function () use ($deviceId, $bearer, $key) {
                $device = Device::query()->lockForUpdate()->find($deviceId);
                $this->requireEligible($device);
                abort_unless(hash_equals($device->device_token_hash, hash('sha256', $bearer)), 401);
                abort_if($device->token_expires_at?->isPast(), 401);
                $hash = hash('sha256', $key);
                abort_if($device->recovery_key_hash !== null && ! hash_equals($device->recovery_key_hash, $hash), 409, 'Inscripción de recuperación en conflicto.');
                $device->forceFill(['recovery_key_hash' => $hash])->save();
            });
        } catch (UniqueConstraintViolationException) {
            // Two devices cannot enroll the same capability, even concurrently.
            abort(409, 'Inscripción de recuperación en conflicto.');
        }
    }

    /** @return array{device: Device, token: string} */
    public function recover(string $key, ?string $appVersion): array
    {
        return DB::transaction(function () use ($key, $appVersion) {
            $device = Device::query()->where('recovery_key_hash', hash('sha256', $key))->lockForUpdate()->first();
            $this->requireEligible($device);
            // Check revocation BEFORE issueToken(), which resets token_revoked_at.
            $token = $device->issueToken(preserveRecovery: true);
            if ($appVersion !== null) {
                $device->forceFill(['app_version' => $appVersion])->save();
            }

            return ['device' => $device, 'token' => $token];
        });
    }

    private function requireEligible(?Device $device): void
    {
        abort_unless($device !== null
            && in_array($device->status, [DeviceStatus::Online, DeviceStatus::Offline, DeviceStatus::Maintenance], true)
            && $device->token_revoked_at === null && $device->device_token_hash !== null, 404, 'Activación no recuperable.');
        $business = $device->business()->lockForUpdate()->first();
        abort_unless($business?->status === BusinessStatus::Active, 404, 'Activación no recuperable.');
    }
}
