<?php

namespace App\Http\Middleware;

use App\Domain\Devices\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    /**
     * Authenticate an Android TV device using its issued bearer token.
     *
     * Devices never use session authentication. Only the SHA-256 hash of the
     * issued token is stored, so a database leak cannot reveal live tokens.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-Device-Token');

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Token de dispositivo requerido.'], 401);
        }

        $device = Device::query()
            ->where('device_token_hash', hash('sha256', $token))
            ->first();

        if (! $device || $device->token_revoked_at !== null) {
            return response()->json(['message' => 'Token inválido o revocado.'], 401);
        }

        if ($device->token_expires_at !== null && $device->token_expires_at->isPast()) {
            return response()->json(['message' => 'Token expirado.'], 401);
        }

        $device->forceFill(['last_ip' => $request->ip()])->saveQuietly();

        $request->setUserResolver(fn () => $device);
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
