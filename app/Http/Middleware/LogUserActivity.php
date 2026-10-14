<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records what each signed-in user does: pages opened, files downloaded,
 * forms submitted, and any attempt to open something they're not allowed to.
 *
 * Runs AFTER the request has been handled so the outcome (allowed, denied,
 * rejected by validation) is known. Registered globally on the "web" group in
 * bootstrap/app.php, so new routes are covered automatically.
 */
class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->log($request, $response);
        } catch (Throwable $e) {
            report($e); // the audit trail must never take a page down
        }

        return $response;
    }

    private function log(Request $request, Response $response): void
    {
        $user = $request->user();
        $name = $request->route()?->getName();

        if (! $user || ! $name || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return;
        }

        if (in_array($name, config('audit.ignore_routes', []), true)) {
            return;
        }

        $status = $response->getStatusCode();
        $isRead = $request->isMethod('GET');

        // Background JSON polling isn't a user action.
        if ($isRead && $request->expectsJson()) {
            return;
        }

        // Someone tried to open something their role doesn't permit.
        if ($status === 403) {
            AuditLogger::record(
                'security.denied',
                'Access denied: '.$request->method().' /'.ltrim($request->path(), '/'),
                $user,
                $request,
            );

            return;
        }

        $subject = $this->subjectOf($request);
        $suffix = $subject
            ? ' ('.Str::headline(class_basename($subject)).' #'.$subject->getKey().')'
            : '';

        if ($isRead) {
            // Redirects/errors on a GET aren't "a page was opened".
            if ($status < 200 || $status >= 300) {
                return;
            }

            $isFile = (bool) preg_match('/download|export/', $name);

            AuditLogger::record(
                $isFile ? 'action.download' : 'page.view',
                $this->label('pages', $name, 'Opened').$suffix,
                $user,
                $request,
                $subject,
            );

            return;
        }

        // A change was attempted (POST / PUT / PATCH / DELETE).
        $outcome = '';
        if ($status >= 500) {
            $outcome = ' - failed (server error)';
        } elseif ($status === 422 || ($response->isRedirection() && $this->hasValidationErrors($request))) {
            $outcome = ' - rejected (validation failed)';
        }

        AuditLogger::record(
            'action.'.strtolower($request->method()),
            $this->label('actions', $name, 'Performed').$suffix.$outcome,
            $user,
            $request,
            $subject,
        );
    }

    /** Wording from config/audit.php, or a readable fallback built from the route name. */
    private function label(string $group, string $routeName, string $fallbackVerb): string
    {
        // NB: index the array directly - route names contain dots, which
        // config()'s dot-notation would misread as nested keys.
        $labels = config("audit.$group", []);

        if (isset($labels[$routeName])) {
            return $labels[$routeName];
        }

        $rest = preg_replace('/^(coordinator|instructor|hte|student)\./', '', $routeName);

        return $fallbackVerb.' '.Str::headline(str_replace(['.', '-'], ' ', $rest));
    }

    /** The first route-model-bound parameter (Student, Document, Dtr, ...), if any. */
    private function subjectOf(Request $request): ?Model
    {
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }

    private function hasValidationErrors(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has('errors');
    }
}
