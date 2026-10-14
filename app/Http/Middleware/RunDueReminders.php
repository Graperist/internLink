<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the Form 19 reminders automatic without any server setup.
 *
 * Normally Laravel's scheduler (routes/console.php) sends them at 8:00 PM and
 * 8:00 AM, but that needs a cron/Task Scheduler job. This middleware is a
 * safety net: after a response has been sent, if a reminder slot's time has
 * passed today and it hasn't run yet, it runs it once (Cache::add makes sure
 * only one request does). The reminders de-duplicate themselves, so it is safe
 * to use together with the real scheduler.
 *
 * It runs in terminate(), i.e. after the page has been delivered, so it never
 * slows down the request that triggers it.
 */
class RunDueReminders
{
    /** key => [time of day it becomes due, extra artisan options] */
    private const SLOTS = [
        'overdue' => ['08:00', ['--overdue' => true]],
        'evening' => ['20:00', []],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $now = now();

        foreach (self::SLOTS as $key => [$dueAt, $options]) {
            if ($now->format('H:i') < $dueAt) {
                continue;
            }

            $cacheKey = "reminders:{$key}:".$now->toDateString();

            // Add() only succeeds for the first request that gets here today.
            if (! Cache::add($cacheKey, true, $now->copy()->endOfDay())) {
                continue;
            }

            try {
                Artisan::call('internlink:send-reminders', $options);
            } catch (\Throwable $e) {
                Cache::forget($cacheKey); // try again on a later request
                report($e);
            }
        }
    }
}
