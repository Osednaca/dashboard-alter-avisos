<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Operations\Actions\RecordAudit;
use App\Domain\Operations\Services\AndroidUpdatePublisher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PublishAndroidUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AndroidUpdateController extends Controller
{
    public function index(Request $request, AndroidUpdatePublisher $publisher): Response
    {
        abort_unless($request->user()->hasPermission('system.settings'), 403);

        return Inertia::render('Admin/AndroidUpdates/Index', ['currentUpdate' => $publisher->current()]);
    }

    public function store(PublishAndroidUpdateRequest $request, AndroidUpdatePublisher $publisher, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validated();
        try {
            $result = $publisher->publish($request->file('apk'), $request->boolean('forceUpdate'), $data['changelog'] ?? '');
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['apk' => $exception instanceof \RuntimeException
                ? $exception->getMessage() : 'No se pudo publicar la actualización. Inténtelo nuevamente.']);
        }
        $audit->handle('android_update.published', null, $result['previous'] ?? [], $result['manifest']);

        return back()->with('success', 'Actualización Android publicada correctamente.');
    }
}
