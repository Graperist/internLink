<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Usage in routes: ->middleware('role:coordinator')
     * or for multiple: ->middleware('role:coordinator,instructor')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->code, $roles, true)) {
            abort(403, 'You do not have access to this section of INTERN-LINK.');
        }

        return $next($request);
    }
}
