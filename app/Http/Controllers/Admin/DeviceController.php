<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Actions\AssignActivation;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Actions\IssueDeviceCommand;
use App\Domain\Devices\Enums\ActivationStatus;
use App\Domain\Devices\Enums\DeviceCommandType;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Devices\Models\GlobalScreenPin;
use App\Domain\Media\Models\Layout;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\DeviceCommandRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Device::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
            'business_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'city' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string'],
            'sort' => ['nullable', 'in:name,last_seen_at,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $devices = Device::query()
            ->search($request->string('search')->toString())
            ->withStatus($request->string('status')->toString() ?: null)
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', $request->integer('business_id')))
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', $request->integer('location_id')))
            ->when($request->filled('city'), fn ($q) => $q->whereHas('location', fn ($qq) => $qq->where('city', $request->string('city'))))
            ->when($request->filled('app_version'), fn ($q) => $q->where('app_version', $request->string('app_version')))
            ->with(['business', 'location', 'currentLayout'])
            ->orderBy($request->string('sort', 'name')->toString(), $request->string('direction', 'asc')->toString())
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Device $device) => EntityPresenter::device($device));

        return Inertia::render('Admin/Devices/Index', [
            'devices' => $devices,
            'filters' => $request->only('search', 'status', 'business_id', 'location_id', 'city', 'app_version', 'sort', 'direction'),
            'options' => $this->filterOptions(),
        ]);
    }

    public function show(Device $device): Response
    {
        $this->authorize('view', $device);

        $device->load(['business', 'location', 'currentLayout', 'currentPlaylist']);

        $commands = $device->commands()->with('creator')->latest()->limit(20)->get()
            ->map(fn ($command) => EntityPresenter::deviceCommand($command));

        $manifests = $device->manifests()->latest()->limit(10)->get()->map(fn ($manifest) => [
            'id' => $manifest->id,
            'version' => $manifest->version,
            'status' => EntityPresenter::enum($manifest->status),
            'checksum' => $manifest->checksum,
            'generated_at' => $manifest->generated_at?->toIso8601String(),
            'activated_at' => $manifest->activated_at?->toIso8601String(),
        ]);

        $playback = $device->playbackEvents()
            ->with(['mediaAsset', 'campaign'])
            ->latest('started_at')
            ->limit(15)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'media' => $event->mediaAsset?->filename,
                'campaign' => $event->campaign?->name,
                'started_at' => $event->started_at?->toIso8601String(),
                'duration_played' => $event->duration_played,
                'completed' => $event->completed,
                'error_code' => $event->error_code,
            ]);

        $heartbeats = $device->heartbeats()->latest('recorded_at')->limit(24)->get()->map(fn ($h) => [
            'recorded_at' => $h->recorded_at?->toIso8601String(),
            'available_storage' => $h->available_storage,
            'player_status' => $h->player_status,
            'network_status' => $h->network_status,
            'diagnostics' => $h->diagnostics,
        ]);

        $failures = $device->playbackEvents()->whereNotNull('error_code')
            ->with(['mediaAsset', 'campaign'])->latest('started_at')->limit(20)->get()
            ->map(fn ($event) => [
                'id' => $event->id, 'media' => $event->mediaAsset?->filename,
                'campaign' => $event->campaign?->name, 'started_at' => $event->started_at?->toIso8601String(),
                'duration_played' => $event->duration_played, 'completed' => $event->completed,
                'error_code' => $event->error_code,
            ]);

        return Inertia::render('Admin/Devices/Show', [
            'device' => EntityPresenter::device($device),
            'adminPinConfigured' => GlobalScreenPin::current() !== null,
            'canManagePin' => request()->user()->hasPermission('devices.manage'),
            'commands' => $commands,
            'manifests' => $manifests,
            'playback' => $playback,
            'heartbeats' => $heartbeats,
            'failures' => $failures,
            'commandTypes' => collect(DeviceCommandType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'requires_confirmation' => $type->requiresConfirmation(),
            ])->all(),
            'options' => [
                'layouts' => Layout::query()->get()->map(fn ($layout) => ['id' => $layout->id, 'name' => $layout->name, 'ratio' => $layout->ratioLabel()])->all(),
            ],
        ]);
    }

    public function command(DeviceCommandRequest $request, Device $device, IssueDeviceCommand $issue): RedirectResponse
    {
        $this->authorize('command', $device);

        $type = DeviceCommandType::from($request->string('command')->toString());

        $command = $issue->handle($device, $type, $request->input('payload', []));

        app(RecordAudit::class)->handle('device.command.issued', $device, [], [
            'command' => $type->value,
            'command_id' => $command->id,
        ]);

        return back()->with('success', "Comando «{$type->label()}» enviado a {$device->name}.");
    }

    public function sync(Device $device, BuildDeviceManifest $builder): RedirectResponse
    {
        $this->authorize('update', $device);

        $manifest = $builder->handle($device);

        app(RecordAudit::class)->handle('device.sync.queued', $device, [], ['version' => $manifest->version]);

        return back()->with('success', "Manifiesto {$manifest->version} generado para {$device->name}.");
    }

    public function toggleStatus(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $newStatus = $device->status === DeviceStatus::Disabled
            ? DeviceStatus::Offline
            : DeviceStatus::Disabled;

        $old = $device->status;
        $changes = ['status' => $newStatus];
        if ($newStatus === DeviceStatus::Offline) {
            // Campaign targeting excludes disabled screens. Rebuild on the next
            // automatic poll to include all edits made while this screen was off.
            $changes['manifest_dirty'] = true;
        }
        $device->forceFill($changes)->save();

        app(RecordAudit::class)->handle('device.status.changed', $device, ['status' => $old->value], ['status' => $newStatus->value]);

        return back()->with('success', $newStatus === DeviceStatus::Disabled
            ? 'Pantalla deshabilitada.'
            : 'Pantalla habilitada.');
    }

    public function revokeToken(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $device->revokeToken();

        app(RecordAudit::class)->handle('device.token.revoked', $device);

        return back()->with('success', 'Acceso de la pantalla revocado. Deberá activarse de nuevo.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->authorize('delete', $device);

        DB::transaction(function () use ($device) {
            // Claimed codes support activation retries; revoke them before the
            // foreign key is cleared so a deleted screen cannot recreate itself.
            DeviceActivation::query()->where('device_id', $device->id)
                ->update(['status' => ActivationStatus::Revoked->value]);
            $device->delete();
        });

        return redirect()->route('devices.index')->with('success', 'Pantalla eliminada.');
    }

    public function activations(): Response
    {
        $this->authorize('viewAny', Device::class);

        $activations = DeviceActivation::query()
            ->with(['business', 'location'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($activation) => EntityPresenter::activation($activation));

        return Inertia::render('Admin/Devices/Activations', [
            'activations' => $activations,
            'businesses' => Business::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($business) => [
                    'id' => $business->id,
                    'name' => $business->name,
                    'locations' => $business->locations()->get(['id', 'name'])->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
                ]),
        ]);
    }

    public function assignActivation(Request $request, DeviceActivation $activation): RedirectResponse
    {
        $this->authorize('update', new Device);

        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'device_name' => ['nullable', 'string', 'max:160'],
        ]);

        app(AssignActivation::class)->handle(
            $activation,
            $data['business_id'],
            $data['location_id'] ?? null,
            $data['device_name'] ?? null,
        );

        app(RecordAudit::class)->handle('device.activation.assigned', $activation, [], $data);

        return back()->with('success', 'Código asignado. La pantalla ya puede confirmar su activación.');
    }

    public function revokeActivation(DeviceActivation $activation): RedirectResponse
    {
        $this->authorize('update', new Device);

        app(AssignActivation::class)->revoke($activation);

        app(RecordAudit::class)->handle('device.activation.revoked', $activation);

        return back()->with('success', 'Código de activación revocado.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterOptions(): array
    {
        return [
            'statuses' => collect(DeviceStatus::cases())->map(fn ($status) => ['value' => $status->value, 'label' => $status->label()])->all(),
            'businesses' => DB::table('businesses')->orderBy('name')->get(['id', 'name']),
            'cities' => DB::table('locations')->distinct()->orderBy('city')->pluck('city'),
            'appVersions' => Device::query()->whereNotNull('app_version')->distinct()->orderBy('app_version')->pluck('app_version'),
        ];
    }
}
