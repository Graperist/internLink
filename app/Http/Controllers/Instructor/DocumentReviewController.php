<?php



namespace App\Http\Controllers\Instructor;



use App\Http\Controllers\Controller;

use App\Models\Document;

use App\Models\DocumentRequirement;

use App\Models\DocumentReview;

use App\Models\Notification;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;

use Illuminate\View\View;

use Symfony\Component\HttpFoundation\StreamedResponse;



class DocumentReviewController extends Controller

{

    /**

     * Document Review - per-student requirement checklist. Requirements

     * with structured field definitions (e.g., Application for OJT) now

     * show the real workflow: view the student's entered data, Return or

     * Print for Signing, then Mark Completed once the signed copy comes

     * back - which unlocks the student's one-time upload. Requirements

     * without field definitions keep the original Approve/Return flow

     * unchanged.

     *

     * The 5 signatories row (Subject Adviser/Registrar/Coordinator/Dean/

     * CTE) remains static/informational only - nothing tracks

     * per-signatory approval state anywhere in the schema.

     */

    public function index(Request $request): View

    {

        $instructor = auth()->user()->instructor;



        // Coordinator: every student on the campus. Instructor: own program.
        $advisees = $instructor->visibleStudents()

            ->with(['user', 'program'])

            ->withCount(['documents as pending_documents_count' => fn ($q) => $q

                ->whereIn('documents.status', ['submitted', 'for_review'])

                ->whereHas('requirement', fn ($r) => $r

                    ->where('is_active', true)

                    ->where('is_required', true)

                    ->whereIn('audience', ['student', 'coordinator'])
                    ->whereNotIn('code', \App\Support\EvaluationRouting::studentToHteCodes()))

            ])

            ->orderByDesc('pending_documents_count')

            ->orderBy('id')

            ->get();



        // Teams-style: Document Review opens on the student list; clicking a
        // name opens that one student's forms (and nothing else).
        if (! $request->filled('student')) {
            return $this->studentList($request, $instructor, $advisees);
        }

        $student = $advisees->firstWhere('id', (int) $request->query('student'));
        abort_unless($student, 404);

        // Previous / next student, in the same name order as the list.
        $ordered = $advisees->sortBy(fn ($a) => $a->user->name, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $position = $ordered->search(fn ($a) => $a->id === $student->id);
        $prevStudent = $position > 0 ? $ordered[$position - 1] : null;
        $nextStudent = $position < $ordered->count() - 1 ? $ordered[$position + 1] : null;
        $studentPosition = ['index' => $position + 1, 'total' => $ordered->count()];



        $requirementsByStage = collect();

        $documentsByRequirement = collect();

        $fieldWarnings = [];
        $form19Packets = collect();

        $internship = null;



        if ($student) {

            $internship = $student->currentInternship;



            $requirements = DocumentRequirement::where('campus_id', $instructor->campus_id)

                ->where('is_required', true)

                ->where('is_active', true)

                ->whereIn('audience', ['student', 'coordinator']) // coordinator = MOA, uploaded by the coordinator, reviewed here
                ->whereNotIn('code', \App\Support\EvaluationRouting::studentToHteCodes()) // Forms 20 / 23c are delivered to the HTE, not reviewed here

                ->with('fieldDefinitions')

                ->orderByRaw("CASE stage WHEN 'pre_deployment' THEN 1 WHEN 'active' THEN 2 WHEN 'post_deployment' THEN 3 ELSE 9 END")

                ->orderBy('display_order')

                ->orderBy('name')

                ->get();



            if (! $internship) {

                $requirements = $requirements->where('stage', 'pre_deployment')->values();

            } elseif ($internship->status === 'pre_deployment') {

                $requirements = $requirements->where('stage', 'pre_deployment')->values();

            } elseif (! $internship->hasCompletedDeployment()) {

                // Active internship: include active-stage requirements such as
                // Form 19 (Weekly Report), while keeping post-deployment
                // requirements hidden until the internship is completed.
                $requirements = $requirements
                    ->whereIn('stage', ['pre_deployment', 'active'])
                    ->values();

            }



            $requirementsByStage = $requirements->groupBy('stage');



            $allStudentDocuments = $internship
                ? $internship->documents()->with(['latestReview', 'fieldValues', 'entries.values', 'requirement', 'attachments'])->get()
                : collect();
            $documentsByRequirement = $allStudentDocuments->keyBy('document_requirement_id');
            $form19Packets = $allStudentDocuments
                ->filter(fn ($doc) => $doc->requirement?->code === 'WEEKLY_REPORT')
                ->sortBy('packet_number')->values();



            // Advisory-only checks (reference data + cross-student duplicates)

            // computed fresh on each page load, only for documents still

            // awaiting a decision - once printed, the data is frozen anyway.

            foreach ($documentsByRequirement as $requirementId => $document) {

                if (! in_array($document->status, ['submitted', 'for_review'])) {

                    continue;

                }



                foreach ($document->fieldValues as $fieldValue) {

                    $warning = \App\Support\DocumentFieldChecker::check($fieldValue, $instructor->campus_id);

                    if ($warning) {

                        $fieldWarnings[$document->id][$fieldValue->field_key] = $warning;

                    }

                }

            }

        }



        // Approve / return / print / unlock buttons only for students in the
        // viewer's own program. The coordinator sees other programs read-only.
        $canAct = $student ? $instructor->canActOn($student) : false;

        return view('instructor.documents.index', compact(

            'advisees', 'student', 'internship', 'requirementsByStage', 'documentsByRequirement', 'fieldWarnings', 'form19Packets', 'canAct',
            'prevStudent', 'nextStudent', 'studentPosition'

        ));

    }



    /**
     * The student list (first screen of Document Review): one row per
     * student with how many forms wait for review, how many are approved,
     * and a View link that opens only that student. Mirrors MS Teams'
     * assignment list -> one student's work.
     */
    private function studentList(Request $request, $instructor, $advisees): View
    {
        $requirements = DocumentRequirement::where('campus_id', $instructor->campus_id)
            ->where('is_required', true)
            ->where('is_active', true)
            ->whereIn('audience', ['student', 'coordinator'])
            ->whereNotIn('code', \App\Support\EvaluationRouting::studentToHteCodes())
            ->get(['id', 'stage']);

        $advisees->load('currentInternship.documents');

        $rows = $advisees->map(function ($student) use ($requirements, $instructor) {
            $internship = $student->currentInternship;
            $applicable = $requirements->filter(fn ($r) => match (true) {
                ! $internship, $internship->status === 'pre_deployment' => $r->stage === 'pre_deployment',
                ! $internship->hasCompletedDeployment() => in_array($r->stage, ['pre_deployment', 'active'], true),
                default => true,
            });
            $docs = $internship ? $internship->documents->whereIn('document_requirement_id', $applicable->pluck('id')) : collect();
            $byStatus = fn (array $statuses) => $docs->whereIn('status', $statuses)->pluck('document_requirement_id')->unique()->count();

            return (object) [
                'student' => $student,
                'required' => $applicable->count(),
                'approved' => $byStatus(['approved']),
                'to_review' => $byStatus(['submitted', 'for_review', 'completed']),
                'signing' => $byStatus(['printed']),
                'returned' => $byStatus(['returned']),
                'missing' => max($applicable->count() - $docs->pluck('document_requirement_id')->unique()->count(), 0),
                'last_submitted' => $docs->max('submitted_at'),
                'can_act' => $instructor->canActOn($student),
            ];
        });

        if ($search = trim((string) $request->query('search'))) {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->student->user->name), $needle)
                || str_contains(mb_strtolower((string) $r->student->user->id_number), $needle));
        }
        if ($instructor->isCoordinator() && ($programId = $request->query('program_id'))) {
            $rows = $rows->where('student.program_id', (int) $programId);
        }

        $counts = [
            'all' => $rows->count(),
            'review' => $rows->filter(fn ($r) => $r->to_review > 0)->count(),
            'missing' => $rows->filter(fn ($r) => $r->missing > 0 || $r->returned > 0)->count(),
            'complete' => $rows->filter(fn ($r) => $r->required > 0 && $r->approved >= $r->required)->count(),
        ];

        $tab = in_array($request->query('tab'), ['review', 'missing', 'complete'], true) ? $request->query('tab') : 'all';
        $rows = match ($tab) {
            'review' => $rows->filter(fn ($r) => $r->to_review > 0),
            'missing' => $rows->filter(fn ($r) => $r->missing > 0 || $r->returned > 0),
            'complete' => $rows->filter(fn ($r) => $r->required > 0 && $r->approved >= $r->required),
            default => $rows,
        };

        // Students with forms waiting on the reviewer first, then by name.
        $rows = $rows->sortBy(fn ($r) => sprintf('%d|%s', $r->to_review > 0 ? 0 : 1, mb_strtolower($r->student->user->name)))->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $programs = $instructor->isCoordinator()
            ? \App\Models\Program::where('campus_id', $instructor->campus_id)->orderBy('code')->get()
            : collect();

        return view('instructor.documents.list', [
            'rows' => $paginator,
            'counts' => $counts,
            'tab' => $tab,
            'programs' => $programs,
            'instructor' => $instructor,
        ]);
    }

    /**
     * Bulk action from the student list: remind the selected students (own
     * program only) which forms they still need to submit or correct.
     */
    public function remind(Request $request): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
        ], ['student_ids.required' => 'Select at least one student first.']);

        $requirements = DocumentRequirement::where('campus_id', $instructor->campus_id)
            ->where('is_required', true)->where('is_active', true)
            ->where('audience', 'student')
            ->whereNotIn('code', \App\Support\EvaluationRouting::studentToHteCodes())
            ->get();

        $sent = 0;
        $instructor->visibleStudents()->with(['user', 'currentInternship.documents'])
            ->whereIn('id', $data['student_ids'])->get()
            ->filter(fn ($student) => $instructor->canActOn($student))
            ->each(function ($student) use ($requirements, &$sent) {
                $internship = $student->currentInternship;
                $stages = ! $internship || $internship->status === 'pre_deployment'
                    ? ['pre_deployment']
                    : ($internship->hasCompletedDeployment() ? ['pre_deployment', 'active', 'post_deployment'] : ['pre_deployment', 'active']);
                $docs = $internship?->documents->keyBy('document_requirement_id') ?? collect();

                $outstanding = $requirements->whereIn('stage', $stages)
                    ->filter(fn ($r) => ! $docs->has($r->id) || $docs[$r->id]->status === 'returned')
                    ->pluck('name');

                if ($outstanding->isEmpty()) {
                    return;
                }

                Notification::create([
                    'user_id' => $student->user_id,
                    'type' => 'document_status',
                    'title' => 'Reminder: forms to submit',
                    'message' => 'Please submit or correct: '.$outstanding->take(6)->implode(', ').($outstanding->count() > 6 ? ', and '.($outstanding->count() - 6).' more' : '').'.',
                    'url' => route('student.documents', [], false),
                ]);
                $sent++;
            });

        return back()->with('status', $sent
            ? "Reminder sent to {$sent} ".\Illuminate\Support\Str::plural('student', $sent).'.'
            : 'Nothing to remind - the selected students have no missing or returned forms (or are in another program).');
    }

    /**

     * Approve or return a document. Unchanged - still used directly for

     * plain-upload requirements. For structured-field requirements this

     * only makes sense with decision=returned; approval instead happens

     * by way of printForSigning() + markCompleted() below.

     */

    public function review(Request $request, Document $document): RedirectResponse

    {

        $instructor = auth()->user()->instructor;

        $this->authorizeInstructor($document, $instructor);

        abort_unless(in_array($document->requirement->audience, ['student', 'coordinator'], true), 403);
        abort_if(in_array($document->requirement->code, \App\Support\EvaluationRouting::studentToHteCodes(), true), 403, 'This evaluation is delivered to the Host Training Establishment, not reviewed here.');

        // A repeated Return submission can happen if the browser resubmits the

        // same form after the first request already changed the document to

        // returned. Treat that as an idempotent action instead of throwing 422.

        if ($document->status === 'returned' && $request->input('decision') === 'returned') {

            return redirect()

                ->to($this->backUrl($document))

                ->with('status', 'Document is already returned to the student.');

        }



        // A form can be returned while waiting for review, and also while it
        // is out for signing (printed) if the instructor spots an error
        // before the signed copy comes back. Approving still needs review status.
        $returnable = ['submitted', 'for_review', 'printed', 'completed'];
        // Students print their own forms: a form approved for printing
        // (printed) or whose signed copy was uploaded (completed) can still
        // be returned. The signed copy itself is approved with "complete".
        abort_unless(in_array($document->status, $returnable, true), 422, 'This document is not awaiting review.');
        abort_if(in_array($document->status, ['printed', 'completed'], true) && $request->input('decision') !== 'returned', 422, 'A form in the signing step can only be returned here.');



        // Print-and-sign forms do not support direct approval, but they can

        // still be returned to the student when the instructor finds an

        // error or missing information. A returned print-and-sign form can

        // then be corrected and submitted again.

        $allowedDecisions = $document->requirement->workflow_mode === 'print_sign'

            ? ['returned']

            : ['approved', 'returned'];



        $validated = $request->validate([

            'decision' => ['required', 'in:'.implode(',', $allowedDecisions)],

            'remarks' => ['nullable', 'string', 'max:2000', 'required_if:decision,returned'],

        ]);



        if ($validated['decision'] === 'approved' && ($blocked = \App\Support\AutoDeployment::blockedReason($document))) {
            return redirect()
                ->to($this->backUrl($document))
                ->withErrors(['placement' => $blocked]);
        }

        DocumentReview::create([

            'document_id' => $document->id,

            'instructor_id' => $instructor->id,

            'decision' => $validated['decision'],

            'remarks' => $validated['remarks'] ?? null,

            'reviewed_at' => now(),

        ]);



        // Returning a signed copy clears it, so the student can correct the
        // form, print it again and upload the new signed copy.
        if ($validated['decision'] === 'returned' && $document->requirement->workflow_mode === 'print_sign' && $document->file_path) {
            $oldFile = $document->file_path;
            $document->update(['file_path' => null, 'original_filename' => null, 'completed_at' => null]);
            Storage::delete($oldFile);
        }

        $document->update(['status' => $validated['decision']]);



        $placement = null;

        if ($validated['decision'] === 'approved') {

            \App\Support\PreDeployment::notifyCoordinatorsIfComplete($document->internship);

            // An accepted Application for OJT places the student at the
            // firm they chose - no manual deployment assignment.
            $placement = \App\Support\AutoDeployment::fromAcceptedApplication($document);

        }



        $this->notifyStudent($document, $validated['decision'] === 'approved'

            ? "{$document->requirement->name} approved"

            : "{$document->requirement->name} returned",

            $validated['decision'] === 'approved'

                ? "Your {$document->requirement->name} has been approved by your subject instructor."

                : "Your {$document->requirement->name} was returned by your subject instructor."

                    .($validated['remarks'] ? ' Remarks: '.$validated['remarks'] : ''), $validated['decision'] === 'returned');



        return redirect()

            ->to($this->backUrl($document))

            ->with('status', trim('Document '.$validated['decision'].'. '.($placement ?? '')));

    }



    /**

     * Instructor has checked the student's entered data on screen and is

     * satisfied with it - prints it for physical signature routing. This

     * is the "approval" step for structured-field forms; there is no

     * separate approve click before this.

     */

    public function printForSigning(Document $document): RedirectResponse

    {

        $instructor = auth()->user()->instructor;

        $this->authorizeInstructor($document, $instructor);

        abort_unless($document->requirement->audience === 'student', 403);

        abort_unless($document->requirement->workflow_mode === 'print_sign', 422, 'This form uses direct review and does not require printing for signatures.');

        abort_unless(in_array($document->status, ['submitted', 'for_review'], true), 422, 'Only a form awaiting review can be printed for signing.');



        if ($blocked = \App\Support\AutoDeployment::blockedReason($document)) {
            return redirect()
                ->to($this->backUrl($document))
                ->withErrors(['placement' => $blocked]);
        }

        $document->update(['status' => 'printed', 'printed_at' => now()]);

        // Print for Signing is how the instructor accepts a print-and-sign
        // form, so an accepted Application for OJT creates the deployment.
        $placement = \App\Support\AutoDeployment::fromAcceptedApplication($document);



        $this->notifyStudent($document, "{$document->requirement->name} approved for printing",
            "Your {$document->requirement->name} looks good. Print it from My Documents, have it signed by the signatories, then upload the signed copy.", true);

        return redirect()
            ->to($this->backUrl($document))
            ->with('status', trim('Approved for printing - the student will print it, have it signed and upload the signed copy. '.($placement ?? '')));

    }



    /**

     * A print-friendly, view-only page of the student's entered data, for

     * the instructor to physically print and route for signature.

     */

    public function printable(Document $document): View|\Illuminate\Http\Response

    {

        $instructor = auth()->user()->instructor;

        $this->authorizeViewer($document, $instructor);



        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user');



        // Each of these requirements has a dedicated template matching its

        // real official PSU form layout exactly. Anything not listed here

        // still falls back to the generic label/value printable view below.

        $view = self::printableView($document);



        // The instructor (and coordinator) only view and download forms - the
        // student is the one who prints them for signing. So no print dialog
        // or Print button here; ?download=1 saves the same page as a PDF.
        $html = view($view, ['document' => $document, 'autoPrint' => false])->render();
        $html = str_replace('<body onload="window.print()">', '<body>', $html);
        $html = preg_replace('#<button class="no-print" onclick="window\.print\(\)">[^<]*</button>#', '', $html);

        if (request()->boolean('download')) {
            $name = trim(preg_replace('#[\\\\/:*?"<>|]+#', '-', $document->requirement->name
                .($document->packet_number ? ' - Packet '.$document->packet_number : '')
                .' - '.$document->internship->student->user->name));
            $script = view('student.documents._pdf-download', ['filename' => $name.'.pdf'])->render();
            $html = str_ireplace('</body>', $script.'</body>', $html);
        }

        return response($html);

    }



    /**

     * The signed physical copies have come back from the signatories.

     * Marking this Completed is what unlocks the student's one-time

     * upload of the scanned, signed document.

     */

    /** The official printable template of a form (also used for the student's Print). */
    public static function printableView(Document $document): string
    {
        $dedicatedViews = [
            'APPLICATION_FOR_OJT' => 'instructor.documents.printable-application-for-ojt',
            'CERTIFICATE_OF_UNITS_EARNED' => 'instructor.documents.printable-certificate-units-earned',
            'ENDORSEMENT_LETTER' => 'instructor.documents.printable-endorsement-letter',
            'PARENT_CONSENT' => 'instructor.documents.printable-consent-waiver',
            'OJT_RESUME' => 'instructor.documents.printable-ojt-resume',
            'INTERNSHIP_AGREEMENT' => 'instructor.documents.printable-internship-agreement',
            'INTERNSHIP_ACADEMIC_PRACTICUM_COURSE' => 'instructor.documents.printable-internship-academic-practicum',
            'DAILY_JOURNAL' => 'instructor.documents.printable-daily-journal',
            'WEEKLY_REPORT' => 'instructor.documents.printable-weekly-report',
            'INTERNSHIP_TIME_FRAME' => 'instructor.documents.printable-internship-time-frame',
            'STUDENT_FEEDBACK_FORM' => 'instructor.documents.printable-student-feedback',
            'STUDENT_HTE_EVALUATION' => 'instructor.documents.printable-student-hte-evaluation',
            'INTERNSHIP_RELEASE_FORM' => 'instructor.documents.printable-internship-release-form',
            'TRAINING_LIABILITY' => 'instructor.documents.printable-training-agreement',
            'EVALUATION_FORM' => 'instructor.documents.printable-form13',
            'TRAINING_SUPERVISOR_FEEDBACK' => 'instructor.documents.printable-training-supervisor-feedback',
            'PARTNER_AGENCY_EVAL_SELF' => 'instructor.documents.printable-partner-agency-eval-self',
            'PARTNER_AGENCY_EVAL_INSTRUCTOR' => 'instructor.documents.printable-partner-agency-eval-instructor',
        ];
                return $dedicatedViews[$document->requirement->code] ?? 'instructor.documents.printable';
    }

    public function markCompleted(Document $document): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        $this->authorizeInstructor($document, $instructor);
        abort_unless($document->requirement->workflow_mode === 'print_sign', 403);
        abort_unless($document->status === 'completed' && $document->file_path, 422, 'Waiting for the student to upload the signed copy.');

        // Final step of print-and-sign: the student printed the form, had it
        // signed and uploaded the scan; the instructor approves that copy.
        // This does NOT complete the student's internship/deployment.
        DocumentReview::create([
            'document_id' => $document->id,
            'instructor_id' => $instructor->id,
            'decision' => 'approved',
            'remarks' => null,
            'reviewed_at' => now(),
        ]);
        $document->update(['status' => 'approved']);

        \App\Support\PreDeployment::notifyCoordinatorsIfComplete($document->internship);

        $this->notifyStudent($document, "{$document->requirement->name} approved",
            "Your signed {$document->requirement->name} has been approved by your subject instructor.");

        return redirect()
            ->to($this->backUrl($document))
            ->with('status', 'Signed copy approved. Internship completion is unchanged.');
    }

    public function download(Document $document): StreamedResponse

    {

        $instructor = auth()->user()->instructor;

        $this->authorizeViewer($document, $instructor);

        abort_unless($document->file_path && Storage::exists($document->file_path), 404);



        return Storage::download($document->file_path, $document->original_filename);

    }



    /**

     * Opens the uploaded file (an image, most commonly a scanned form or a

     * medical certificate photo) directly in the browser tab instead of

     * forcing a download - same file as download(), just served inline.

     */

    public function view(Document $document): StreamedResponse

    {

        $instructor = auth()->user()->instructor;

        $this->authorizeViewer($document, $instructor);

        abort_unless($document->file_path && Storage::exists($document->file_path), 404);



        return Storage::response($document->file_path, $document->original_filename, [], 'inline');

    }



    /**
     * Opens a file attached to a form (Form 1's Medical Certificate or
     * Psychological Test Result). Anyone who may view the form may open it.
     */
    public function attachment(\App\Models\DocumentAttachment $attachment): StreamedResponse
    {
        $instructor = auth()->user()->instructor;
        $this->authorizeViewer($attachment->document, $instructor);
        abort_unless(Storage::exists($attachment->file_path), 404);

        return Storage::response($attachment->file_path, $attachment->original_filename, [], $attachment->isPreviewable() ? 'inline' : 'attachment');
    }

    /**
     * Full view of one submitted form (Google Classroom style): the form or
     * file on the left, and on the right the files, previous remarks and the
     * Return / Approve / Print for signing actions with a remarks box. Arrows
     * move through this student's other submitted forms.
     */
    public function show(Document $document): View
    {
        $instructor = auth()->user()->instructor;
        $this->authorizeViewer($document, $instructor);
        $document->load('requirement.fieldDefinitions', 'internship.student.user', 'internship.student.program', 'latestReview', 'uploader', 'attachments');

        abort_if(in_array($document->requirement->code, \App\Support\EvaluationRouting::studentToHteCodes(), true), 404);

        // This student's other submitted forms, in checklist order.
        $siblings = Document::where('internship_id', $document->internship_id)
            ->whereNotNull('status')
            ->whereHas('requirement', fn ($r) => $r->where('is_active', true)->where('is_required', true)
                ->whereIn('audience', ['student', 'coordinator'])
                ->whereNotIn('code', \App\Support\EvaluationRouting::studentToHteCodes()))
            ->with('requirement')
            ->get()
            ->sortBy(fn ($d) => sprintf('%d|%05d|%s|%05d',
                ['pre_deployment' => 1, 'active' => 2, 'post_deployment' => 3][$d->requirement->stage] ?? 9,
                $d->requirement->display_order ?? 0, $d->requirement->name, $d->packet_number ?? 0))
            ->values();

        $position = $siblings->search(fn ($d) => $d->id === $document->id);
        $prevDocument = $position !== false && $position > 0 ? $siblings[$position - 1] : null;
        $nextDocument = $position !== false && $position < $siblings->count() - 1 ? $siblings[$position + 1] : null;

        $reviews = DocumentReview::where('document_id', $document->id)->with('instructor.user')->latest('reviewed_at')->get();

        return view('instructor.documents.viewer', [
            'document' => $document,
            'student' => $document->internship->student,
            'canAct' => $instructor->canActOn($document->internship),
            'prevDocument' => $prevDocument,
            'nextDocument' => $nextDocument,
            'position' => ['index' => $position === false ? 1 : $position + 1, 'total' => max($siblings->count(), 1)],
            'reviews' => $reviews,
        ]);
    }

    /** After an action: back to the viewer if it came from there, else the student's page. */
    private function backUrl(Document $document): string
    {
        return request()->input('return_to') === 'viewer'
            ? route('instructor.documents.show', $document)
            : route('instructor.documents', ['student' => $document->internship->student_id, 'form' => $document->document_requirement_id]);
    }

    /** Acting on a document: own program only (the coordinator included). */
    private function authorizeInstructor(Document $document, $instructor): void
    {
        abort_unless($instructor->canActOn($document->internship), 403);
    }

    /** Opening / printing / downloading: anything the viewer can see. */
    private function authorizeViewer(Document $document, $instructor): void
    {
        abort_unless($instructor->canView($document->internship), 403);
    }



    private function notifyStudent(Document $document, string $title, string $message, bool $actionNeeded = false): void

    {

        Notification::create([

            'user_id' => $document->internship->student->user->id,

            'type' => 'document_status',

            'title' => $title,

            'message' => $message,
            'resolve_key' => $actionNeeded ? 'document:'.$document->id : null,

        ]);



        // HTE-submitted official post-OJT forms also need the result sent

        // back to the representative who actually completed the form.

        $uploaderId = $document->uploaded_by_user_id;

        if ($uploaderId && (int) $uploaderId !== (int) $document->internship->student->user_id) {

            Notification::create([

                'user_id' => $uploaderId,

                'type' => 'document_status',

                'title' => $title,

                'message' => $message,
            'resolve_key' => $actionNeeded ? 'document:'.$document->id : null,

            ]);

        }

    }

}
