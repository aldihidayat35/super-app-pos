<?php

use App\Console\Commands\CreateEncryptedBackupCommand;
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

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CreateEncryptedBackupCommand::class,
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

// Pada shared hosting, front controller dapat berada di public_html sementara
// source aplikasi berada di direktori lain. Gunakan direktori index.php yang
// benar sebagai public path agar Vite manifest dan asset statis ditemukan.
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && is_string($_SERVER['SCRIPT_FILENAME'])) {
    $frontController = realpath($_SERVER['SCRIPT_FILENAME']);

    if ($frontController !== false && is_file($frontController)) {
        $app->usePublicPath(dirname($frontController));
    }
}

return $app;
