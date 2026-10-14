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



        $advisees = $instructor->advisees()

            ->with('user')

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



        $selectedId = $request->query('student', $advisees->first()?->id);

        $student = $advisees->firstWhere('id', (int) $selectedId) ?? $advisees->first();



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
                ? $internship->documents()->with(['latestReview', 'fieldValues', 'entries.values', 'requirement'])->get()
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



        return view('instructor.documents.index', compact(

            'advisees', 'student', 'internship', 'requirementsByStage', 'documentsByRequirement', 'fieldWarnings', 'form19Packets'

        ));

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

                ->route('instructor.documents', ['student' => $document->internship->student_id])

                ->with('status', 'Document is already returned to the student.');

        }



        abort_unless(in_array($document->status, ['submitted', 'for_review'], true), 422, 'This document is not awaiting review.');



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



        DocumentReview::create([

            'document_id' => $document->id,

            'instructor_id' => $instructor->id,

            'decision' => $validated['decision'],

            'remarks' => $validated['remarks'] ?? null,

            'reviewed_at' => now(),

        ]);



        $document->update(['status' => $validated['decision']]);



        if ($validated['decision'] === 'approved') {

            \App\Support\PreDeployment::notifyCoordinatorsIfComplete($document->internship);

        }



        $this->notifyStudent($document, $validated['decision'] === 'approved'

            ? "{$document->requirement->name} approved"

            : "{$document->requirement->name} returned",

            $validated['decision'] === 'approved'

                ? "Your {$document->requirement->name} has been approved by your subject instructor."

                : "Your {$document->requirement->name} was returned by your subject instructor."

                    .($validated['remarks'] ? ' Remarks: '.$validated['remarks'] : ''), $validated['decision'] === 'returned');



        return redirect()

            ->route('instructor.documents', ['student' => $document->internship->student_id])

            ->with('status', 'Document '.$validated['decision'].'.');

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



        $document->update(['status' => 'printed', 'printed_at' => now()]);



        return redirect()

            ->route('instructor.documents', ['student' => $document->internship->student_id])

            ->with('status', 'Marked as printed - out for signing.');

    }



    /**

     * A print-friendly, view-only page of the student's entered data, for

     * the instructor to physically print and route for signature.

     */

    public function printable(Document $document): View

    {

        $instructor = auth()->user()->instructor;

        $this->authorizeInstructor($document, $instructor);



        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user');



        // Each of these requirements has a dedicated template matching its

        // real official PSU form layout exactly. Anything not listed here

        // still falls back to the generic label/value printable view below.

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



        $view = $dedicatedViews[$document->requirement->code] ?? 'instructor.documents.printable';



        return view($view, compact('document'));

    }



    /**

     * The signed physical copies have come back from the signatories.

     * Marking this Completed is what unlocks the student's one-time

     * upload of the scanned, signed document.

     */

    public function markCompleted(Document $document): RedirectResponse

    {

        $instructor = auth()->user()->instructor;

        $this->authorizeInstructor($document, $instructor);

        abort_unless($document->requirement->workflow_mode === 'print_sign', 403);

        abort_unless($document->status === 'printed', 422, 'Only a form that has been printed for signing can be unlocked for signed-copy upload.');
        abort_if($document->file_path, 422, 'A signed copy has already been uploaded for this form.');

        // This completes the physical-signature handoff only. It does NOT
        // complete the student's internship/deployment.
        $document->update(['status' => 'completed', 'completed_at' => now()]);



        $this->notifyStudent($document, "{$document->requirement->name} ready for upload",

            "Your signed {$document->requirement->name} is ready - upload the scanned copy from My Documents.", true);



        return redirect()

            ->route('instructor.documents', ['student' => $document->internship->student_id])

            ->with('status', 'Signed-copy upload unlocked for the student. Internship completion is unchanged.');

    }



    public function download(Document $document): StreamedResponse

    {

        $instructor = auth()->user()->instructor;

        $this->authorizeInstructor($document, $instructor);

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

        $this->authorizeInstructor($document, $instructor);

        abort_unless($document->file_path && Storage::exists($document->file_path), 404);



        return Storage::response($document->file_path, $document->original_filename, [], 'inline');

    }



    private function authorizeInstructor(Document $document, $instructor): void

    {

        abort_unless(

            $document->internship->campus_id === $instructor->campus_id

                && $document->internship->program_id === $instructor->program_id,

            403

        );

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
