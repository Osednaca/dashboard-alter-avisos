<?php

namespace App\Models\Concerns;

use App\Domain\Users\Enums\PermissionEnum;
use App\Domain\Users\Enums\RoleEnum;
use App\Domain\Users\Models\Permission;
use App\Domain\Users\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(RoleEnum|string ...$roles): bool
    {
        $names = array_map(
            fn (RoleEnum|string $role) => $role instanceof RoleEnum ? $role->value : $role,
            $roles,
        );

        return $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->intersect($names)->isNotEmpty()
            : $this->roles()->whereIn('name', $names)->exists();
    }

    public function hasPermission(PermissionEnum|string ...$permissions): bool
    {
        $names = array_map(
            fn (PermissionEnum|string $permission) => $permission instanceof PermissionEnum ? $permission->value : $permission,
            $permissions,
        );

        return count(array_intersect($names, $this->permissionNames())) > 0;
    }

    /**
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $this->roles()->select('roles.id')))
            ->pluck('name')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        return $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->all()
            : $this->roles()->pluck('name')->all();
    }

    /**
     * @param  array<int, int|string>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $ids = Role::query()
            ->whereIn('name', $roles)
            ->orWhereIn('id', $roles)
            ->pluck('id')
            ->all();

        $this->roles()->sync($ids);
        $this->unsetRelation('roles');
    }
}
