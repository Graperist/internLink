<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->must_change_password
            // HTE accounts are given by the coordinator for evaluations only and keep their password.
            && ! $user->isHteRepresentative()
            // The Super Admin managing a campus doesn't change the coordinator's password.
            && ! \App\Support\CampusManager::active()
            && ! $request->routeIs('password.change', 'password.update', 'logout')
        ) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
