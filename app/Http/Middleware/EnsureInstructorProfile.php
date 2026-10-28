<?php

namespace App\Http\Middleware;

use App\Models\Instructor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OJT Coordinator is also a program instructor, so they use the shared
 * instructor portal modules. Those modules read auth()->user()->instructor,
 * so make sure a coordinator always has that record. Its program stays
 * empty until the coordinator picks the program they teach in My Profile;
 * until then they can still view the whole campus but cannot act on any
 * student.
 */
class EnsureInstructorProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isCoordinator() && ! $user->instructor) {
            Instructor::create(['user_id' => $user->id, 'campus_id' => $user->campus_id]);
            $user->load('instructor');
        }

        return $next($request);
    }
}
