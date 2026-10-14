<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Document;
use App\Support\EvaluationRouting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EvaluationController extends Controller
{
    /**
     * Student Evaluations - where each advisee's evaluation of their partner
     * agency stands. The system does not record grades: the Subject
     * Instructor's evaluation is the official Form 23a (Evaluation Instrument
     * of PSU Partner Agencies), filled in once the internship is completed.
     */
    public function index(): View
    {
        $instructor = auth()->user()->instructor;

        $advisees = $instructor->advisees()
            ->with([
                'user',
                'program',
                'currentInternship.deployment.hte',
                'currentInternship.documents',
            ])
            ->whereHas('currentInternship.deployment')
            ->latest('id')
            ->get();

        $partnerAgencyFormRequirement = \App\Models\DocumentRequirement::where('campus_id', $instructor->campus_id)
            ->where('code', 'PARTNER_AGENCY_EVAL_INSTRUCTOR')
            ->where('is_active', true)
            ->first();

        // The six official evaluation forms and who fills each one:
        //   HTE         -> Form 13, Form 21, Form 23b
        //   Student     -> Form 20, Form 23c
        //   Instructor  -> Form 23a
        $formIds = \App\Models\DocumentRequirement::where('campus_id', $instructor->campus_id)
            ->whereIn('code', [
                'EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK', 'PARTNER_AGENCY_EVAL_SELF',
                'STUDENT_FEEDBACK_FORM', 'STUDENT_HTE_EVALUATION',
                'PARTNER_AGENCY_EVAL_INSTRUCTOR',
            ])
            ->pluck('id', 'code');

        $progress = $advisees->mapWithKeys(fn ($student) => [
            $student->id => $this->evaluationProgress($student, $formIds),
        ]);

        // Form 13 (Internship Evaluation Form) arrives here from the HTE.
        $form13Requirement = \App\Models\DocumentRequirement::where('campus_id', $instructor->campus_id)
            ->where('code', 'EVALUATION_FORM')
            ->where('is_active', true)
            ->first();

        return view('instructor.evaluations.index', compact('advisees', 'partnerAgencyFormRequirement', 'form13Requirement', 'progress'));
    }

    /**
     * Open a Form 13 the host training establishment sent for one of this
     * instructor's advisees. View / print only - the instructor cannot edit it.
     */
    public function show(Request $request, Document $document): View
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor, 403);

        $document->load('requirement', 'internship');
        abort_unless($document->requirement?->code === 'EVALUATION_FORM', 404);
        abort_unless($document->requirement->campus_id === $instructor->campus_id, 403);
        abort_unless(
            $document->internship->campus_id === $instructor->campus_id
                && $document->internship->program_id === $instructor->program_id,
            403
        );
        abort_unless(EvaluationRouting::isSubmitted($document), 404);

        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user', 'uploader');

        return view(EvaluationRouting::view('EVALUATION_FORM'), [
            'document' => $document,
            'autoPrint' => $request->boolean('download'),
            'backUrl' => route('instructor.evaluations'),
        ]);
    }

    /**
     * Where this student's evaluation stands. Evaluation is complete when all
     * six official forms are in: HTE (Forms 13, 21, 23b), student (Forms 20,
     * 23c) and Subject Instructor (Form 23a). Status is derived from official
     * document records; legacy numeric ratings are not treated as form submissions.
     *
     * With no form in yet, the deployment decides the label, so a student who
     * is still training reads "Ongoing Internship", not "Not Started".
     *
     * @return array{key:string,label:string,color:string,detail:?string,done:int,total:int,parties:array}
     */
    private function evaluationProgress($student, $formIds): array
    {
        $internship = $student->currentInternship;
        $deployment = $internship?->deployment;
        $docs = $internship?->documents?->keyBy('document_requirement_id') ?? collect();

        $submittedStatuses = ['submitted', 'for_review', 'approved', 'printed', 'completed'];
        $formDone = fn (string $code) => isset($formIds[$code])
            && in_array($docs->get($formIds[$code])?->status, $submittedStatuses, true);

        $parties = [
            'HTE' => [
                'EVALUATION_FORM' => false,
                'TRAINING_SUPERVISOR_FEEDBACK' => false,
                'PARTNER_AGENCY_EVAL_SELF' => false,
            ],
            'Student' => [
                'STUDENT_FEEDBACK_FORM' => false,
                'STUDENT_HTE_EVALUATION' => false,
            ],
            'Instructor' => [
                'PARTNER_AGENCY_EVAL_INSTRUCTOR' => false,
            ],
        ];

        $done = 0;
        $total = 0;
        $detailParts = [];
        $partyCounts = [];
        foreach ($parties as $party => $codes) {
            $partyDone = 0;
            foreach ($codes as $code => $legacyDone) {
                $total++;
                if ($legacyDone || $formDone($code)) {
                    $partyDone++;
                    $done++;
                }
            }
            $detailParts[] = $party.' '.$partyDone.'/'.count($codes);
            $partyCounts[$party] = ['done' => $partyDone, 'total' => count($codes)];
        }

        $detail = implode(' · ', $detailParts);

        $state = match (true) {
            $done === $total => ['completed', 'Completed', 'text-bg-primary'],
            $done > 0 => ['in_progress', 'In Progress', 'text-bg-warning'],
            $deployment?->status === 'terminated' => ['terminated', 'Terminated', 'text-bg-danger'],
            $deployment?->status === 'pending' => ['pending', 'Awaiting Deployment', 'text-bg-secondary'],
            $deployment?->status === 'active' => ['ongoing', 'Ongoing Internship', 'text-bg-info'],
            default => ['not_started', 'Not Started', 'text-bg-light'],
        };

        return [
            'key' => $state[0],
            'label' => $state[1],
            'color' => $state[2],
            'detail' => in_array($state[0], ['in_progress', 'completed', 'not_started'], true)
                ? $done.' of '.$total.' forms submitted — '.$detail
                : null,
            'done' => $done,
            'total' => $total,
            'parties' => $partyCounts,
        ];
    }
}
