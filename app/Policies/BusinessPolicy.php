<?php

namespace App\Policies;

use App\Domain\Businesses\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('businesses.view');
    }

    public function view(User $user, Business $business): bool
    {
        return $user->hasPermission('businesses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('businesses.create');
    }

    public function update(User $user, Business $business): bool
    {
        return $user->hasPermission('businesses.edit');
    }

    public function delete(User $user, Business $business): bool
    {
        return $user->hasPermission('businesses.delete');
    }
}
