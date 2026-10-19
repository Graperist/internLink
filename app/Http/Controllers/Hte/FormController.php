<?php

namespace App\Http\Controllers\Hte;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentFieldValue;
use App\Models\DocumentRequirement;
use App\Models\Instructor;
use App\Models\Notification;
use App\Support\AgencyEvaluations;
use App\Support\DocumentFormRules;
use App\Support\EvaluationRouting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The HTE's evaluations. Rating an intern (Form 13 Internship Evaluation,
 * Form 21 Training Supervisor's Feedback) or the agency itself, once
 * (Form 23b Self Ratee). Each goes to the Subject Instructor only - the
 * intern does not see it. Open once the internship is completed.
 */
class FormController extends Controller
{
    private const SELF_CODE = 'PARTNER_AGENCY_EVAL_SELF';

    // ---- Forms 13 and 21: one intern --------------------------------------

    public function create(DocumentRequirement $requirement, Deployment $deployment): View
    {
        $representative = $this->representative();
        $this->authorizeRequirement($requirement, $representative, EvaluationRouting::HTE_INTERN_CODES);
        $this->authorizeDeployment($deployment, $representative->hte_id);

        $document = Document::where('internship_id', $deployment->internship_id)
            ->where('document_requirement_id', $requirement->id)
            ->with(['fieldValues', 'latestReview'])
            ->first();
        abort_if(EvaluationRouting::isSubmitted($document), 403, 'You already evaluated this intern on this form.');

        $student = $deployment->internship->student()->with(['user', 'program'])->first();
        $fieldDefaults = $this->defaults($representative) + [
            'student_name' => $student?->user?->name ?? '',
            'date_started' => $deployment->start_date?->format('Y-m-d') ?? '',
            'date_ended' => $deployment->end_date?->format('Y-m-d') ?? '',
        ];
        $formAction = route('hte.forms.store', [$requirement, $deployment]);

        return view('hte.forms.fill', compact('requirement', 'deployment', 'document', 'fieldDefaults', 'representative', 'student', 'formAction'));
    }

    public function store(Request $request, DocumentRequirement $requirement, Deployment $deployment): RedirectResponse
    {
        $representative = $this->representative();
        $this->authorizeRequirement($requirement, $representative, EvaluationRouting::HTE_INTERN_CODES);
        $this->authorizeDeployment($deployment, $representative->hte_id);

        $validated = $this->validated($request, $requirement, $representative);
        $existing = Document::where('internship_id', $deployment->internship_id)->where('document_requirement_id', $requirement->id)->first();
        abort_if(EvaluationRouting::isSubmitted($existing), 403, 'You already evaluated this intern on this form.');

        $this->save($existing, ['internship_id' => $deployment->internship_id, 'document_requirement_id' => $requirement->id], $validated, $representative);

        $studentName = $deployment->internship->student->user->name;
        $this->notifyInstructors(
            Instructor::where('campus_id', $deployment->internship->campus_id)->where('program_id', $deployment->internship->program_id),
            EvaluationRouting::FORMS[$requirement->code]['form']." received from {$representative->hte->name}",
            "{$representative->hte->name} submitted {$requirement->name} for {$studentName}. Open Evaluations to view it.",
            route('instructor.evaluations', [], false).'#student-'.$deployment->internship->student_id,
        );

        return redirect()->route('hte.evaluations')->with('status', "Evaluation of {$studentName} submitted. It goes to the Subject Instructor.");
    }

    // ---- Form 23b: the agency rates itself, once ---------------------------

    public function createSelf(): View
    {
        $representative = $this->representative();
        [$requirement, $deployment] = $this->selfContext($representative);
        $document = AgencyEvaluations::find($requirement, $representative->hte_id);
        abort_if(EvaluationRouting::isSubmitted($document), 403, 'Your agency already submitted its self-rating.');

        $fieldDefaults = $this->defaults($representative);
        $student = null;
        $formAction = route('hte.self-evaluation.store');
        $subtitle = 'Your agency: '.$representative->hte->name;

        return view('hte.forms.fill', compact('requirement', 'deployment', 'document', 'fieldDefaults', 'representative', 'student', 'formAction', 'subtitle'));
    }

