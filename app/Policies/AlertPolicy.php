<?php

namespace App\Policies;

use App\Domain\Operations\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('alerts.manage') || $user->hasPermission('devices.view');
    }

    public function manage(User $user, ?Alert $alert = null): bool
    {
        return $user->hasPermission('alerts.manage');
    }
}
