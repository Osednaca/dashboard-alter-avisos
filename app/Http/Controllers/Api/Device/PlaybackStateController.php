<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Devices\Services\DevicePlaybackStateService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DevicePlaybackStateRequest;
use Illuminate\Http\JsonResponse;

class PlaybackStateController extends Controller
{
    public function store(DevicePlaybackStateRequest $request, DevicePlaybackStateService $states): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'accepted' => $states->accept($request->user(), $request->validated()),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
