<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsCurrent;
use App\Http\Middleware\EnsureStepUp;
use App\Http\Middleware\EnsureTrustedDevice;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'device.trust' => EnsureTrustedDevice::class,
            'account.active' => EnsureAccountIsActive::class,
            'password.current' => EnsurePasswordIsCurrent::class,
            '2fa.enrolled' => EnsureTwoFactorEnrolled::class,
            'step.up' => EnsureStepUp::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        $middleware->prependToGroup('api', EncryptCookies::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