    public function storeSelf(Request $request): RedirectResponse
    {
        $representative = $this->representative();
        [$requirement, $deployment] = $this->selfContext($representative);
        $validated = $this->validated($request, $requirement, $representative);
        $existing = AgencyEvaluations::find($requirement, $representative->hte_id);
        abort_if(EvaluationRouting::isSubmitted($existing), 403, 'Your agency already submitted its self-rating.');

        $this->save($existing, ['internship_id' => $deployment->internship_id, 'hte_id' => $representative->hte_id, 'document_requirement_id' => $requirement->id], $validated, $representative);

        // Every program whose students finished an internship here.
        $programIds = Deployment::where('deployments.hte_id', $representative->hte_id)->where('deployments.status', 'completed')
            ->join('internships', 'internships.id', '=', 'deployments.internship_id')->pluck('internships.program_id')->unique();
        $this->notifyInstructors(
            Instructor::where('campus_id', $representative->hte->campus_id)->whereIn('program_id', $programIds),
            "Form 23b received from {$representative->hte->name}",
            "{$representative->hte->name} submitted its self-rating (Form 23b). Open Evaluations to view it.",
            route('instructor.evaluations', [], false),
        );

        return redirect()->route('hte.evaluations')->with('status', 'Your agency\'s self-rating (Form 23b) is submitted. It goes to the Subject Instructor.');
    }

    // ---- shared -------------------------------------------------------------

    private function defaults($representative): array
    {
        return [
            'company_name' => $representative->hte->name,
            'company_address' => $representative->hte->address ?? '',
            'agency_name' => $representative->hte->name,
            'rater_name' => $representative->user?->name ?? '',
            'rater_type' => 'Self Ratee',
            'unit_division' => '',
            'supervisor_name' => $representative->user?->name ?? '',
            'department' => '',
        ];
    }

    private function validated(Request $request, DocumentRequirement $requirement, $representative): array
    {
        return DocumentFormRules::coerceCheckboxes($request->validate(DocumentFormRules::rules($requirement, $representative->hte?->campus_id), DocumentFormRules::messages($requirement), DocumentFormRules::attributes($requirement)));
    }

    private function save(?Document $existing, array $keys, array $validated, $representative): Document
    {
        return DB::transaction(function () use ($existing, $keys, $validated, $representative) {
            $values = ['uploaded_by_user_id' => $representative->user_id, 'status' => 'submitted', 'submitted_at' => now()];
            $document = $existing ? tap($existing)->update($keys + $values) : Document::create($keys + $values);
            foreach ($validated as $key => $value) {
                DocumentFieldValue::updateOrCreate(
                    ['document_id' => $document->id, 'field_key' => $key],
                    ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
                );
            }

            return $document;
        });
    }

    private function notifyInstructors($instructors, string $title, string $message, string $url): void
    {
        $instructors->whereHas('user', fn ($q) => $q->where('status', 'active'))->pluck('user_id')->each(fn ($userId) => Notification::create([
            'user_id' => $userId, 'type' => 'document_status', 'title' => $title, 'message' => $message, 'url' => $url,
        ]));
    }

    /** @return array{0: DocumentRequirement, 1: Deployment} */
    private function selfContext($representative): array
    {
        $requirement = AgencyEvaluations::requirement(self::SELF_CODE, (int) $representative->hte->campus_id);
        abort_unless($requirement && $requirement->audience === 'hte', 404);
        $deployment = AgencyEvaluations::anchor($representative->hte);
        abort_unless($deployment, 422, 'The self-rating opens once one of your interns completes their internship.');

        return [$requirement, $deployment];
    }

    private function representative()
    {
        $representative = auth()->user()->hteRepresentative()->with('hte', 'user')->first();
        abort_unless($representative, 403, 'Your HTE representative profile is not linked to a Host Training Establishment.');

        return $representative;
    }

    private function authorizeRequirement(DocumentRequirement $requirement, $representative, array $codes): void
    {
        abort_unless($requirement->is_active && $requirement->audience === 'hte', 404);
        abort_unless(in_array($requirement->code, $codes, true), 404);
        abort_unless((int) $requirement->campus_id === (int) $representative->hte?->campus_id, 403);
    }

    private function authorizeDeployment(Deployment $deployment, int $hteId): void
    {
        abort_unless($deployment->hte_id === $hteId, 403);
        abort_unless($deployment->status === 'completed', 422, 'This evaluation opens once the internship is completed.');
    }
}
