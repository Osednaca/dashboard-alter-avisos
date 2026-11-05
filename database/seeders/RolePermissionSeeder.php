<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\PermissionEnum;
use App\Domain\Users\Enums\RoleEnum;
use App\Domain\Users\Models\Permission;
use App\Domain\Users\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission->value],
                ['label' => $permission->label(), 'group' => $permission->group()],
            );
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleEnum->value],
                ['label' => $roleEnum->label(), 'is_system' => true],
            );

            $ids = Permission::query()
                ->whereIn('name', $roleEnum->permissionValues())
                ->pluck('id');

            $role->permissions()->sync($ids);
        }
    }
}
