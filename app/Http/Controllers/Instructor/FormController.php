<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFieldValue;
use App\Models\DocumentRequirement;
use App\Models\Deployment;
use App\Models\HteRepresentative;
use App\Models\Notification;
use App\Support\DocumentFormRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FormController extends Controller
{
    public function create(DocumentRequirement $requirement, Deployment $deployment): View
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor, 403);
        $this->authorizeRequirement($requirement, $instructor->campus_id);
        $this->authorizeDeployment($deployment, $instructor);

        $document = Document::where('internship_id', $deployment->internship_id)
            ->where('document_requirement_id', $requirement->id)
            ->with(['fieldValues', 'latestReview'])
            ->first();

        abort_if($document && in_array($document->status, ['submitted', 'for_review', 'approved', 'printed', 'completed'], true), 403,
            'This form has already been submitted or finalized.');

        $student = $deployment->internship->student()->with(['user', 'program'])->first();
        $fieldDefaults = [
            'rater_name' => auth()->user()->name,
            'agency_name' => $deployment->hte?->name ?? '',
            'unit_division' => '',
            'sex_gender' => '',
            'rater_type' => 'Subject Instructor',
        ];

        return view('instructor.forms.fill', compact('requirement', 'deployment', 'document', 'fieldDefaults', 'instructor', 'student'));
    }

    public function store(Request $request, DocumentRequirement $requirement, Deployment $deployment): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor, 403);
        $this->authorizeRequirement($requirement, $instructor->campus_id);
        $this->authorizeDeployment($deployment, $instructor);

        $rules = DocumentFormRules::rules($requirement, $instructor->campus_id);
        $validated = $request->validate($rules);
        $validated = DocumentFormRules::coerceCheckboxes($validated);

        $existing = Document::where('internship_id', $deployment->internship_id)
            ->where('document_requirement_id', $requirement->id)
            ->first();
        abort_if($existing && in_array($existing->status, ['submitted', 'for_review', 'approved', 'printed', 'completed'], true), 403,
            'This form has already been submitted or finalized.');

        DB::transaction(function () use ($deployment, $requirement, $validated, $instructor) {
            $document = Document::updateOrCreate(
                [
                    'internship_id' => $deployment->internship_id,
                    'document_requirement_id' => $requirement->id,
                ],
                [
                    'uploaded_by_user_id' => $instructor->user_id,
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
        });

        Notification::create([
            'user_id' => $deployment->internship->student->user_id,
            'type' => 'document_status',
            'title' => "{$requirement->name} submitted by Subject Instructor",
            'message' => 'Your instructor submitted the partner-agency evaluation form.',
        ]);

        // Let the host training establishment know an evaluation of their agency
        // is ready to read (HTE portal > Submit Evaluation > Evaluations Received).
        $studentName = $deployment->internship->student->user->name;
        HteRepresentative::where('hte_id', $deployment->hte_id)->pluck('user_id')->each(
            fn ($userId) => Notification::create([
                'user_id' => $userId,
                'type' => 'document_status',
                'title' => 'Evaluation received from the Subject Instructor',
                'message' => "{$instructor->user->name} submitted an evaluation of your agency (Form 23a) for {$studentName}. "
                    .'You can view and print it under Submit Evaluation > Evaluations Received.',
                'url' => route('hte.evaluations.received', [], false),
            ])
        );

        return redirect()->route('instructor.evaluations')->with('status', 'Partner agency evaluation form submitted.');
    }

    private function authorizeRequirement(DocumentRequirement $requirement, int $campusId): void
    {
        abort_unless($requirement->is_active && $requirement->audience === 'instructor', 404);
        abort_unless($requirement->code === 'PARTNER_AGENCY_EVAL_INSTRUCTOR', 404);
        abort_unless($requirement->campus_id === $campusId, 403);
    }

    private function authorizeDeployment(Deployment $deployment, $instructor): void
    {
        abort_unless(
            $deployment->internship->campus_id === $instructor->campus_id
                && $deployment->internship->program_id === $instructor->program_id,
            403
        );
        abort_unless($deployment->status === 'completed', 422, 'Form 23a becomes available after the internship is completed.');
    }
}
