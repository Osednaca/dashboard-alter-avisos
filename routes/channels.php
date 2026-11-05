<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| The admin dashboard subscribes to network-wide device and campaign events.
| Access is gated by the user's permissions on the server.
*/

Broadcast::channel('network', fn (User $user) => $user->hasPermission('devices.view') || $user->hasPermission('analytics.view'));

Broadcast::channel('devices.{deviceId}', fn (User $user, int $deviceId) => $user->hasPermission('devices.view'));

Broadcast::channel('campaigns', fn (User $user) => $user->hasPermission('campaigns.view'));

Broadcast::channel('quick-plays.{quickPlayId}', fn (User $user, int $quickPlayId) => $user->hasPermission('quick_play.view'));
