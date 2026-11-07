<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Support\AgencyEvaluations;
use App\Support\EvaluationRouting;
use App\Support\EvaluationView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Evaluations, the way PSU students evaluate their instructors: a list of
 * names, click one, rate it, and it reads EVALUATED.
 *
 *  - The instructor evaluates each partner agency their students finished an
 *    internship at (Form 23a, once per agency). It stays with the instructor.
 *  - Every other evaluation arrives here, and only here: the HTE's about each
 *    intern (Forms 13, 21) and about itself (Form 23b), and each intern's
 *    about the agency (Forms 23c, 20). Whoever is rated never sees it.
 */
class EvaluationController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        $instructor = $this->subjectInstructor();
        $isCoordinator = $instructor->isCoordinator();

        // The coordinator sees every program's evaluations and can narrow them to one program.
        $programs = $isCoordinator
            ? \App\Models\Program::where('campus_id', $instructor->campus_id)->orderBy('code')->get()
            : collect();
        // It opens on the program the coordinator handles; "All programs" shows every program.
        $wanted = $request->has('program_id') ? (int) $request->query('program_id') : (int) $instructor->program_id;
        $programId = $isCoordinator && $programs->contains('id', $wanted) ? $wanted : null;

        // One academic year (the current one) and, if picked, one semester.
        $term = \App\Support\TermFilter::fromRequest($request);
        $agencies = AgencyEvaluations::agenciesFor($instructor, $programId, $term);
        // Like Document Review: the agencies on the left, one open on the right (?hte_id=).
        $hteId = (int) $request->query('hte_id') ?: null;

        // The other partner agencies (interns still training there, or none placed
        // yet this term): listed too, their evaluations open once an intern finishes.
        // The chosen term's partner lists (both semesters unless one is picked).
        $academicYear = $term->year === 'all' ? null : $term->year;
        $scope = fn ($q) => $instructor->limitToEvaluationScope($q)->when($programId, fn ($q) => $q->where('program_id', $programId));
        $training = $term->applyThrough(\App\Models\Deployment::whereIn('status', ['pending', 'active'])->whereHas('internship', $scope), 'internship.student')
            ->with(['hte', 'internship.student.user'])->get()->filter(fn ($d) => $d->hte)->groupBy('hte_id');
        $listed = \App\Models\HteListing::when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->when($term->semester, fn ($q) => $q->where('semester', $term->semester))
            ->where(fn ($q) => $scope($q))->with('hte')->get()->filter(fn ($l) => $l->hte)->pluck('hte', 'hte_id');
        $waitingAgencies = collect($listed)->union($training->map(fn ($d) => $d->first()->hte))
            ->reject(fn ($hte, $id) => $agencies->has($id))
            ->map(fn ($hte, $id) => (object) [
                'hte' => $hte,
                'training' => $training->get($id, collect())->count(),
                'students' => $training->get($id, collect())->map(fn ($d) => $d->internship?->student)->filter()->sortBy(fn ($st) => $st->user?->name)->values(),
            ])
            ->sortBy(fn ($r) => $r->hte->name)->values();
        // ?student_id= opens that intern's agency and shows only them.
        $studentId = (int) $request->query('student_id') ?: null;

        // Agencies whose interns are still training: listed too, their evaluations open when an intern finishes.
        $inTraining = $term->applyThrough(\App\Models\Deployment::where('status', 'active'), 'internship.student')
            ->whereHas('internship', fn ($q) => $instructor->limitToEvaluationScope($q)->when($programId, fn ($q) => $q->where('program_id', $programId)))
            ->whereNotIn('hte_id', $agencies->keys())
            ->with(['hte', 'internship.student.user'])
            ->get()->filter(fn ($d) => $d->hte)
            ->groupBy('hte_id')
            ->map(fn ($ds) => (object) ['hte' => $ds->first()->hte, 'deployments' => $ds->sortBy(fn ($d) => $d->internship->student->user->name)->values()])
            ->sortBy(fn ($row) => $row->hte->name)->values();

        $requirements = DocumentRequirement::where('campus_id', $instructor->campus_id)
            ->where('is_active', true)
            ->whereIn('code', array_keys(EvaluationRouting::FORMS))
            ->get()->keyBy('code');

        $form23a = $requirements->get('PARTNER_AGENCY_EVAL_INSTRUCTOR');
        $form23b = $requirements->get('PARTNER_AGENCY_EVAL_SELF');

        $agencyRows = $agencies->map(fn ($row) => (object) [
            'hte' => $row['hte'],
            'deployments' => $row['deployments'],
            'mine' => $form23a ? $this->submitted(AgencyEvaluations::find($form23a, $row['hte']->id, $instructor->user_id)) : null,
            'self' => $form23b ? $this->submitted(AgencyEvaluations::find($form23b, $row['hte']->id)) : null,
            // Form 23a: only for an agency where students of the program you handle trained.
            'canEvaluate' => $instructor->program_id !== null
                && $row['deployments']->contains(fn ($d) => (int) $d->internship->program_id === (int) $instructor->program_id),
        ])->values();

        // Per intern: what the HTE sent about them and what they sent about the agency.
        $internCodes = array_merge(EvaluationRouting::HTE_INTERN_CODES, EvaluationRouting::STUDENT_CODES);
        // (Eloquent's only() picks by primary key, so filter by code.)
        $internRequirementIds = $requirements->filter(fn ($r) => in_array($r->code, $internCodes, true))->pluck('id', 'code');
        $deployments = $agencies->flatMap(fn ($row) => $row['deployments']);
        $docs = Document::whereIn('internship_id', $deployments->pluck('internship_id'))
            ->whereIn('document_requirement_id', $internRequirementIds)
            ->whereIn('status', EvaluationRouting::SUBMITTED)
            ->get()
            ->groupBy('internship_id');
        $internRows = $deployments->map(fn ($deployment) => (object) [
            'deployment' => $deployment,
            'student' => $deployment->internship->student,
            'forms' => collect($internCodes)->mapWithKeys(fn ($code) => [
                $code => ($docs->get($deployment->internship_id) ?? collect())->firstWhere('document_requirement_id', $internRequirementIds[$code] ?? 0),
            ]),
        ])->sortBy(fn ($row) => $row->student->user->name)->values();

        return view('instructor.evaluations.index', compact('agencyRows', 'internRows', 'internCodes', 'requirements', 'isCoordinator', 'programs', 'programId', 'instructor', 'hteId', 'waitingAgencies', 'studentId', 'inTraining', 'term'));
    }

    /** Read or (?download=1) download a received evaluation, or your own Form 23a. */
    public function show(Document $document): Response
    {
        $this->authorizeView($document, $this->subjectInstructor());

        return EvaluationView::response($document, route('instructor.evaluations'));
    }

    /**
     * Lock a received evaluation (Forms 13, 21, 23b, 23c, 20) so the student
     * or the HTE can no longer edit it - e.g. once it has been graded.
     * Only the instructor of the program it belongs to (the coordinator for
     * their own program).
     */
    public function lock(Document $document): RedirectResponse
    {
        $instructor = $this->subjectInstructor();
        $this->authorizeLock($document, $instructor);
        $document->update(['locked_at' => now(), 'locked_by_user_id' => auth()->id()]);
        \App\Support\AuditLogger::record('evaluation.locked', 'Locked '.$document->requirement->name.' from editing.', auth()->user(), request());

        return back()->with('status', $document->requirement->name.' is locked. It can no longer be edited.');
    }

    /** Unlock it again for a correction: a new editing window starts today. */
    public function unlock(Document $document): RedirectResponse
    {
        $instructor = $this->subjectInstructor();
        $this->authorizeLock($document, $instructor);
        $document->update(['locked_at' => null, 'locked_by_user_id' => null, 'unlocked_at' => now()]);
        \App\Support\AuditLogger::record('evaluation.unlocked', 'Unlocked '.$document->requirement->name.' for editing.', auth()->user(), request());
        $until = \App\Support\EvaluationEditWindow::editableUntil($document->fresh(['internship.campus', 'internship.deployment']));

        return back()->with('status', $document->requirement->name.' is unlocked. It can be edited until '.$until?->format('M j, Y').'.');
    }

    /**
     * The editing deadline for the program you handle: students and HTEs can
     * edit their evaluations for this many days after the internship ends
     * (until 11:59 PM), unless you lock one sooner.
     */
    public function editDays(\Illuminate\Http\Request $request): RedirectResponse
    {
        $instructor = $this->subjectInstructor()->load('program');
        // Always the program on your own account: the coordinator sets it for
        // the program they handle only, never for another program.
        abort_unless($instructor->program, 422, 'Your account has no program yet - ask your coordinator.');
        $days = (int) $request->validate([
            'evaluation_edit_days' => ['required', 'integer', 'min:0', 'max:90'],
        ], ['evaluation_edit_days.*' => 'Enter a number of days from 0 to 90.'])['evaluation_edit_days'];
        $instructor->program->forceFill(['evaluation_edit_days' => $days])->save();

        $text = $days === 0 ? 'until the day the internship ends' : "for {$days} ".\Illuminate\Support\Str::plural('day', $days).' after the internship ends';

        return back()->with('status', "{$instructor->program->code}: students and HTEs can edit their evaluations {$text}, unless you lock one sooner.");
    }

    /**
     * The editing deadline for one partner agency's evaluations (Forms 13, 21,
     * 23b) in the program you handle - or cleared, back to the program's days.
     */
    public function agencyDeadline(\Illuminate\Http\Request $request, \App\Models\Hte $hte): RedirectResponse
    {
        $instructor = $this->subjectInstructor();
        abort_unless($instructor->program_id && (int) $hte->campus_id === (int) $instructor->campus_id, 403);
        abort_unless(\App\Models\Deployment::where('hte_id', $hte->id)->where('status', 'completed')
            ->whereHas('internship', fn ($q) => $instructor->limitToOwnProgram($q))->exists(), 403,
            'Only the subject instructor of a program whose students finished here sets this deadline.');
        $due = $this->validatedDeadline($request);

        if ($due === null) {
            \App\Models\EvaluationDeadline::where('hte_id', $hte->id)->where('program_id', $instructor->program_id)->delete();
            $message = "{$hte->name} is back to the program's editing deadline.";
        } else {
            \App\Models\EvaluationDeadline::updateOrCreate(
                ['hte_id' => $hte->id, 'program_id' => $instructor->program_id],
                ['due_at' => $due, 'set_by_user_id' => auth()->id()]
            );
            $message = "{$hte->name} can edit its evaluations until ".$due->format('M d, Y g:i A').'.';
            // The agency is told.
            \App\Models\HteRepresentative::where('hte_id', $hte->id)->whereNotNull('user_id')->pluck('user_id')
                ->each(fn ($userId) => \App\Models\Notification::create([
                    'user_id' => $userId, 'type' => 'evaluation_deadline',
                    'title' => 'Evaluation deadline: '.$due->format('M d, g:i A'),
                    'message' => 'You can submit or edit your evaluations until '.$due->format('l, M d, Y \\a\\t g:i A').'.',
                    'url' => route('hte.evaluations', [], false),
                ]));
        }
        \App\Support\AuditLogger::record('evaluation.deadline', $message, auth()->user(), $request);

        return back()->with('status', $message);
    }

    /** The editing deadline for one intern's own evaluations (Forms 23c, 20) - or cleared. */
    public function studentDeadline(\Illuminate\Http\Request $request, \App\Models\Internship $internship): RedirectResponse
    {
        $instructor = $this->subjectInstructor();
        abort_unless($instructor->ownsProgram($internship), 403);
        $due = $this->validatedDeadline($request);
        $name = $internship->student?->user?->name ?? 'The intern';

        $internship->forceFill(['evaluation_deadline_at' => $due])->save();
        if ($due === null) {
            $message = "{$name} is back to the program's editing deadline.";
        } else {
            $message = "{$name} can edit their evaluations until ".$due->format('M d, Y g:i A').'.';
            if ($userId = $internship->student?->user_id) {
                \App\Models\Notification::create([
                    'user_id' => $userId, 'type' => 'evaluation_deadline',
                    'title' => 'Evaluation deadline: '.$due->format('M d, g:i A'),
                    'message' => 'You can submit or edit your evaluations of your agency until '.$due->format('l, M d, Y \\a\\t g:i A').'.',
                    'url' => route('student.hte', [], false),
                ]);
            }
        }
        \App\Support\AuditLogger::record('evaluation.deadline', $message, auth()->user(), $request);

        return back()->with('status', $message);
    }

    /** The picked date and time, or null for "Use the program's deadline". */
    private function validatedDeadline(\Illuminate\Http\Request $request): ?\Carbon\Carbon
    {
        if ($request->boolean('clear')) {
            return null;
        }
        $value = $request->validate(
            ['due_at' => ['required', 'date_format:Y-m-d\\TH:i']],
            ['due_at.*' => 'Pick the deadline date and time.']
        )['due_at'];

        return \Carbon\Carbon::createFromFormat('Y-m-d\\TH:i', $value);
    }

    private function authorizeView(Document $document, $instructor): void
    {
        $document->load('requirement', 'internship');
        $code = $document->requirement?->code;
        abort_unless(isset(EvaluationRouting::FORMS[$code]), 404);
        abort_unless($document->requirement->campus_id === $instructor->campus_id, 403);
        abort_unless(EvaluationRouting::isSubmitted($document), 404);

        if (EvaluationRouting::isPerAgency($code)) {
            // An agency where this instructor's students trained; Form 23a only the instructor's own.
            abort_unless($document->hte_id && AgencyEvaluations::agenciesFor($instructor)->has($document->hte_id), 403);
            abort_if($code === 'PARTNER_AGENCY_EVAL_INSTRUCTOR' && $document->uploaded_by_user_id !== $instructor->user_id, 403);
        } else {
            // The coordinator reads every program's; an instructor their own program's.
            abort_unless($instructor->isCoordinator()
                ? (int) $document->internship?->campus_id === (int) $instructor->campus_id
                : $instructor->ownsProgram($document->internship), 403);
        }
    }

    private function authorizeLock(Document $document, $instructor): void
    {
        $this->authorizeView($document, $instructor);
        $code = $document->requirement->code;
        // Form 23a is the instructor's own; nothing to lock.
        abort_if($code === 'PARTNER_AGENCY_EVAL_INSTRUCTOR', 404);
        if (EvaluationRouting::isPerAgency($code)) {
            // Form 23b: an agency where students of the program you handle trained.
            abort_unless($instructor->program_id && AgencyEvaluations::agenciesFor($instructor, $instructor->program_id)->has($document->hte_id), 403);
        } else {
            abort_unless($instructor->ownsProgram($document->internship), 403);
        }
    }

    /**
     * Evaluations belong to the instructor of the student's program. The OJT
     * Coordinator sees the evaluations of every program on the campus.
     */
    private function subjectInstructor()
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor, 403);

        return $instructor;
    }

    private function submitted(?Document $document): ?Document
    {
        return EvaluationRouting::isSubmitted($document) ? $document : null;
    }
}
