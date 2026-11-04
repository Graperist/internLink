<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\NullSessionHandler;
use Symfony\Component\HttpFoundation\Response;

/**
 * For small background requests (the audit click beacon, the audit feed,
 * the login page's campus lookup): they READ the session (to know who is
 * signed in) but never WRITE it back.
 *
 * Otherwise they race the page's own requests: clicking "Submit for review"
 * sends the click beacon at the same moment as the form. The beacon, which
 * loaded the session before the form's answer, saved it after - wiping the
 * error messages and typed-in answers the form had just stored, so the
 * form came back empty with no message. (It didn't show locally because
 * the local server handles one request at a time.)
 */
class DontSaveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->hasSession()) {
            $request->session()->setHandler(new NullSessionHandler);
        }

        return $response;
    }
}
