<?php

namespace App\Http\Controllers\Business;

use App\Domain\Locations\Enums\LocationStatus;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Business\BusinessLocationRequest;
use App\Http\Requests\Business\UpdateBusinessSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use AuthorizesBusiness;

    public function index(): Response
    {
        $business = $this->business();

        return Inertia::render('Business/Settings/Index', [
            'business' => EntityPresenter::businessSelf($business),
            'locations' => $business->locations()
                ->withCount('devices')
                ->orderBy('name')
                ->get()
                ->map(fn ($location) => [
                    ...EntityPresenter::location($location),
                    'devices_count' => $location->devices_count,
                ])
                ->values()
                ->all(),
            'users' => $business->users()
                ->get()
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'job_title' => $user->job_title,
                    'role' => $user->pivot->role,
                    'is_primary' => (bool) $user->pivot->is_primary,
                ])
                ->values()
                ->all(),
            'preferences' => [
                'audio_volume' => (int) data_get($business->metadata, 'audio_volume', 70),
                'notify_email' => (bool) data_get($business->metadata, 'notify_email', true),
                'notify_offline' => (bool) data_get($business->metadata, 'notify_offline', true),
            ],
            'locationStatuses' => collect(LocationStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->all(),
        ]);
    }

    public function update(UpdateBusinessSettingsRequest $request): RedirectResponse
    {
        $business = $this->business();
        $data = $request->validated();

        $attributes = [
            'name' => $data['name'],
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
        ];

        // Hidden settings are not part of the simplified form; omission must not reset them.
        if (array_key_exists('timezone', $data)) {
            $attributes['timezone'] = $data['timezone'];
        }

        foreach (['audio_volume', 'notify_email', 'notify_offline'] as $preference) {
            if (array_key_exists($preference, $data)) {
                $attributes['metadata'] ??= $business->metadata ?? [];
                $attributes['metadata'][$preference] = $preference === 'audio_volume'
                    ? ($data[$preference] ?? data_get($business->metadata, $preference, 70))
                    : (bool) $data[$preference];
            }
        }

        if ($request->hasFile('logo')) {
            $disk = config('signage.media_disk');
            $path = $request->file('logo')->store('business/'.$business->id.'/branding', $disk);

            if ($business->logo_path && $business->logo_path !== $path) {
                Storage::disk($disk)->delete($business->logo_path);
            }

            $attributes['logo_path'] = $path;
        }

        $business->update($attributes);

        app(RecordAudit::class)->handle('business.settings.updated', $business, [], [
            'business_id' => $business->id,
        ]);

        return back()->with('success', 'Configuración guardada.');
    }

    public function storeLocation(BusinessLocationRequest $request): RedirectResponse
    {
        $this->business()->locations()->create($request->validated());

        return back()->with('success', 'Ubicación creada.');
    }

    public function updateLocation(BusinessLocationRequest $request, int $location): RedirectResponse
    {
        $model = $this->business()->locations()->findOrFail($location);
        $model->update($request->validated());

        return back()->with('success', 'Ubicación actualizada.');
    }

    public function destroyLocation(int $location): RedirectResponse
    {
        $model = $this->business()->locations()->findOrFail($location);
        $model->delete();

        return back()->with('success', 'Ubicación eliminada.');
    }
}
