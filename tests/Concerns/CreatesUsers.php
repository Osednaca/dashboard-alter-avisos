<?php

namespace Tests\Concerns;

use App\Domain\Businesses\Models\Business;
use App\Domain\Users\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

trait CreatesUsers
{
    protected function seedAccessControl(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function userWithRole(RoleEnum $role, array $attributes = []): User
    {
        $this->seedAccessControl();

        $user = User::factory()->create($attributes);
        $user->syncRoles([$role->value]);

        return $user->fresh();
    }

    protected function superAdmin(array $attributes = []): User
    {
        return $this->userWithRole(RoleEnum::SuperAdmin, $attributes);
    }

    protected function businessUser(Business $business, array $attributes = []): User
    {
        $this->seedAccessControl();

        $user = User::factory()->create($attributes);
        $user->syncRoles([RoleEnum::BusinessUser->value]);

        $business->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'is_primary' => true],
        ]);

        return $user->fresh();
    }
}
