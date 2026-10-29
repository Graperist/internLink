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
            && ! $request->routeIs('password.change', 'password.update', 'logout')
        ) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
