<?php

namespace App\Policies;

use App\Domain\Media\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('creatives.view');
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermission('creatives.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('creatives.manage');
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermission('creatives.manage');
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermission('creatives.manage');
    }
}
