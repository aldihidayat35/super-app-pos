<?php

use App\Console\Commands\RunNotificationSchedulesCommand;
use App\Console\Commands\SendDailyReportCommand;
use App\Http\Middleware\BlockB2bPortalOnlyUserFromInternal;
use App\Http\Middleware\EnsureB2bCustomerAccess;
use App\Http\Middleware\EnsureHealthAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveWorkLocation;
use App\Http\Middleware\SecureResponseHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        RunNotificationSchedulesCommand::class,
        SendDailyReportCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecureResponseHeaders::class,
        ]);

        $middleware->alias([
            'health.access' => EnsureHealthAccess::class,
            'active.user' => EnsureUserIsActive::class,
            'b2b.customer' => EnsureB2bCustomerAccess::class,
            'internal.access' => BlockB2bPortalOnlyUserFromInternal::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'work.location' => ResolveWorkLocation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
