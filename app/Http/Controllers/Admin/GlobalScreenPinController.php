<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Devices\Models\GlobalScreenPin;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeviceAdminPinRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class GlobalScreenPinController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Admin/Devices/GlobalPin', [
            'configured' => GlobalScreenPin::current() !== null,
        ]);
    }

    public function update(DeviceAdminPinRequest $request): RedirectResponse
    {
        $setting = GlobalScreenPin::current() ?? new GlobalScreenPin;
        $setting->forceFill(['id' => 1, 'pin_hash' => Hash::make($request->validated('pin'))])->save();
        app(RecordAudit::class)->handle('screen.global_pin.updated');

        return back()->with('success', 'PIN actualizado para todas las pantallas de todos los negocios.');
    }
}
