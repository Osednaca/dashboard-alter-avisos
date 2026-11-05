<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\Services\DashboardService;
use App\Domain\Devices\Models\Device;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'device' => ['nullable', 'integer', 'exists:devices,id'],
        ]);

        $to = $request->date('to') ?? today();
        $from = $request->date('from') ?? today()->subDays(29);

        return Inertia::render('Admin/Dashboard', [
            'overview' => fn () => $this->dashboard->overview(Carbon::parse($from), Carbon::parse($to)),
            'screenPreview' => fn () => $this->dashboard->screenPreview($request->filled('device') ? $request->integer('device') : null),
            'previewDevices' => fn () => Device::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn ($device) => ['id' => $device->id, 'name' => $device->name])->all(),
        ]);
    }
}
