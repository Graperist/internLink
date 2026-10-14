<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Figure 20 - My Dashboard: hours rendered vs. required, and the
     * document checklist against this campus's actual requirement list.
     */
    public function index(): View
    {
        $student = auth()->user()->student;
        $internship = $student->currentInternship;
        $isActivelyDeployed = $internship
            && $internship->deployment
            && $internship->deployment->status === 'active';

        $requiredHours = $student->program?->required_hours;
        $requiredHours = is_numeric($requiredHours) && (float) $requiredHours > 0 ? (float) $requiredHours : null;
        $hoursRendered = $student->hours_rendered;
        $hoursRemaining = $requiredHours !== null ? max($requiredHours - $hoursRendered, 0) : null;
        $hoursPercent = $requiredHours !== null ? min(100, (int) round(($hoursRendered / $requiredHours) * 100)) : 0;

        $requirements = DocumentRequirement::where('campus_id', $student->campus_id)
            ->where('is_required', true)
            ->where('is_active', true)
            ->where('audience', 'student')
            ->orderByRaw("CASE stage WHEN 'pre_deployment' THEN 1 WHEN 'active' THEN 2 WHEN 'post_deployment' THEN 3 ELSE 9 END")
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->filter(function ($requirement) use ($internship) {
                if ($requirement->stage === 'pre_deployment') {
                    return true;
                }

                if ($requirement->stage === 'active') {
                    return $internship && $internship->status !== 'pre_deployment';
                }

                return $requirement->stage === 'post_deployment'
                    && $internship?->hasCompletedDeployment();
            })
            ->values();

        $docsByCode = $internship
            ? $internship->documents()->with('requirement')->get()->keyBy(fn ($d) => $d->requirement->code)
            : collect();

        $checklist = $requirements->map(fn ($req) => [
            'requirement' => $req,
            'document' => $docsByCode->get($req->code),
        ]);

        $submittedCount = $checklist->filter(fn ($row) => $row['document'] !== null)->count();

        return view('student.dashboard', compact(
            'student', 'internship', 'requiredHours', 'hoursRendered',
            'hoursRemaining', 'hoursPercent', 'checklist', 'submittedCount'
        ));
    }
}
