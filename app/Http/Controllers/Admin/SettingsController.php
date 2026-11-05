<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Operations\Actions\RecordAudit;
use App\Domain\Operations\Models\SystemSetting;
use App\Domain\Users\Enums\PermissionEnum;
use App\Domain\Users\Models\Permission;
use App\Domain\Users\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('system.settings') || $request->user()->hasPermission('roles.manage'), 403);

        $settings = SystemSetting::query()->orderBy('group')->orderBy('key')->get()->map(fn (SystemSetting $setting) => [
            'id' => $setting->id,
            'key' => $setting->key,
            'value' => $setting->value['data'] ?? null,
            'group' => $setting->group,
            'label' => $setting->label,
        ]);

        $roles = Role::query()->with('permissions')->withCount('users')->orderBy('label')->get()
            ->map(fn (Role $role) => EntityPresenter::role($role));

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'roles' => $roles,
            'permissionGroups' => PermissionEnum::grouped(),
            'environment' => [
                'app_env' => config('app.env'),
                'app_version' => config('signage.version'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database' => config('database.default'),
                'queue' => config('queue.default'),
                'cache' => config('cache.default'),
                'broadcast' => config('broadcasting.default'),
                'filesystem' => config('filesystems.default'),
                'timezone' => config('app.timezone'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('system.settings'), 403);

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:120'],
            'settings.*.value' => ['nullable'],
            'settings.*.group' => ['nullable', 'string', 'max:60'],
        ]);

        foreach ($data['settings'] as $setting) {
            SystemSetting::put($setting['key'], $setting['value'] ?? null, $setting['group'] ?? 'general');
        }

        app(RecordAudit::class)->handle('settings.updated', null, [], ['keys' => collect($data['settings'])->pluck('key')->all()]);

        return back()->with('success', 'Configuración guardada.');
    }

    public function updateRolePermissions(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);

        if ($role->is_system && $role->name === 'super-admin') {
            return back()->with('error', 'El rol de super administrador siempre conserva todos los permisos.');
        }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $ids = Permission::query()->whereIn('name', $data['permissions'] ?? [])->pluck('id');

        $role->permissions()->sync($ids);

        app(RecordAudit::class)->handle('role.permissions.updated', $role, [], ['permissions' => $data['permissions'] ?? []]);

        return back()->with('success', 'Permisos del rol actualizados.');
    }
}
