<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessSwitchController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer'],
        ]);

        $belongs = $request->user()
            ->businesses()
            ->whereKey($data['business_id'])
            ->exists();

        abort_unless($belongs, 403, 'No tienes acceso a ese negocio.');

        $request->session()->put('business_id', (int) $data['business_id']);

        return redirect()
            ->route('business.dashboard')
            ->with('success', 'Negocio actualizado.');
    }
}
