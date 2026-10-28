<?php

namespace App\Http\Middleware;

use App\Support\CampusManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only the Super Admin, while managing a campus (Manage), gets through. */
class EnsureManagingSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(CampusManager::superAdmin(), 403, 'Only the Super Admin can do this.');

        return $next($request);
    }
}
