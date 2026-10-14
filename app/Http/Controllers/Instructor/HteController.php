<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Hte;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only list of the campus's partner agencies for Subject Instructors.
 * Adding or editing HTEs stays with the OJT Coordinator, so this controller
 * only has an index action.
 */
class HteController extends Controller
{
    public function index(Request $request): View
    {
        $instructor = auth()->user()->instructor;

        $query = Hte::where('campus_id', $instructor->campus_id)->withCount([
            // Only this instructor's own advisees (same campus + program).
            'deployments as my_students_count' => fn ($q) => $q
                ->whereIn('status', ['pending', 'active'])
                ->whereHas('internship.student', fn ($s) => $s
                    ->where('campus_id', $instructor->campus_id)
                    ->where('program_id', $instructor->program_id)),
        ]);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->query('moa_status')) {
            $query->where('moa_status', $status);
        }

        $htes = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('instructor.htes.index', compact('htes'));
    }
}
