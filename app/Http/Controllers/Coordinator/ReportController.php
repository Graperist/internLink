<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Hte;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * The Campus Exports page is now part of Generate Reports (one page for
     * the coordinator).
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('instructor.reports');
    }

    /**
     * Campus summary shown on the coordinator's Generate Reports page: the
     * per-program breakdown, partner HTEs and active MOAs.
     *
     * @return array{programStats: \Illuminate\Support\Collection, hteCount: int, activeMoaCount: int}
     */
    public static function campusSummary(int $campusId, ?\App\Support\TermFilter $term = null): array
    {

        // Pre-deployment requirements only (live, required, pre-deployment stage).
        $preDeploymentIds = \App\Support\PreDeployment::requirementIds($campusId);
        $requiredDocsCount = $preDeploymentIds->count();

        $incompleteDocsSql = \App\Support\PreDeployment::incompleteSql($preDeploymentIds);

        $programs = Program::where('campus_id', $campusId)->orderBy('name')->get();

        $programStats = $programs->map(function ($program) use ($requiredDocsCount, $incompleteDocsSql, $term) {
            // The interns enrolled in the chosen academic year / semester.
            $base = fn () => $term ? $term->applyToStudents(Student::where('program_id', $program->id)) : Student::where('program_id', $program->id);

            return [
                'program' => $program,
                'total' => $base()->count(),
                'deployed' => $base()->whereHas(
                    'currentInternship.deployment',
                    fn ($q) => $q->where('status', 'active')
                )->count(),
                'pending' => $base()->where(fn ($q) => $q
                    ->whereDoesntHave('currentInternship.deployment')
                    ->orWhereHas('currentInternship.deployment', fn ($qq) => $qq->where('status', 'pending'))
                )->count(),
                'incomplete' => $requiredDocsCount > 0
                    ? $base()->whereRaw($incompleteDocsSql)->count()
                    : 0,
            ];
        });

        $hteCount = Hte::where('campus_id', $campusId)->count();
        $activeMoaCount = Hte::where('campus_id', $campusId)->where('moa_status', 'active')->count();

        return compact('programStats', 'hteCount', 'activeMoaCount');
    }

    /**
     * Every enrolled student, one row each - the same data as the Intern
     * List table, without pagination, as a downloadable CSV.
     */
    public function exportInternRoster(\Illuminate\Http\Request $request): StreamedResponse
    {
        $campusId = auth()->user()->campus_id;
        // The academic year / semester chosen on Generate Reports (current year by default).
        $term = \App\Support\TermFilter::fromRequest($request);

        $students = $term->applyToStudents(Student::with(['user', 'program', 'currentInternship.deployment.hte'])
            ->where('campus_id', $campusId))
            ->get();

        $callback = function () use ($students) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name', 'ID Number', 'Program', 'Academic Year', 'Semester', 'Host Training Establishment',
                'Deployment Status', 'Hours Rendered', 'Required Hours',
            ]);

            foreach ($students as $student) {
                $deployment = $student->currentInternship?->deployment;

                fputcsv($handle, [
                    $student->user->name,
                    $student->user->id_number,
                    $student->program->code,
                    $student->academic_year,
                    $student->semester,
                    $deployment?->hte?->name ?? '',
                    $deployment?->status ? ucfirst($deployment->status) : 'Not deployed',
                    $student->hours_rendered,
                    $student->currentInternship?->requiredHours() ?? $student->program->required_hours,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="intern-roster-'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
