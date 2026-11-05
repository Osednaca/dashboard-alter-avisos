<?php

namespace App\Policies;

use App\Domain\Advertisers\Models\Advertiser;
use App\Models\User;

class AdvertiserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('advertisers.view');
    }

    public function view(User $user, Advertiser $advertiser): bool
    {
        return $user->hasPermission('advertisers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('advertisers.manage');
    }

    public function update(User $user, Advertiser $advertiser): bool
    {
        return $user->hasPermission('advertisers.manage');
    }

    public function delete(User $user, Advertiser $advertiser): bool
    {
        return $user->hasPermission('advertisers.manage');
    }
}
