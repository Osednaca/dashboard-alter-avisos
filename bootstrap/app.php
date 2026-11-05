<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveBusinessContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->trimStrings(except: ['pin', 'pin_confirmation', 'recovery_key']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
            'device.token' => AuthenticateDevice::class,
            'business.context' => ResolveBusinessContext::class,
            'staff' => EnsureStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['pin', 'pin_confirmation', 'recovery_key']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();

            if ($request->is('api/*') || $request->expectsJson() || ! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            return Inertia::render('Errors/Error', [
                'status' => $status,
                'message' => $e->getMessage() ?: null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
