<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Devices\Actions\ConfirmDeviceActivation;
use App\Domain\Devices\Actions\RequestDeviceActivation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivationController extends Controller
{
    public function request(Request $request, RequestDeviceActivation $action): JsonResponse
    {
        $data = $request->validate([
            'device_uuid' => ['nullable', 'uuid'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $action->handle(
            $data['device_uuid'] ?? null,
            $data['app_version'] ?? null,
            $request->ip(),
        );

        return response()->json([
            'activation_code' => $result['code'],
            'status' => $result['status'],
            'expires_at' => $result['expires_at'],
            'poll_interval_seconds' => 15,
        ]);
    }

    public function confirm(Request $request, ConfirmDeviceActivation $action): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'device_uuid' => ['nullable', 'uuid'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:160'],
        ]);

        $result = $action->handle(
            strtoupper($data['code']),
            $data['device_uuid'] ?? null,
            $data['app_version'] ?? null,
            $request->ip(),
            $data['device_name'] ?? null,
        );

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'device' => [
                'id' => $result['device']->id,
                'uuid' => $result['device']->uuid,
                'name' => $result['device']->name,
                'layout_id' => $result['device']->current_layout_id,
            ],
        ], 201);
    }
}
