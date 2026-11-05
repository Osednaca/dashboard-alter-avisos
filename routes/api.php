<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Android TV Device API (v1)
|--------------------------------------------------------------------------
| These endpoints are consumed by the Android TV APK. They never use the
| session guard; devices authenticate with an issued bearer token via the
| `device.token` middleware. See routes/api/device.php for the routes.
*/

Route::prefix('v1/device')->group(base_path('routes/api/device.php'));

Route::get('/v1/ping', fn () => response()->json([
    'service' => 'signage-device-api',
    'version' => config('signage.version'),
    'time' => now()->toIso8601String(),
]));
