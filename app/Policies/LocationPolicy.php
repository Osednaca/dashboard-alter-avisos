<?php

namespace App\Policies;

use App\Domain\Locations\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('locations.view');
    }

    public function view(User $user, Location $location): bool
    {
        return $user->hasPermission('locations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('locations.manage');
    }

    public function update(User $user, Location $location): bool
    {
        return $user->hasPermission('locations.manage');
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->hasPermission('locations.manage');
    }
}
