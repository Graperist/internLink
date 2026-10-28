<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\HteRepresentative;
use App\Models\Program;
use App\Models\Dtr;
use App\Models\Notification;
use App\Support\AuditLogger;
use App\Support\PreDeployment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdviseeController extends Controller
{
    /**
     * My Students - matches the manuscript prototype's columns (Required
     * Hours / HTE Assignment / Doc Status / Deployment Status / Action)
     * rather than the earlier generic version. Scoped to every student in
     * this instructor's program at their campus (Instructor::advisees()).
     */
    public function index(Request $request): View
    {
        $instructor = auth()->user()->instructor;

        $preDeploymentIds = PreDeployment::requirementIds($instructor->campus_id);
        $requiredDocsCount = $preDeploymentIds->count();

        $advisees = $this->filteredAdvisees($instructor, $request, $preDeploymentIds)
            ->latest('id')->paginate(20)->withQueryString();

        // The coordinator sees the whole campus, so give them a program filter.
        $programs = $instructor->isCoordinator()
            ? Program::where('campus_id', $instructor->campus_id)->orderBy('code')->get()
            : collect();

        // Partner agencies the instructor's (or campus's) students are placed at.
        $agencies = $instructor->visibleStudents()
            ->with('currentInternship.deployment.hte')
            ->when($instructor->isCoordinator() && $request->query('program_id'), fn ($q) => $q->where('program_id', $request->query('program_id')))
            ->get()
            ->map(fn ($s) => $s->currentInternship?->deployment?->hte)
            ->filter()->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('instructor.advisees.index', compact('advisees', 'requiredDocsCount', 'programs', 'instructor', 'agencies'));
    }

    /**
     * Start the OJT. The deployment itself was created automatically when
     * the student's Application for OJT was accepted (see AutoDeployment);
     * here the subject instructor sets the training dates and marks the
     * student as deployed, once every pre-OJT requirement is approved.
     */
    public function activate(Request $request, Deployment $deployment): RedirectResponse
    {
        $user = auth()->user();
        $instructor = $user->instructor;
        $deployment->load('internship.student.user', 'hte');
        $internship = $deployment->internship;
        abort_unless($instructor && $instructor->canActOn($internship), 403);

        if ($deployment->status !== 'pending') {
            throw ValidationException::withMessages(['activation' => 'Only a pending deployment can be started.']);
        }

        if (! PreDeployment::isComplete($internship)) {
            throw ValidationException::withMessages([
                'activation' => 'This student cannot be deployed yet - every required pre-OJT document must be approved first.',
            ]);
        }

        // No dates to enter: the internship runs until the student's rendered
        // hours reach the required hours (from Form 1). The start is recorded
        // as today (Week 1 and the first Time Frame day count from it); the
        // end is set when the instructor confirms completion.
        DB::transaction(function () use ($deployment, $internship) {
            $deployment->update([
                'start_date' => today()->toDateString(),
                'end_date' => null,
                'status' => 'active',
            ]);
            $internship->update(['status' => 'deployed']);
        });

        $start = $deployment->start_date->format('M d, Y');
        $required = \App\Support\TimeFrame::format((float) ($internship->requiredHours() ?? 0));

        if ($studentUserId = $internship->student?->user_id) {
            Notification::create([
                'user_id' => $studentUserId,
                'type' => 'deployment',
                'title' => 'You are now deployed',
                'message' => "Your OJT at {$deployment->hte?->name} started on {$start}. Enter the hours you finish each day in Time Frame (DTR) until you reach your {$required} required hours.",
                'url' => route('student.dtr', [], false),
            ]);
        }

        HteRepresentative::where('hte_id', $deployment->hte_id)->pluck('user_id')->each(
            fn ($userId) => Notification::create([
                'user_id' => $userId,
                'type' => 'deployment',
                'title' => 'New student-intern assigned',
                'message' => ($internship->student?->user?->name ?? 'A student')." starts training with you on {$start} ({$required} required hours).",
                'url' => route('hte.students', [], false),
            ])
        );

        AuditLogger::record(
            'deployment.activated',
            "Deployment started at {$deployment->hte?->name} on {$start}.",
            $user,
            $request,
            $deployment,
            $internship->campus_id,
        );

        return redirect()->route('instructor.advisees', $request->only('search', 'status', 'program_id'))
            ->with('status', ($internship->student?->user?->name ?? 'The student').' is now deployed.');
    }

    /**
     * Confirm internship completion once the hours the student entered in
     * Time Frame reach the required hours (from Form 1). The end date is
     * then set to the last day entered.
     */
    public function confirmCompletion(Deployment $deployment): RedirectResponse
    {
        $user = auth()->user();
        $instructor = $user->instructor;
        abort_unless($instructor, 403);

        $result = DB::transaction(function () use ($deployment, $instructor, $user) {
            $locked = Deployment::query()->whereKey($deployment->id)->lockForUpdate()->firstOrFail();
            $locked->load(['internship.student.program', 'internship.program']);
            $internship = $locked->internship;
            abort_unless($instructor->canActOn($internship), 403);

            if ($locked->status !== 'active' || $internship->status !== 'deployed') {
                throw ValidationException::withMessages([
                    'completion' => 'Only an active internship can be confirmed as completed.',
                ]);
            }

            $requiredHours = (float) ($internship->requiredHours() ?? 0);

            if ($requiredHours <= 0) {
                throw ValidationException::withMessages([
                    'completion' => 'The required internship hours are not set. Check the hours on the student\'s Application for OJT (Form 1).',
                ]);
            }

            // The hours the student entered in Time Frame (they count right away).
            $days = Dtr::where('deployment_id', $locked->id)->get();
            $verifiedHours = round((float) $days->sum(fn ($d) => $d->countedHours()), 2);

            if ($verifiedHours < $requiredHours) {
                $remaining = \App\Support\TimeFrame::format(max($requiredHours - $verifiedHours, 0));
                throw ValidationException::withMessages([
                    'completion' => 'The student has rendered '.\App\Support\TimeFrame::format($verifiedHours).' of '.\App\Support\TimeFrame::format($requiredHours)." required hours. {$remaining} more hours are needed.",
                ]);
            }

            // The internship ends on the last day entered.
            $locked->end_date = $days->max('log_date') ?? today();
            $locked->update(['status' => 'completed']);
            $internship->update(['status' => 'completed']);

            $studentUserId = $internship->student?->user_id;
            if ($studentUserId) {
                Notification::create([
                    'user_id' => $studentUserId,
                    'type' => 'deployment_completed',
                    'title' => 'Internship completion confirmed',
                    'message' => 'Your instructor confirmed your internship completion: you reached your required hours. Your post-OJT requirements are now available under My Documents.',
                    'url' => route('student.documents'),
                ]);
            }

            return ['hours' => $verifiedHours, 'required' => $requiredHours, 'campus_id' => $internship->campus_id];
        });

        AuditLogger::record(
            'deployment.instructor_completed',
            "Instructor confirmed internship completion with {$result['hours']} of {$result['required']} rendered hours.",
            $user,
            request(),
            $deployment,
            $result['campus_id'],
        );

        return redirect()->route('instructor.advisees')
            ->with('status', 'Internship completion confirmed. Post-OJT requirements are now available to the student.');
    }

    /**
     * Same filtered list as index(), unpaginated, as a downloadable CSV -
     * the "Improve via Excel" link in the prototype (read as "Export via
     * Excel"; the mockup's own label appears to be a typo, since nothing
     * on this page is editable).
     */
    public function export(Request $request): StreamedResponse
    {
        $instructor = auth()->user()->instructor;

        $preDeploymentIds = PreDeployment::requirementIds($instructor->campus_id);
        $requiredDocsCount = $preDeploymentIds->count();

        $advisees = $this->filteredAdvisees($instructor, $request, $preDeploymentIds)->get();

        $callback = function () use ($advisees, $requiredDocsCount) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name', 'ID Number', 'Program', 'Required Hours', 'HTE Assignment',
                'Documents Approved', 'Documents Required', 'Deployment Status',
            ]);

            foreach ($advisees as $student) {
                $internship = $student->currentInternship;
                $deployment = $internship?->deployment;

                fputcsv($handle, [
                    $student->user->name,
                    $student->user->id_number,
                    $student->program?->code ?? '',
                    $internship?->requiredHours() ?? $student->program->required_hours,
                    $deployment?->hte?->name ?? '',
                    $student->approved_documents_count,
                    $requiredDocsCount,
                    $deployment?->status ? ucfirst($deployment->status) : 'Not deployed',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="my-students-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    private function filteredAdvisees($instructor, Request $request, $preDeploymentIds)
    {
        // Coordinator: whole campus. Instructor: own program.
        $query = $instructor->visibleStudents()
            ->with(['user', 'program', 'currentInternship.deployment.hte', 'currentInternship.deployment.dtrs'])
            ->withCount(['documents as approved_documents_count' => fn ($q) => $q
                ->where('documents.status', 'approved')
                ->whereIn('documents.document_requirement_id', $preDeploymentIds)]);

        if ($instructor->isCoordinator() && ($programId = $request->query('program_id'))) {
            $query->where('program_id', $programId);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%")
            );
        }

        if ($hteId = $request->query('hte_id')) {
            $hteId === 'none'
                ? $query->whereDoesntHave('currentInternship.deployment', fn ($q) => $q->whereNotNull('hte_id'))
                : $query->whereHas('currentInternship.deployment', fn ($q) => $q->where('hte_id', (int) $hteId));
        }

        if ($status = $request->query('status')) {
            $status === 'not_deployed'
                ? $query->whereDoesntHave('currentInternship.deployment')
                : $query->whereHas('currentInternship.deployment', fn ($q) => $q->where('status', $status));
        }

        return $query;
    }
}
