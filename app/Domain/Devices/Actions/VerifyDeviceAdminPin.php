<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\GlobalScreenPin;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class VerifyDeviceAdminPin
{
    public function handle(Device $device, string $pin): void
    {
        abort_if($device->status === DeviceStatus::Disabled, 403);
        $setting = GlobalScreenPin::current();
        if ($setting === null) {
            throw new HttpResponseException(response()->json(['message' => 'El administrador debe configurar el PIN global en el dashboard.'], 409));
        }
        // Shared buckets cannot be reset by unlocking another screen. The broad
        // ceiling bounds distributed guesses without a five-attempt global lockout.
        $prefix = 'global-screen-pin:'.hash('sha256', $setting->pin_hash).':';
        $limits = [
            $prefix.'device:'.$device->getKey() => 5,
            $prefix.'ip:'.hash('sha256', request()->ip() ?? 'unknown') => 30,
            $prefix.'all' => 300,
        ];
        foreach ($limits as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                throw new HttpResponseException(response()->json(['message' => 'Demasiados intentos. Espera cinco minutos.'], 429)
                    ->header('Retry-After', (string) RateLimiter::availableIn($key)));
            }
        }
        if (! Hash::check($pin, $setting->pin_hash)) {
            foreach (array_keys($limits) as $key) {
                RateLimiter::hit($key, 300);
            }
            throw new HttpResponseException(response()->json(['message' => 'PIN incorrecto.'], 403));
        }
        RateLimiter::clear(array_key_first($limits));
    }
}
