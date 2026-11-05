<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Devices\Actions\RecoverDeviceActivation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecoveryController extends Controller
{
    public function enroll(Request $request, RecoverDeviceActivation $action): Response
    {
        $data = $request->validate(['recovery_key' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']]);
        $action->enroll($request->user()->id, $request->bearerToken() ?: $request->header('X-Device-Token'), $data['recovery_key']);

        return response()->noContent();
    }

    public function recover(Request $request, RecoverDeviceActivation $action): JsonResponse
    {
        $data = $request->validate([
            'recovery_key' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'device_uuid' => ['prohibited'],
        ]);
        $result = $action->recover($data['recovery_key'], $data['app_version'] ?? null);

        return response()->json([
            'token' => $result['token'], 'token_type' => 'Bearer',
            'device' => [
                'id' => $result['device']->id, 'uuid' => $result['device']->uuid,
                'name' => $result['device']->name, 'layout_id' => $result['device']->current_layout_id,
            ],
        ]);
    }
}
