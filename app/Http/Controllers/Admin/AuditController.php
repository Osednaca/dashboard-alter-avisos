<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Operations\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);

        $request->validate([
            'action' => ['nullable', 'string', 'max:120'],
            'entity_type' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->string('action').'%'))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', 'like', '%'.$request->string('entity_type').'%'))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($qq) => $qq
                ->where('action', 'like', '%'.$request->string('search').'%')
                ->orWhere('entity_type', 'like', '%'.$request->string('search').'%')))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log) => EntityPresenter::auditLog($log));

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only('action', 'entity_type', 'search'),
        ]);
    }
}
