<?php

namespace App\Support;

use App\Http\Middleware\EnsureStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoginDestination
{
    public function resolve(Request $request, string $home): string
    {
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || preg_match('/[\x00-\x20\x7f\\\\]/', $intended)) {
            return $home;
        }

        $url = parse_url($intended);

        if ($url === false || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])) {
            return $home;
        }

        if (isset($url['host'])) {
            $scheme = strtolower($url['scheme'] ?? '');
            $port = $url['port'] ?? ($scheme === 'https' ? 443 : 80);

            if ($scheme !== $request->getScheme()
                || strtolower($url['host']) !== strtolower($request->getHost())
                || $port !== $request->getPort()) {
                return $home;
            }
        } elseif (isset($url['scheme']) || ! str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            return $home;
        }

        $path = $url['path'] ?? '/';

        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (HttpException) {
            return $home;
        }

        // Model-specific authorization belongs to the destination controller.
        // After an identity change, retain only navigation without model IDs.
        if (! $route->getName() || $route->parameters() !== []) {
            return $home;
        }

        $middleware = $route->gatherMiddleware();
        $user = $request->user();

        if (in_array('staff', $middleware, true)) {
            if (! $user->hasPermission(...EnsureStaff::PERMISSIONS)) {
                return $home;
            }
        } elseif (in_array('business.context', $middleware, true)) {
            if (! $user->businesses()->exists()) {
                return $home;
            }
        } else {
            return $home;
        }

        foreach ($middleware as $guard) {
            if (str_starts_with($guard, 'permission:')) {
                if (! $user->hasPermission(...explode(',', substr($guard, strlen('permission:'))))) {
                    return $home;
                }
            } elseif (! in_array($guard, ['web', 'auth', 'staff', 'business.context'], true)) {
                return $home;
            }
        }

        return $path.(isset($url['query']) ? '?'.$url['query'] : '');
    }
}
