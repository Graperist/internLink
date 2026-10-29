<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => \App\Http\Middleware\EnsureUserHasRole::class]);

        // Audit trail: records page views, downloads, form actions and denied
        // access for every signed-in user. See LogUserActivity + config/audit.php.
        // The Super Admin working in a campus gets the coordinator's access (CampusManager).
        $middleware->appendToGroup('web', \App\Http\Middleware\ApplySuperAdminCampus::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\LogUserActivity::class);

        // Sends the Form 19 weekly-report reminders automatically (8:00 PM /
        // 8:00 AM) even when no cron job or Task Scheduler is set up.
        $middleware->appendToGroup('web', \App\Http\Middleware\RunDueReminders::class);

        // Without this, an already-logged-in user hitting "/" gets caught
        // by the 'guest' middleware wrapping the login route, which falls
        // back to redirecting back to "/" itself when no target is
        // configured - an instant infinite loop. Send them to their actual
        // role's dashboard instead.
        $middleware->redirectUsersTo(function ($request) {
            $user = $request->user();

            return $user ? route($user->dashboardRouteName()) : route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
