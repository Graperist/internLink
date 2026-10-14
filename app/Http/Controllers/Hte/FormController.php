<?php

namespace App\Http\Controllers\Hte;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFieldValue;
use App\Models\DocumentRequirement;
use App\Models\Deployment;
use App\Models\Instructor;
use App\Models\Notification;
use App\Support\DocumentFormRules;
use App\Support\EvaluationRouting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FormController extends Controller
{
    public function create(DocumentRequirement $requirement, Deployment $deployment): View
    {
        $representative = $this->representative();
        $this->authorizeRequirement($requirement, (int) ($representative->hte?->campus_id ?? 0));
        $this->authorizeDeployment($deployment, $representative->hte_id);

        $document = Document::where('internship_id', $deployment->internship_id)
            ->where('document_requirement_id', $requirement->id)
            ->with(['fieldValues', 'latestReview'])
            ->first();

        abort_if($document && in_array($document->status, ['submitted', 'for_review', 'approved', 'printed', 'completed'], true), 403,
            'This form has already been submitted or finalized.');

        $student = $deployment->internship->student()->with(['user', 'program'])->first();
        $fieldDefaults = [
            'student_name' => $student?->user?->name ?? '',
            'company_name' => $deployment->hte?->name ?? '',
            'company_address' => $deployment->hte?->address ?? '',
            'agency_name' => $deployment->hte?->name ?? '',
            'rater_name' => $representative->user?->name ?? '',
            'rater_type' => 'Self Ratee',
            'unit_division' => '',
            'supervisor_name' => $representative->user?->name ?? '',
            'department' => '',
            'date_started' => $deployment->start_date?->format('Y-m-d') ?? '',
            'date_ended' => $deployment->end_date?->format('Y-m-d') ?? '',
        ];

        return view('hte.forms.fill', compact('requirement', 'deployment', 'document', 'fieldDefaults', 'representative', 'student'));
    }

    public function store(Request $request, DocumentRequirement $requirement, Deployment $deployment): RedirectResponse
    {
        $representative = $this->representative();
        $this->authorizeRequirement($requirement, (int) ($representative->hte?->campus_id ?? 0));
        $this->authorizeDeployment($deployment, $representative->hte_id);

        $rules = DocumentFormRules::rules($requirement, $representative->hte?->campus_id);
        $validated = $request->validate($rules);
        $validated = DocumentFormRules::coerceCheckboxes($validated);

        $existing = Document::where('internship_id', $deployment->internship_id)
            ->where('document_requirement_id', $requirement->id)
            ->first();
        abort_if($existing && in_array($existing->status, ['submitted', 'for_review', 'approved', 'printed', 'completed'], true), 403,
            'This form has already been submitted or finalized.');

        $document = DB::transaction(function () use ($deployment, $requirement, $validated, $representative) {
            $document = Document::updateOrCreate(
                [
                    'internship_id' => $deployment->internship_id,
                    'document_requirement_id' => $requirement->id,
                ],
                [
                    'uploaded_by_user_id' => $representative->user_id,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]
            );

            foreach ($validated as $key => $value) {
                DocumentFieldValue::updateOrCreate(
                    ['document_id' => $document->id, 'field_key' => $key],
                    ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
                );
            }

            return $document;
        });

        $this->notifyRecipient($requirement, $deployment, $document, $representative);

        $destination = match (EvaluationRouting::FORMS[$requirement->code]['to'] ?? null) {
            'instructor' => 'the Subject Instructor (Evaluations)',
            'student' => 'the student (My HTE Info)',
            'coordinator' => 'the Campus Internship Coordinator (Documents)',
            default => 'the university',
        };

        return redirect()->route('hte.evaluations')->with('status', EvaluationRouting::FORMS[$requirement->code]['form'].' submitted and sent to '.$destination.'.');
    }

    /**
     * Tell the party the form is addressed to that it has arrived:
     *   Form 13  -> the student's Subject Instructor(s)  (Evaluations)
     *   Form 21  -> the student                          (My HTE Info)
     *   Form 23b -> the campus Coordinator(s)            (Documents)
     */
    private function notifyRecipient(DocumentRequirement $requirement, Deployment $deployment, Document $document, $representative): void
    {
        $internship = $deployment->internship;
        $studentName = $internship->student->user->name;
        $hteName = $deployment->hte?->name ?? 'The host training establishment';
        $formLabel = EvaluationRouting::FORMS[$requirement->code]['form'];

        switch (EvaluationRouting::FORMS[$requirement->code]['to'] ?? null) {
            case 'instructor':
                $userIds = Instructor::where('campus_id', $internship->campus_id)
                    ->where('program_id', $internship->program_id)
                    ->whereHas('user', fn ($q) => $q->where('status', 'active'))
                    ->pluck('user_id');
                foreach ($userIds as $userId) {
                    Notification::create([
                        'user_id' => $userId,
                        'type' => 'document_status',
                        'title' => "{$formLabel} received from {$hteName}",
                        'message' => "{$hteName} submitted the Internship Evaluation Form for {$studentName}. Open Evaluations to view it.",
                        // Evaluations sidebar, scrolled to (and highlighting) this student's row.
                        'url' => route('instructor.evaluations', [], false).'#student-'.$internship->student->id,
                    ]);
                }
                break;

            case 'student':
                Notification::create([
                    'user_id' => $internship->student->user_id,
                    'type' => 'document_status',
                    'title' => "Training Supervisor's feedback received ({$formLabel})",
                    'message' => "{$hteName} submitted its feedback about your training. Open My HTE Info to read it.",
                    'url' => route('student.hte', [], false),
                ]);
                break;

            case 'coordinator':
                $userIds = User::where('campus_id', $internship->campus_id)
                    ->whereHas('role', fn ($q) => $q->where('code', 'coordinator'))
                    ->where('status', 'active')
                    ->pluck('id');
                foreach ($userIds as $userId) {
                    Notification::create([
                        'user_id' => $userId,
                        'type' => 'document_status',
                        'title' => "{$hteName} submitted {$formLabel}",
                        'message' => "The partner-agency self-evaluation for {$studentName} is listed under Documents > HTE Evaluation Forms.",
                        'url' => route('coordinator.documents', [], false),
                    ]);
                }
                break;
        }
    }

    private function representative()
    {
        $representative = auth()->user()->hteRepresentative()->with('hte')->first();
        abort_unless($representative, 403, 'Your HTE representative profile is not linked to a Host Training Establishment.');
        return $representative;
    }

    private function authorizeRequirement(DocumentRequirement $requirement, int $hteCampusId): void
    {
        abort_unless($requirement->is_active && $requirement->audience === 'hte', 404);
        abort_unless(in_array($requirement->code, ['EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK', 'PARTNER_AGENCY_EVAL_SELF'], true), 404);
        abort_unless($hteCampusId > 0 && $requirement->campus_id === $hteCampusId, 403);
    }

    private function authorizeDeployment(Deployment $deployment, int $hteId): void
    {
        abort_unless($deployment->hte_id === $hteId, 403);
        abort_unless($deployment->status === 'completed', 422, 'This post-OJT form becomes available after the internship is completed.');
    }
}
