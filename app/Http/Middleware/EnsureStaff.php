<?php

namespace App\Http\Middleware;

use App\Domain\Users\Enums\RoleEnum;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    /**
     * Platform-level permissions. Business users hold none of these, so they are
     * kept out of the administrative area entirely while staff pass through.
     */
    public const PERMISSIONS = [
        'businesses.view',
        'locations.view',
        'devices.view',
        'campaigns.view',
        'advertisers.view',
        'creatives.view',
        'analytics.view',
        'alerts.manage',
        'audit.view',
        'users.manage',
        'roles.manage',
        'system.settings',
        'quick_play.view',
    ];

    public static function allows(User $user): bool
    {
        $roles = $user->roleNames();
        if (count($roles) === 1 && $roles[0] === RoleEnum::BusinessUser->value) {
            return false;
        }

        return $user->hasPermission(...self::PERMISSIONS);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! self::allows($user)) {
            abort(403, 'Acceso restringido al personal de la plataforma.');
        }

        return $next($request);
    }
}
