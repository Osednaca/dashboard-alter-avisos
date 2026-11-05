<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Devices\Actions\VerifyDeviceAdminPin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VerifyDeviceAdminPinRequest;
use Illuminate\Http\JsonResponse;

class AdminPinController extends Controller
{
    public function verify(VerifyDeviceAdminPinRequest $request, VerifyDeviceAdminPin $verify): JsonResponse
    {
        $verify->handle($request->user(), $request->validated('pin'));

        return response()->json(['authorized' => true]);
    }
}
