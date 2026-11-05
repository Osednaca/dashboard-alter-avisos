<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Businesses\Models\Business;
use App\Domain\Operations\Actions\RecordAudit;
use App\Domain\Users\Enums\RoleEnum;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
        ]);

        $users = User::query()
            ->with(['roles', 'businesses'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($qq) => $qq
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('email', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($qq) => $qq->where('name', $request->string('role'))))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => EntityPresenter::user($user));

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only('search', 'status', 'role'),
            'options' => [
                'statuses' => collect(UserStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
                'roles' => Role::query()->orderBy('label')->get(['name', 'label']),
                'businesses' => Business::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($business) => ['id' => $business->id, 'name' => $business->name])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'job_title' => $data['job_title'] ?? null,
            'status' => $data['status'],
            'password' => $data['password'],
        ]);

        $user->syncRoles($data['roles']);
        $this->syncBusinessMembership($user, $data);

        app(RecordAudit::class)->handle('user.created', $user, [], [
            'email' => $user->email,
            'roles' => $data['roles'],
            'business_id' => $data['business_id'] ?? null,
        ]);

        return back()->with('success', 'Usuario creado.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'job_title' => $data['job_title'] ?? null,
            'status' => $data['status'],
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => $data['password']]);
        }

        $oldRoles = $user->roleNames();
        $user->syncRoles($data['roles']);
        $this->syncBusinessMembership($user, $data);

        app(RecordAudit::class)->handle('user.updated', $user, ['roles' => $oldRoles], [
            'roles' => $data['roles'],
            'business_id' => $data['business_id'] ?? null,
        ]);

        return back()->with('success', 'Usuario actualizado.');
    }

    /**
     * Business users belong to exactly one business chosen by the administrator;
     * staff roles keep no business membership.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncBusinessMembership(User $user, array $data): void
    {
        $isBusinessUser = in_array(RoleEnum::BusinessUser->value, $data['roles'] ?? [], true);
        $businessId = $isBusinessUser ? ($data['business_id'] ?? null) : null;

        if ($businessId) {
            $user->businesses()->sync([
                $businessId => ['role' => 'staff', 'is_primary' => true],
            ]);

            return;
        }

        $user->businesses()->detach();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->hasRole(RoleEnum::SuperAdmin) && User::query()->whereHas('roles', fn ($q) => $q->where('name', RoleEnum::SuperAdmin->value))->count() <= 1) {
            return back()->with('error', 'No puedes eliminar al último super administrador.');
        }

        $user->delete();

        app(RecordAudit::class)->handle('user.deleted', $user, ['email' => $user->email]);

        return back()->with('success', 'Usuario eliminado.');
    }
}
