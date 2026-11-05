<?php

namespace App\Http\Middleware;

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

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission(...self::PERMISSIONS)) {
            abort(403, 'Acceso restringido al personal de la plataforma.');
        }

        return $next($request);
    }
}
