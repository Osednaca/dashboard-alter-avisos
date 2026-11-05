<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Operations\Actions\RecordAudit;
use App\Domain\Operations\Enums\AlertSeverity;
use App\Domain\Operations\Enums\AlertStatus;
use App\Domain\Operations\Enums\AlertType;
use App\Domain\Operations\Models\Alert;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Alert::class);

        $request->validate([
            'status' => ['nullable', 'string'],
            'severity' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        $alerts = Alert::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest('triggered_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Alert $alert) => EntityPresenter::alert($alert));

        return Inertia::render('Admin/Alerts/Index', [
            'alerts' => $alerts,
            'filters' => $request->only('status', 'severity', 'type'),
            'options' => [
                'statuses' => collect(AlertStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
                'severities' => collect(AlertSeverity::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
                'types' => collect(AlertType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all(),
            ],
        ]);
    }

    public function acknowledge(Request $request, Alert $alert): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('alerts.manage'), 403);

        $alert->forceFill(['status' => AlertStatus::Acknowledged, 'acknowledged_at' => now()])->save();

        app(RecordAudit::class)->handle('alert.acknowledged', $alert);

        return back()->with('success', 'Alerta reconocida.');
    }

    public function resolve(Request $request, Alert $alert): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('alerts.manage'), 403);

        $alert->forceFill(['status' => AlertStatus::Resolved, 'resolved_at' => now()])->save();

        app(RecordAudit::class)->handle('alert.resolved', $alert);

        return back()->with('success', 'Alerta resuelta.');
    }
}
