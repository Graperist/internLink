<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentFieldValue;
use App\Models\DocumentEntry;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Whether a requirement should be visible/usable for this internship yet.
     * Showing post-deployment forms while the student is still deployed is
     * confusing, because those forms cannot be completed until the
     * deployment is finished.
     */
    private function isRequirementAvailable(DocumentRequirement $requirement, $internship): bool
    {
        if ($requirement->stage === 'pre_deployment') {
            return true;
        }

        if (! $internship || $internship->status === 'pre_deployment') {
            return false;
        }

        if ($requirement->stage === 'post_deployment') {
            return $internship->hasCompletedDeployment();
        }

        return true; // 'active' stage
    }

    /** Prevent route-model binding from exposing another campus or another party's form. */
    private function authorizeStudentRequirement(DocumentRequirement $requirement, $student): void
    {
        abort_unless($student, 403, 'Your student profile is not linked to this account.');
        abort_unless(
            $requirement->campus_id === $student->campus_id
                && $requirement->audience === 'student'
                && $requirement->is_active,
            404
        );
    }

    /**
     * Figure 22 - My Documents. Requirements with structured field
     * definitions (e.g., Application for OJT) now follow the real
     * workflow: fill in-system -> instructor reviews -> print for
     * signing -> instructor marks completed -> student's one-time signed
     * upload unlocks. Requirements without field definitions keep the
     * original plain upload behavior unchanged.
     */
    public function index(): View
    {
        $student = auth()->user()->student;
        if ($student->currentInternship) {
            // Close the deployment if its end date has passed, then reload it.
            if (\App\Support\DeploymentLifecycle::completeEnded(null, $student->currentInternship->id)) {
                $student->unsetRelation('currentInternship');
            }
        }
        $internship = $student->currentInternship;

        // Inactive requirements (legacy forms, Pre-/Post-OJT Seminar) are
        // not part of the live checklist, even if an old document exists.
        // Only forms the student themself deals with: their own forms plus
        // coordinator-handled ones shown for status (e.g. the MOA). Forms that
        // belong to the HTE (13, 21, 23b) or the instructor (23a) are not listed.
        $requirements = DocumentRequirement::where('campus_id', $student->campus_id)
            ->where('is_active', true)
            ->whereIn('audience', ['student', 'coordinator'])
            ->with('fieldDefinitions')
            ->get()
            // In process order: pre-deployment, then active (e.g. the weekly
            // report), then post-deployment. Within a stage the forms follow
            // their form number (1, 2, 3, 6, 11, 12, 15, 18 - "23a" after 23);
            // items without a number come after the numbered forms.
            ->sortBy(fn ($req) => [
                ['pre_deployment' => 1, 'active' => 2, 'post_deployment' => 3][$req->stage] ?? 4,
                self::formNumberSortKey($req->form_number),
                $req->display_order ?? 0,
                $req->name,
            ])
            ->values();

        // Stage-gated (see isRequirementAvailable): pre-deployment forms are always
        // visible, active-stage forms once deployment has started, and
        // post-deployment forms only after the internship is completed. The
        // Weekly Journal is the one exception - it is written during the
        // deployment, so it shows as soon as the student is deployed.
        $requirements = $requirements
            ->filter(fn ($req) => $this->isRequirementAvailable($req, $internship))
            ->values();

        $docsByCode = $internship
            ? $internship->documents()->with(['requirement', 'latestReview', 'entries', 'attachments', 'reviews' => fn ($q) => $q->latest('reviewed_at')])->get()->keyBy(fn ($d) => $d->requirement->code)
            : collect();

        $checklist = $requirements->map(fn ($req) => [
            'requirement' => $req,
            'document' => $docsByCode->get($req->code),
        ]);

        // Like Document Review: the forms on the left, one open on the
        // right. ?form= picks it; otherwise the first form that needs the
        // student (returned, signed copy to upload, or not started yet).
        $selectedId = (int) request('form');
        if (! $checklist->contains(fn ($row) => $row['requirement']->id === $selectedId)) {
            $needsStudent = $checklist->first(fn ($row) => ! $row['document']
                || in_array($row['document']->status, ['returned', 'printed', 'draft'], true));
            $selectedId = ($needsStudent ?? $checklist->first())['requirement']->id ?? null;
        }

        return view('student.documents', compact('checklist', 'internship', 'selectedId'));
    }

    /**
     * The in-system data-entry form for a structured requirement. Shows a
     * blank form the first time, or pre-fills previously entered values
     * if the document was returned for correction.
     */
    public function create(DocumentRequirement $requirement): View
    {
        abort_unless($requirement->hasStructuredFields(), 404);

        $student = auth()->user()->student;
        $this->authorizeStudentRequirement($requirement, $student);
        $internship = $student->currentInternship;
        abort_unless($internship, 422, 'You need an internship record before filling out forms - contact your coordinator.');
        abort_unless($this->isRequirementAvailable($requirement, $internship), 403, 'This requirement is not available yet.');

        $document = $internship->documents()
            ->where('document_requirement_id', $requirement->id)
            ->with('fieldValues', 'attachments')
            ->first();

        // Once printed, the entered data is what physically went out for
        // signature - editing it here would silently disagree with the
        // signed paper copy, so further changes only happen through a
        // Return-to-student decision, not by revisiting this form.
        abort_if($document && in_array($document->status, ['printed', 'completed', 'approved']), 403,
            'This form has already been printed for signing and can no longer be edited here.');

        // Forms 20 and 23c go straight to the HTE's Evaluations Received, so a
        // delivered copy is final - the HTE could already have read it.
        abort_if($document
            && in_array($requirement->code, \App\Support\EvaluationRouting::studentToHteCodes(), true)
            && \App\Support\EvaluationRouting::isSubmitted($document), 403,
            'This evaluation was already sent to your Host Training Establishment and can no longer be edited.');

        if ($requirement->code === 'CERTIFICATE_OF_UNITS_EARNED') {
            return view('student.documents.certificate-units-earned', compact('requirement', 'document'));
        }

        // The "Name of the Firm/Agency/Company" field (hte_name) is a plain
        // text box by default, but a student shouldn't be free-typing a
        // company name here - they pick from their program's Partner Agency
        // list for the current term (kept by their subject instructor), so
        // what they submit always matches a real partner. PM-PSU-03-AA-06,
        // Note 2: present the list of partner agencies, let the student choose.
        $htes = ($requirement->fieldDefinitions->contains('field_key', 'hte_name')
                || $requirement->fieldDefinitions->contains('field_key', 'company_name'))
            ? $this->partnerAgencyChoices($student, $internship)
            : collect();

        // Slots per agency for the student's program this term, so a full
        // agency shows as full and can't be chosen.
        [$termYear, $termSemester] = \App\Support\Term::current();
        $hteAvailability = $htes->isNotEmpty()
            ? \App\Http\Controllers\Instructor\HteController::availability($student->campus_id, $student->program_id, $termYear, $termSemester)
            : [];

        // Course/Major defaults for this student's own program. Each program
        // row is one course + optional major (see the coordinator's Programs
        // page), so e.g. BSBA-OM and BSBA-FM both show the course "Business
        // Administration" with Major picking between them - matching the
        // real paper form's Course/Major split.
        $courseMajorDefaults = [
            'course' => $student->program?->courseTitle() ?? '',
            'major' => $student->program?->majorLabel() ?? 'N/A',
        ];

        // Course choices come from the programs the coordinator set up for
        // this campus (Programs page), not a fixed list.
        $campusPrograms = \App\Models\Program::where('campus_id', $student->campus_id)->get();
        $courseOptions = $campusPrograms
            ->map(fn ($program) => $program->courseTitle())
            ->push($courseMajorDefaults['course'])
            ->filter()->unique()->sort()->values()->all();
        $majorOptionsByCourse = $campusPrograms
            ->groupBy(fn ($program) => $program->courseTitle())
            ->map(fn ($programs) => $programs->map(fn ($program) => $program->majorLabel())
                ->reject(fn ($major) => $major === 'N/A')->unique()->sort()->values())
            ->filter(fn ($majors) => $majors->isNotEmpty())
            ->all();

        // Endorsement Letter defaults come from the student's current
        // deployment when an HTE has already been selected. The recipient
        // details remain editable because the letter may be addressed to a
        // specific officer rather than the HTE contact person on file.
        $currentHte = $internship?->deployment?->hte;

        // The student title can be determined from the gender already
        // entered in the student's OJT Resume, when that form has been
        // completed. We do not infer it from the student's name.
        $studentTitle = '';
        $resumeRequirement = DocumentRequirement::where('code', 'OJT_RESUME')->first();
        if ($resumeRequirement) {
            $resumeDocument = $internship->documents()
                ->where('document_requirement_id', $resumeRequirement->id)
                ->with('fieldValues')
                ->first();

            $gender = strtolower(trim((string) ($resumeDocument?->fieldValue('gender') ?? '')));
            $studentTitle = match ($gender) {
                'male' => 'Mr.',
                'female' => 'Ms.',
                default => '',
            };
        }

        // A recipient's honorific cannot be reliably inferred from a name
        // alone. When the HTE contact person already contains a common
        // honorific, use it as the default; otherwise the student chooses
        // Sir or Madam in the fill-up form.
        $recipientSalutation = '';
        $contactPerson = trim((string) ($currentHte?->contact_person ?? ''));
        if (preg_match('/^\s*(?:mr\.?)\b/i', $contactPerson)) {
            $recipientSalutation = 'Sir';
        } elseif (preg_match('/^\s*(?:ms\.?|mrs\.?|miss)\b/i', $contactPerson)) {
            $recipientSalutation = 'Madam';
        }

        $endorsementDefaults = [
            'endorsement_date' => now()->format('Y-m-d'),
            'company_name' => $currentHte?->name ?? '',
            'recipient_name' => $currentHte?->contact_person ?? '',
            'recipient_office' => $currentHte?->name ?? '',
            'recipient_address' => $currentHte?->address ?? '',
            'recipient_salutation' => $recipientSalutation,
            'student_title' => $studentTitle,
            'course' => $courseMajorDefaults['course'],
            'major' => $courseMajorDefaults['major'],
        ];

        // Internship Agreement uses the coordinator's HTE master record and
        // the student's program as its source of truth. These values are
        // displayed read-only in the student form and re-derived server-side
        // on submission so they cannot be tampered with in the browser.
        $agreementRequiredHours = (int) ($student->program?->required_hours
            ?? $internship->required_hours
            ?? 0);

        $agreementStudentAddress = $document?->fieldValue('address');
        if (blank($agreementStudentAddress)) {
            $applicationRequirement = DocumentRequirement::where('code', 'APPLICATION_FOR_OJT')->first();
            if ($applicationRequirement) {
                $applicationDocument = $internship->documents()
                    ->where('document_requirement_id', $applicationRequirement->id)
                    ->with('fieldValues')
                    ->first();
                $agreementStudentAddress = $applicationDocument?->fieldValue('current_address')
                    ?: $applicationDocument?->fieldValue('home_address');
            }
        }

        if (blank($agreementStudentAddress)) {
            $resumeDocument = $internship->documents()
                ->whereHas('requirement', fn ($query) => $query->where('code', 'OJT_RESUME'))
                ->with('fieldValues')
                ->first();
            $agreementStudentAddress = $resumeDocument?->fieldValue('address');
        }

        return view('student.documents.fill', compact(
            'requirement', 'document', 'htes', 'courseMajorDefaults', 'courseOptions', 'majorOptionsByCourse', 'hteAvailability', 'endorsementDefaults', 'agreementRequiredHours', 'agreementStudentAddress',
            'student', 'internship'
        ));
    }

    /**
     * Saves the student's entered field values and (re)submits the form
     * for instructor review. Reused for both the first submission and a
     * correction after a Return decision.
     */
    public function storeFields(Request $request, DocumentRequirement $requirement): RedirectResponse
    {
        abort_unless($requirement->hasStructuredFields(), 404);

        $student = auth()->user()->student;
        $this->authorizeStudentRequirement($requirement, $student);
        $internship = $student->currentInternship;
        abort_unless($internship, 422, 'You need an internship record before filling out forms - contact your coordinator.');
        abort_unless($this->isRequirementAvailable($requirement, $internship), 403, 'This requirement is not available yet.');

        // Internship Agreement is intentionally sourced from trusted
        // records: the student picks an HTE, while address, representative,
        // representative position, and required hours are filled by the
        // server from the selected HTE/program. This prevents a student from
        // submitting altered company or hours information from dev tools.
        if ($requirement->code === 'INTERNSHIP_AGREEMENT') {
            $selected = $request->validate([
                'hte_id' => [
                    'required',
                    'integer',
                    Rule::exists('htes', 'id')->where(fn ($query) =>
                        $query->where('campus_id', $student->campus_id)
                    ),
                ],
                'address' => ['required', 'string', 'max:2000'],
            ]);

            $hte = Hte::where('campus_id', $student->campus_id)
                ->find($selected['hte_id']);

            if (! $hte) {
                return back()->withInput()->withErrors([
                    'hte_id' => 'Please select a valid partner Host Training Establishment.',
                ]);
            }

            if (blank($hte->address) || blank($hte->contact_person) || blank($hte->representative_position)) {
                return back()->withInput()->withErrors([
                    'hte_id' => 'The selected HTE is missing address, representative name, or representative position. Please ask your subject instructor to complete it in Partner Agencies first.',
                ]);
            }

            $requiredHours = (int) ($student->program?->required_hours
                ?? $internship->required_hours
                ?? 0);

            if ($requiredHours <= 0) {
                return back()->withInput()->withErrors([
                    'hte_id' => 'The required internship hours are not configured for this student’s program. Please ask the Coordinator to configure the program first.',
                ]);
            }

            $wasReturned = $internship->documents()
                ->where('document_requirement_id', $requirement->id)
                ->where('status', 'returned')
                ->exists();

            $agreementValues = [
                'course' => $student->program?->courseTitle() ?? '',
                'major' => $student->program?->majorLabel() ?? 'N/A',
                'address' => $selected['address'],
                'company_name' => $hte->name,
                'company_address' => $hte->address,
                'company_representative' => $hte->contact_person,
                'representative_position' => $hte->representative_position,
                'required_hours' => (string) $requiredHours,
            ];

            $document = DB::transaction(function () use ($requirement, $internship, $agreementValues) {
                $document = Document::updateOrCreate(
                    [
                        'internship_id' => $internship->id,
                        'document_requirement_id' => $requirement->id,
                    ],
                    [
                        'uploaded_by_user_id' => auth()->id(),
                        'status' => 'submitted',
                        'submitted_at' => now(),
                    ]
                );

                foreach ($agreementValues as $key => $value) {
                    DocumentFieldValue::updateOrCreate(
                        ['document_id' => $document->id, 'field_key' => $key],
                        ['value' => $value]
                    );
                }

                return $document;
            });

            $verb = $wasReturned ? 'resubmitted' : 'submitted';
            \App\Models\Instructor::where('campus_id', $internship->campus_id)
                ->where('program_id', $internship->program_id)
                ->get()
                ->each(fn ($instructor) => \App\Models\Notification::create([
                    'user_id' => $instructor->user_id,
                    'type' => 'document_status',
                    'title' => "{$student->user->name} {$verb} {$requirement->name}",
                    'message' => 'Awaiting your review.',
                    'url' => route('instructor.documents', ['student' => $student->id], false),
                    'resolve_key' => 'document:'.$document->id,
                ]));

            return redirect()->route('student.documents')
                ->with('status', 'Internship Agreement submitted for instructor review.');
        }

        // Consent and Waiver: the student only chooses the partner agency,
        // confirms the address, and picks the signing date. Name, course,
        // required hours, and "signed at" come from trusted records (user,
        // program, campus) so they cannot be altered from dev tools.
        if ($requirement->code === 'PARENT_CONSENT') {
            $selected = $request->validate([
                'address' => ['required', 'string', 'max:2000'],
                'company_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::exists('htes', 'name')->where(fn ($query) =>
                        $query->where('campus_id', $student->campus_id)
                    ),
                ],
                'signed_date' => ['required', 'date'],
            ] + $this->attachmentRules($requirement, $internship), self::ATTACHMENT_MESSAGES);

            $requiredHours = (int) ($student->program?->required_hours
                ?? $internship->required_hours
                ?? 0);

            if ($requiredHours <= 0) {
                return back()->withInput()->withErrors([
                    'company_name' => 'The required internship hours are not configured for this student\'s program. Please ask the Coordinator to configure the program first.',
                ]);
            }

            $signedCity = $student->campus?->address ?: $student->campus?->name;

            if (blank($signedCity)) {
                return back()->withInput()->withErrors([
                    'signed_date' => 'Your campus location is not configured. Please ask the Coordinator to set the campus address in Campus Settings.',
                ]);
            }

            $consentValues = [
                'full_name' => $student->user->name,
                'address' => $selected['address'],
                'course' => $student->program?->courseTitle() ?? '',
                'required_hours' => (string) $requiredHours,
                'company_name' => $selected['company_name'],
                'signed_city' => $signedCity,
                'signed_date' => \Carbon\Carbon::parse($selected['signed_date'])->toDateString(),
            ];

            $wasReturned = $internship->documents()
                ->where('document_requirement_id', $requirement->id)
                ->where('status', 'returned')
                ->exists();

            $document = DB::transaction(function () use ($requirement, $internship, $consentValues, $request, $student) {
                $document = Document::updateOrCreate(
                    [
                        'internship_id' => $internship->id,
                        'document_requirement_id' => $requirement->id,
                    ],
                    [
                        'uploaded_by_user_id' => auth()->id(),
                        'status' => 'submitted',
                        'submitted_at' => now(),
                    ]
                );

                foreach ($consentValues as $key => $value) {
                    DocumentFieldValue::updateOrCreate(
                        ['document_id' => $document->id, 'field_key' => $key],
                        ['value' => $value]
                    );
                }

                // The Parent/Guardian Valid ID goes with the consent form.
                $this->saveAttachments($document, $request, $student);

                return $document;
            });

            $verb = $wasReturned ? 'resubmitted' : 'submitted';
            \App\Models\Instructor::where('campus_id', $internship->campus_id)
                ->where('program_id', $internship->program_id)
                ->get()
                ->each(fn ($instructor) => \App\Models\Notification::create([
                    'user_id' => $instructor->user_id,
                    'type' => 'document_status',
                    'title' => "{$student->user->name} {$verb} {$requirement->name}",
                    'message' => 'Awaiting your review.',
                    'url' => route('instructor.documents', ['student' => $student->id], false),
                    'resolve_key' => 'document:'.$document->id,
                ]));

            return redirect()->route('student.documents')
                ->with('status', 'Consent and Waiver submitted for instructor review.');
        }

        $rules = [];
        foreach ($requirement->fieldDefinitions as $field) {
            // These legacy Endorsement Letter fields are no longer part of
            // the student's online form. Keep the DB definitions for
            // backwards compatibility, but never require or save them from
            // the current fill-up workflow.
            if ($requirement->code === 'ENDORSEMENT_LETTER'
                && $field->field_key === 'recipient_position') {
                continue;
            }

            $maxLength = $requirement->code === 'CERTIFICATE_OF_UNITS_EARNED' ? 10000 : 2000;

            $rules[$field->field_key] = $field->field_type === 'rating_scale'
                ? [$field->is_required ? 'required' : 'nullable', 'in:1,2,3,4,5']
                : [$field->is_required ? 'required' : 'nullable', 'string', 'max:'.$maxLength];
        }
        // Files that go with this form (Form 1: Medical Certificate and
        // Psychological Test Result). Required the first time; on a
        // resubmission they are only needed to replace a file.
        $attachmentKinds = DocumentAttachment::kindsFor($requirement->code);
        $rules += $this->attachmentRules($requirement, $internship);

        // Form 1's Academic Credentials table: SEM and SY come from dropdowns
        // (checked here too). The student can add rows; SIGNATURE is signed on paper.
        $extraMessages = [];
        if ($requirement->code === 'APPLICATION_FOR_OJT') {
            for ($i = 1; $i <= \App\Support\Form1Academics::ROWS; $i++) {
                unset($rules["academic_{$i}_signature"]);
            }
            // Rows beyond the five on the form are the ones the student added.
            $rules = array_merge($rules, \App\Support\Form1Academics::rules());
            $extraMessages = \App\Support\Form1Academics::messages();
        }

        $validated = $request->validate($rules, $extraMessages + self::ATTACHMENT_MESSAGES);
        foreach (array_keys($attachmentKinds) as $kind) {
            unset($validated['attachment_'.$kind]);
        }
        if ($requirement->code === 'APPLICATION_FOR_OJT') {
            $validated = \App\Support\Form1Academics::compact($validated);
        }

        abort_if(
            in_array($requirement->code, \App\Support\EvaluationRouting::studentToHteCodes(), true)
                && \App\Support\EvaluationRouting::isSubmitted(
                    $internship->documents()->where('document_requirement_id', $requirement->id)->first()
                ),
            403,
            'This evaluation was already sent to your Host Training Establishment and can no longer be edited.'
        );

        $wasReturned = $internship->documents()
            ->where('document_requirement_id', $requirement->id)
            ->where('status', 'returned')
            ->exists();

        $document = DB::transaction(function () use ($requirement, $internship, $validated, $request, $student) {
            $document = Document::updateOrCreate(
                [
                    'internship_id' => $internship->id,
                    'document_requirement_id' => $requirement->id,
                ],
                [
                    'uploaded_by_user_id' => auth()->id(),
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]
            );

            foreach ($validated as $key => $value) {
                DocumentFieldValue::updateOrCreate(
                    ['document_id' => $document->id, 'field_key' => $key],
                    ['value' => $value]
                );
            }

            // Form 1: rows the student removed (and the old typed-in
            // signature column) are cleared so they don't reappear.
            if ($requirement->code === 'APPLICATION_FOR_OJT') {
                $document->fieldValues()
                    ->where('field_key', 'like', 'academic%')
                    ->whereNotIn('field_key', array_keys($validated))
                    ->delete();
            }

            $this->saveAttachments($document, $request, $student);

            return $document;
        });

        $verb = $wasReturned ? 'resubmitted' : 'submitted';

        if (in_array($requirement->code, \App\Support\EvaluationRouting::studentToHteCodes(), true)) {
            // Form 20 (Student-Intern's Feedback) and Form 23c (Partner Agency
            // evaluation, Student) are addressed to the host training
            // establishment, not to the instructor: they land in the HTE
            // portal's Evaluations Received sidebar.
            $formLabel = \App\Support\EvaluationRouting::FORMS[$requirement->code]['form'];
            $hteId = $internship->deployment?->hte_id;
            \App\Models\HteRepresentative::where('hte_id', $hteId)->pluck('user_id')->each(
                fn ($userId) => \App\Models\Notification::create([
                    'user_id' => $userId,
                    'type' => 'document_status',
                    'title' => "Evaluation received from {$student->user->name} ({$formLabel})",
                    'message' => "{$student->user->name} {$verb} {$requirement->name}. Open Evaluations Received to read it.",
                    'url' => route('hte.evaluations.received', [], false),
                ])
            );
        } else {
            // Notify every instructor scoped to this student's program/campus -
            // there's no strict one-to-one assignment, so more than one
            // instructor may legitimately need to see this.
            \App\Models\Instructor::where('campus_id', $internship->campus_id)
                ->where('program_id', $internship->program_id)
                ->get()
                ->each(fn ($instructor) => \App\Models\Notification::create([
                    'user_id' => $instructor->user_id,
                    'type' => 'document_status',
                    'title' => "{$student->user->name} {$verb} {$requirement->name}",
                    'message' => 'Awaiting your review.',
                    'url' => route('instructor.documents', ['student' => $student->id], false),
                    'resolve_key' => 'document:'.$document->id,
                ]));
        }

        // Advisory only. Format checks (age range, phone format, etc.) and
        // the HTE reference check are both safe to show the student
        // directly, since they're about the student's own entered data.
        // The cross-student duplicate-name check is deliberately never
        // run here - telling a student "this matches another student's
        // entry" would expose that other student's data for no benefit,
        // since there's nothing the student themselves can do with that
        // information. The instructor sees all three checks.
        $fieldWarnings = [];
        foreach ($document->fieldValues as $fieldValue) {
            $warning = \App\Support\DocumentFieldChecker::checkFormat($fieldValue)
                ?? \App\Support\DocumentFieldChecker::checkReferenceOnly($fieldValue, $internship->campus_id);
            if ($warning) {
                $fieldWarnings[] = $warning;
            }
        }

        return redirect()->route('student.documents')
            ->with('status', in_array($requirement->code, \App\Support\EvaluationRouting::studentToHteCodes(), true)
                ? \App\Support\EvaluationRouting::FORMS[$requirement->code]['form'].' submitted and sent to your Host Training Establishment.'
                : 'Form submitted for instructor review.')
            ->with('field_warnings', $fieldWarnings);
    }

    /**
     * The ongoing log view for a repeating requirement (Internship Time Frame
     * and Form 19 Weekly Report) - entries accumulate here.
     * throughout the internship. Once the student is satisfied with the
     * log, "Submit for Review" moves the whole thing into the same
     * submitted -> printed -> completed -> upload cycle as flat forms.
     */
    public function showLog(DocumentRequirement $requirement): View
    {
        abort_unless($requirement->is_repeating, 404);

        $student = auth()->user()->student;
        $this->authorizeStudentRequirement($requirement, $student);
        $internship = $student->currentInternship;
        abort_unless($internship, 422, 'You need an internship record before logging entries - contact your coordinator.');
        abort_unless($this->isRequirementAvailable($requirement, $internship), 403, 'This requirement is not available yet.');

        $query = $internship->documents()
            ->where('document_requirement_id', $requirement->id)
            ->with(['entries.values', 'latestReview'])
            ->when($requirement->code === 'WEEKLY_REPORT', fn ($q) => $q->orderBy('packet_number'));
        $packets = $requirement->code === 'WEEKLY_REPORT' ? $query->get() : collect();
        $entries = collect();
        $document = $requirement->code === 'WEEKLY_REPORT'
            ? $packets->first()
            : $query->first();

        $canEdit = ! $document || ! in_array($document->status, ['printed', 'completed', 'approved']);
        if ($requirement->code === 'WEEKLY_REPORT') {
            $entries = $packets->flatMap(fn ($packet) => $packet->entries)->sortBy('entry_date')->values();
            $document = $packets->first();
            $canEdit = true; // per-packet editability is enforced when each entry is changed
        }

        return view('student.documents.log', compact('requirement', 'document', 'canEdit', 'packets', 'entries', 'internship'));
    }

    public function createEntry(DocumentRequirement $requirement): View
    {
        abort_unless($requirement->is_repeating, 404);

        $student = auth()->user()->student;
        $this->authorizeStudentRequirement($requirement, $student);
        $internship = $student->currentInternship;
        abort_unless($internship, 422, 'You need an internship record before logging entries - contact your coordinator.');
        abort_unless($this->isRequirementAvailable($requirement, $internship), 403, 'This requirement is not available yet.');

        $documents = $internship->documents()->where('document_requirement_id', $requirement->id);
        $document = $documents->first();
        if ($requirement->code !== 'WEEKLY_REPORT') {
            abort_if($document && in_array($document->status, ['printed', 'completed', 'approved']), 403,
                'This log has already been printed for signing and can no longer be edited here.');
        }

        $allDates = \App\Models\DocumentEntry::query()
            ->whereHas('document', fn ($q) => $q->where('internship_id', $internship->id)
                ->where('document_requirement_id', $requirement->id))
            ->pluck('entry_date');
        $weekAnchor = \App\Support\TrainingWeeks::anchorForInternship($internship, $allDates);
        $loggedDates = $allDates->map(fn ($date) => $date->toDateString())->all();

        return view('student.documents.entry-create', compact('requirement', 'weekAnchor', 'loggedDates'));
    }

    public function storeEntry(Request $request, DocumentRequirement $requirement): RedirectResponse
    {
        abort_unless($requirement->is_repeating, 404);

        $student = auth()->user()->student;
        $this->authorizeStudentRequirement($requirement, $student);
        $internship = $student->currentInternship;
        abort_unless($internship, 422, 'You need an internship record before logging entries - contact your coordinator.');
        abort_unless($this->isRequirementAvailable($requirement, $internship), 403, 'This requirement is not available yet.');

        $rules = ['entry_date' => ['required', 'date']];

        // Form 19 is the official Practicum/Internship Weekly Report.
        // It is a weekly report containing one daily row per training day.
        // Its only row fields are the two columns shown on the official form.
        // Do NOT inherit stale field definitions from an older Weekly Report
        // configuration (e.g. job_description, start_date, total_hours).
        if ($requirement->code === 'WEEKLY_REPORT') {
            $rules['tasks_accomplished'] = ['required', 'string', 'max:4000'];
            $rules['knowledge_skills_learned'] = ['required', 'string', 'max:4000'];
        } else {
            if ($requirement->entry_cadence === 'weekly') {
                $rules['entry_end_date'] = ['required', 'date', 'after_or_equal:entry_date'];
            }

            foreach ($requirement->fieldDefinitions as $field) {
                $rules[$field->field_key] = [$field->is_required ? 'required' : 'nullable', 'string', 'max:4000'];
            }
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($requirement, $internship, $validated) {
            if ($requirement->code === 'WEEKLY_REPORT') {
                $allDates = DocumentEntry::query()
                    ->whereHas('document', fn ($q) => $q->where('internship_id', $internship->id)
                        ->where('document_requirement_id', $requirement->id))
                    ->pluck('entry_date');
                $anchor = \App\Support\TrainingWeeks::anchorForInternship($internship, $allDates);
                $date = \Carbon\Carbon::parse($validated['entry_date'])->startOfDay();
                $anchor ??= $date;
                abort_unless($date->greaterThanOrEqualTo($anchor), 422, 'The entry date cannot be earlier than the start of your training period.');
                $weekStart = \App\Support\TrainingWeeks::weekStart($date, $anchor);
                $weekIndex = (int) floor($anchor->diffInDays($weekStart, false) / 7);
                $packetNumber = intdiv(max(0, $weekIndex), 4) + 1;

                $document = Document::firstOrCreate(
                    ['internship_id' => $internship->id, 'document_requirement_id' => $requirement->id, 'packet_number' => $packetNumber],
                    ['uploaded_by_user_id' => auth()->id(), 'status' => 'draft']
                );
            } else {
                $document = Document::firstOrCreate(
                    ['internship_id' => $internship->id, 'document_requirement_id' => $requirement->id],
                    ['uploaded_by_user_id' => auth()->id(), 'status' => 'draft']
                );
            }

            abort_if(in_array($document->status, ['submitted', 'for_review', 'printed', 'completed', 'approved']), 403,
                'This report packet has already entered review/signing and can no longer be edited here.');

            // One entry per day: lock the log so a double-click can't slip two
            // entries for the same date through, then reject a repeated date.
            Document::whereKey($document->id)->lockForUpdate()->first();

            if ($document->entries()->whereDate('entry_date', $validated['entry_date'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'entry_date' => 'You already have an entry for '
                        .\Carbon\Carbon::parse($validated['entry_date'])->format('M d, Y')
                        .'. Each day can only be logged once - delete the existing entry first if you need to redo it.',
                ]);
            }

            $entry = $document->entries()->create([
                'entry_date' => $validated['entry_date'],
                'entry_end_date' => $requirement->code === 'WEEKLY_REPORT'
                    ? null
                    : ($validated['entry_end_date'] ?? null),
            ]);

            if ($requirement->code === 'WEEKLY_REPORT') {
                $entry->values()->create([
                    'field_key' => 'tasks_accomplished',
                    'value' => $validated['tasks_accomplished'],
                ]);
                $entry->values()->create([
                    'field_key' => 'knowledge_skills_learned',
                    'value' => $validated['knowledge_skills_learned'],
                ]);
            } else {
                foreach ($requirement->fieldDefinitions as $field) {
                    $entry->values()->create([
                        'field_key' => $field->field_key,
                        'value' => $validated[$field->field_key] ?? null,
                    ]);
                }
            }
        });

        \App\Models\Notification::resolve('logentry:'.$student->id.':'.\Carbon\Carbon::parse($validated['entry_date'])->toDateString());

        return redirect()->route('student.documents.log', $requirement)->with('status', 'Entry added.');
    }

    /**
     * Delete one daily entry from a log (e.g. a training day in the Form 19
     * weekly report) - for when the student made a mistake. Allowed under the
     * same rule as adding an entry: not once the form has been printed for
     * signing.
     */
    public function destroyEntry(DocumentRequirement $requirement, int $entry): RedirectResponse
    {
        if ($requirement->code === 'WEEKLY_REPORT') {
            abort_unless($requirement->is_repeating, 404);
            $internship = auth()->user()->student->currentInternship;
            abort_unless($internship, 422);
            $logEntry = \App\Models\DocumentEntry::whereHas('document', fn ($q) => $q
                ->where('internship_id', $internship->id)
                ->where('document_requirement_id', $requirement->id))
                ->with('document')->findOrFail($entry);
            abort_if(in_array($logEntry->document->status, ['submitted', 'for_review', 'printed', 'completed', 'approved']), 403,
                'This weekly report packet is locked for review/signing.');
        } else {
            $document = $this->editableLogDocument($requirement);
            $logEntry = $document->entries()->findOrFail($entry);
        }

        DB::transaction(function () use ($logEntry) {
            $logEntry->values()->delete();
            $logEntry->delete();
        });

        return redirect()
            ->route('student.documents.log', $requirement)
            ->with('status', 'Entry deleted.');
    }

    /**
     * Delete a whole week of a Form 19 weekly report (every training day
     * whose date falls in that Monday-Sunday week).
     */
    public function destroyWeek(DocumentRequirement $requirement, string $weekStart): RedirectResponse
    {
        abort_unless($requirement->code === 'WEEKLY_REPORT', 404);

        try {
            $start = \Carbon\Carbon::createFromFormat('Y-m-d', $weekStart)->startOfDay();
        } catch (\Throwable $e) {
            abort(404);
        }

        $internship = auth()->user()->student->currentInternship;
        abort_unless($internship, 422);
        $allEntriesQuery = \App\Models\DocumentEntry::whereHas('document', fn ($q) => $q
            ->where('internship_id', $internship->id)
            ->where('document_requirement_id', $requirement->id));
        $allDates = (clone $allEntriesQuery)->pluck('entry_date');
        $anchor = \App\Support\TrainingWeeks::anchorForInternship($internship, $allDates);
        abort_unless($anchor && \App\Support\TrainingWeeks::weekStart($start, $anchor)->equalTo($start), 404);

        $entries = (clone $allEntriesQuery)
            ->whereBetween('entry_date', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()])
            ->with('document')
            ->get();
        abort_if($entries->isEmpty(), 404);
        abort_if($entries->contains(fn ($entry) => in_array($entry->document->status, ['submitted', 'for_review', 'printed', 'completed', 'approved'])), 403,
            'This week belongs to a packet that is locked for review/signing.');

        DB::transaction(function () use ($entries) {
            foreach ($entries as $logEntry) {
                $logEntry->values()->delete();
                $logEntry->delete();
            }
        });

        return redirect()->route('student.documents.log', $requirement)
            ->with('status', 'Week deleted ('.$entries->count().' '.($entries->count() === 1 ? 'entry' : 'entries').' removed).');
    }

    /**
     * The student's own log document for this requirement, only if it is
     * still editable (not printed/completed/approved).
     */
    private function editableLogDocument(DocumentRequirement $requirement): Document
    {
        abort_unless($requirement->is_repeating, 404);

        $internship = auth()->user()->student->currentInternship;
        abort_unless($internship, 422, 'You need an internship record before logging entries - contact your coordinator.');
        abort_unless($this->isRequirementAvailable($requirement, $internship), 403, 'This requirement is not available yet.');

        $document = $internship->documents()->where('document_requirement_id', $requirement->id)->firstOrFail();

        abort_if(in_array($document->status, ['printed', 'completed', 'approved']), 403,
            'This log has already been printed for signing and can no longer be edited here.');

        return $document;
    }

    /**
     * Finalizes the log for instructor review - same meaning as
     * submitting a flat form, just triggered manually rather than on
     * every entry, since entries accumulate over time.
     */
    public function submitLog(DocumentRequirement $requirement, ?int $packet = null): RedirectResponse
    {
        abort_unless($requirement->is_repeating, 404);

        $student = auth()->user()->student;
        $this->authorizeStudentRequirement($requirement, $student);
        $internship = $student->currentInternship;
        abort_unless($this->isRequirementAvailable($requirement, $internship), 403, 'This requirement is not available yet.');

        $documents = $internship->documents()->where('document_requirement_id', $requirement->id);
        if ($requirement->code === 'WEEKLY_REPORT') {
            abort_unless($packet && $packet >= 1, 422, 'Choose a four-week Form 19 packet to submit.');
            $documents->where('packet_number', $packet);
        }
        $document = $documents->first();
        abort_unless($document, 422, 'Add at least one entry before submitting.');
        abort_if($document->entries()->count() === 0, 422, 'Add at least one entry before submitting.');
        abort_unless(in_array($document->status, ['draft', 'returned']), 422, 'This report packet has already entered the review/signing workflow.');

        $document->update(['status' => 'submitted', 'submitted_at' => now()]);

        \App\Models\Notification::resolve('logsubmit:'.$student->id);

        return redirect()->route('student.documents.log', $requirement)->with('status', $requirement->code === 'WEEKLY_REPORT' ? 'Form 19 packet '.$packet.' submitted for instructor review.' : 'Log submitted for instructor review.');
    }

    /**
     * The one-time upload of the scanned, signed physical copy. Only
     * reachable once the instructor has marked the form Completed -
     * mirrors the "upload unlocked" state from the real process.
     */
    public function uploadSigned(Request $request, Document $document): RedirectResponse
    {
        $student = auth()->user()->student;
        abort_unless($document->internship->student_id === $student->id, 403);
        abort_unless($document->requirement->workflow_mode === 'print_sign', 403, 'This upload is only available for print-and-sign forms.');

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store("internship_documents/campus_{$student->campus_id}/student_{$student->id}");

        try {
            DB::transaction(function () use ($document, $path, $file) {
                // Lock and re-check inside the transaction so duplicate requests
                // cannot replace a signed copy after the first upload succeeds.
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                // The student prints the form once the instructor approved it
                // for printing, has it signed, and uploads the scan here. The
                // instructor then approves (or returns) the signed copy.
                abort_unless(in_array($locked->status, ['printed', 'completed'], true), 403, 'Your instructor has to approve this form for printing first.');
                abort_if($locked->file_path !== null, 422, 'A signed copy has already been uploaded and is waiting for your instructor.');

                $locked->update([
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::delete($path);
            throw $exception;
        }

        $document->refresh();
        \App\Models\Instructor::where('campus_id', $document->internship->campus_id)
            ->where('program_id', $document->internship->program_id)
            ->get()
            ->each(fn ($instructor) => \App\Models\Notification::create([
                'user_id' => $instructor->user_id,
                'type' => 'document_status',
                'title' => "{$student->user->name} uploaded the signed {$document->requirement->name}",
                'message' => 'Check the signed copy and approve it.',
                'url' => route('instructor.documents', ['student' => $student->id, 'form' => $document->document_requirement_id], false),
                'resolve_key' => 'document:'.$document->id,
            ]));

        return back()->with('status', 'Signed copy uploaded. Your instructor will check and approve it.');
    }

    /**
     * Form 10 - MOA: the MOA is processed outside InternLink, so the student
     * uploads scans of the signed MOA. Several files can be uploaded at once
     * and more can be added later (each upload is a new submission for the
     * instructor); files already uploaded are kept.
     */
    public function uploadMoa(Request $request, DocumentRequirement $requirement): RedirectResponse
    {
        $student = auth()->user()->student;
        $this->authorizeStudentRequirement($requirement, $student);
        abort_unless(isset(DocumentAttachment::MULTI_UPLOAD[$requirement->code]), 404);
        $internship = $student->currentInternship;
        abort_unless($internship, 422, 'You need an internship record before uploading documents - contact your coordinator.');
        abort_unless($this->isRequirementAvailable($requirement, $internship), 403, 'This requirement is not available yet.');

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'files.required' => 'Choose at least one scanned file of the signed MOA.',
            'files.*.mimes' => 'Each file must be a PDF, JPG or PNG.',
            'files.*.max' => 'Each file must be 10 MB or smaller.',
        ]);

        $prefix = DocumentAttachment::MULTI_UPLOAD[$requirement->code];
        $wasReturned = false;

        $document = DB::transaction(function () use ($request, $requirement, $internship, $student, $prefix, &$wasReturned) {
            $document = Document::firstOrCreate(
                ['internship_id' => $internship->id, 'document_requirement_id' => $requirement->id],
                ['uploaded_by_user_id' => auth()->id(), 'status' => 'submitted', 'submitted_at' => now()]
            );
            $wasReturned = $document->status === 'returned';

            $next = $document->attachments()->where('kind', 'like', $prefix.'_%')->get()
                ->map(fn ($a) => (int) substr($a->kind, strlen($prefix) + 1))->max() ?? 0;

            foreach ($request->file('files') as $file) {
                DocumentAttachment::create([
                    'document_id' => $document->id,
                    'kind' => $prefix.'_'.(++$next),
                    'file_path' => $file->store("internship_documents/campus_{$student->campus_id}/student_{$student->id}"),
                    'original_filename' => $file->getClientOriginalName(),
                    'uploaded_by_user_id' => auth()->id(),
                ]);
            }

            // Every upload is a new submission for the instructor to check.
            $document->update(['uploaded_by_user_id' => auth()->id(), 'status' => 'submitted', 'submitted_at' => now()]);

            return $document;
        });

        $count = count($request->file('files'));
        \App\Models\Instructor::where('campus_id', $internship->campus_id)
            ->where('program_id', $internship->program_id)
            ->get()
            ->each(fn ($instructor) => \App\Models\Notification::create([
                'user_id' => $instructor->user_id,
                'type' => 'document_status',
                'title' => "{$student->user->name} uploaded {$count} ".\Illuminate\Support\Str::plural('scan', $count)." of the signed MOA",
                'message' => 'Awaiting your review.',
                'url' => route('instructor.documents', ['student' => $student->id, 'form' => $requirement->id], false),
                'resolve_key' => 'document:'.$document->id,
            ]));

        return back()->with('status', $count === 1 ? 'Signed MOA scan uploaded and submitted for review.' : "{$count} signed MOA scans uploaded and submitted for review.");
    }

    /**
     * Plain upload path for requirements with no structured fields -
     * unchanged from the original implementation.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'document_requirement_id' => ['required', 'exists:document_requirements,id'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $student = auth()->user()->student;
        $internship = $student->currentInternship;
        abort_unless($internship, 422, 'You need an internship record before uploading documents - contact your coordinator.');

        $requirement = DocumentRequirement::where('id', $request->input('document_requirement_id'))
            ->where('campus_id', $student->campus_id)
            ->where('audience', 'student')
            ->where('is_active', true)
            ->firstOrFail();
        abort_unless($this->isRequirementAvailable($requirement, $internship), 403, 'This requirement is not available yet.');
        abort_if($requirement->hasStructuredFields(), 422, 'This form must be filled out in-system rather than uploaded directly.');
        abort_if(isset(DocumentAttachment::MULTI_UPLOAD[$requirement->code]), 422, 'Upload the signed MOA scans with "Upload Scanned MOA".');

        $document = $internship->documents()
            ->where('document_requirement_id', $requirement->id)
            ->first();

        // Plain-upload requirements may be uploaded for the first time or
        // replaced only after the instructor returns them for correction.
        // This prevents a student from silently replacing a file while it is
        // already under instructor review or after it has been approved.
        if ($document && $document->status !== 'returned') {
            abort(422, match ($document->status) {
                'submitted', 'for_review' => 'This document is already under instructor review. Please wait for a decision before uploading another file.',
                'approved' => 'This document has already been approved and can no longer be replaced.',
                default => 'This document cannot be uploaded again at its current status.',
            });
        }

        $wasReturned = $document?->status === 'returned';
        $oldPath = $document?->file_path;

        $file = $request->file('file');
        $path = $file->store("internship_documents/campus_{$student->campus_id}/student_{$student->id}");

        if ($document) {
            $document->update([
                'uploaded_by_user_id' => auth()->id(),
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        } else {
            $document = Document::create([
                'internship_id' => $internship->id,
                'document_requirement_id' => $requirement->id,
                'uploaded_by_user_id' => auth()->id(),
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        }

        // Remove the superseded returned file only after the new upload has
        // been stored successfully and the database record updated.
        if ($wasReturned && $oldPath && $oldPath !== $path && Storage::exists($oldPath)) {
            Storage::delete($oldPath);
        }

        \App\Models\Instructor::where('campus_id', $internship->campus_id)
            ->where('program_id', $internship->program_id)
            ->get()
            ->each(fn ($instructor) => \App\Models\Notification::create([
                'user_id' => $instructor->user_id,
                'type' => 'document_status',
                'title' => "{$student->user->name} " . ($wasReturned ? 'resubmitted' : 'submitted') . " {$requirement->name}",
                'message' => 'Awaiting your review.',
                    'url' => route('instructor.documents', ['student' => $student->id], false),
                    'resolve_key' => 'document:'.$document->id,
            ]));

        return redirect()->route('student.documents')->with('status', $wasReturned
            ? 'Corrected document uploaded and resubmitted for instructor review.'
            : 'Document uploaded and submitted for review.');
    }

    /**
     * Sorts form numbers as numbers ("2" before "11"), with a letter suffix
     * after the number ("23a" before "23b"); no number sorts last.
     */
    private static function formNumberSortKey(?string $formNumber): array
    {
        if (! preg_match('/^(\d+)\s*([a-z]*)$/i', trim((string) $formNumber), $m)) {
            return [PHP_INT_MAX, ''];
        }

        return [(int) $m[1], strtolower($m[2])];
    }

    private const ATTACHMENT_MESSAGES = [
        'attachment_medical_certificate.required' => 'Attach your Medical Certificate from a government physician.',
        'attachment_psychological_test.required' => 'Attach your Psychological Test Result.',
        'attachment_parent_guardian_id.required' => 'Attach a photocopy of your parent/guardian\'s valid ID.',
        'attachment_*.mimes' => 'Attachments must be a PDF, JPG or PNG file.',
        'attachment_*.max' => 'Each attachment must be 10 MB or smaller.',
    ];

    /**
     * Upload rules for the files a form takes (Form 1: Medical Certificate
     * and Psychological Test; Form 3: Parent/Guardian Valid ID). Required
     * the first time; on a resubmission only needed to replace a file.
     */
    private function attachmentRules(DocumentRequirement $requirement, $internship): array
    {
        $kinds = DocumentAttachment::kindsFor($requirement->code);
        if (! $kinds) {
            return [];
        }

        $existing = $internship->documents()
            ->where('document_requirement_id', $requirement->id)
            ->with('attachments')
            ->first();

        $rules = [];
        foreach (array_keys($kinds) as $kind) {
            $rules['attachment_'.$kind] = [
                $existing?->attachment($kind) ? 'nullable' : 'required',
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240',
            ];
        }

        return $rules;
    }

    /** Stores the uploaded attachments on the form, replacing older files. */
    private function saveAttachments(Document $document, Request $request, $student): void
    {
        $code = $document->requirement?->code
            ?? DocumentRequirement::whereKey($document->document_requirement_id)->value('code');

        foreach (array_keys(DocumentAttachment::kindsFor($code)) as $kind) {
            $file = $request->file('attachment_'.$kind);
            if (! $file) {
                continue;
            }
            $current = $document->attachments()->where('kind', $kind)->first();
            $path = $file->store("internship_documents/campus_{$student->campus_id}/student_{$student->id}");
            DocumentAttachment::updateOrCreate(
                ['document_id' => $document->id, 'kind' => $kind],
                [
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'uploaded_by_user_id' => auth()->id(),
                ]
            );
            if ($current && $current->file_path !== $path && Storage::exists($current->file_path)) {
                Storage::delete($current->file_path);
            }
        }
    }

    /** The student opens a file they attached to a form (e.g. Form 1). */
    public function attachment(DocumentAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->document->internship->student_id === auth()->user()->student?->id, 403);
        abort_unless(Storage::exists($attachment->file_path), 404);

        return Storage::response($attachment->file_path, $attachment->original_filename, [], $attachment->isPreviewable() ? 'inline' : 'attachment');
    }

    /**
     * The student prints their own form once the instructor approved it for
     * printing, to bring it to the signatories. Same official template the
     * instructor sees.
     */
    public function printable(Document $document)
    {
        $student = auth()->user()->student;
        abort_unless($student && $document->internship->student_id === $student->id, 403);
        abort_unless($document->requirement->workflow_mode === 'print_sign', 404);
        abort_unless(in_array($document->status, ['printed', 'completed', 'approved'], true), 403,
            'Your instructor has to approve this form for printing first.');

        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user');

        return view(\App\Http\Controllers\Instructor\DocumentReviewController::printableView($document), compact('document'));
    }

    /** The student opens their own uploaded file in the browser. */
    public function view(Document $document): StreamedResponse
    {
        abort_unless($document->internship->student_id === auth()->user()->student->id, 403);
        abort_unless($document->file_path && Storage::exists($document->file_path), 404);

        return Storage::response($document->file_path, $document->original_filename, [], 'inline');
    }

    public function download(Document $document): StreamedResponse
    {
        abort_unless($document->internship->student_id === auth()->user()->student->id, 403);
        abort_unless($document->file_path && Storage::exists($document->file_path), 404);

        return Storage::download($document->file_path, $document->original_filename);
    }

    /**
     * Partner agencies a student may choose: their program's list for the
     * current term. The HTE they are already placed at is always included,
     * so a later form still shows it even if the list changed.
     */
    private function partnerAgencyChoices($student, $internship)
    {
        [$academicYear, $semester] = \App\Support\Term::current();

        $htes = \App\Http\Controllers\Instructor\HteController::listedHtes($student->program_id, $academicYear, $semester);

        if ($current = $internship?->deployment?->hte) {
            $htes = $htes->push($current)->unique('id')->sortBy('name')->values();
        }

        return $htes;
    }
}
