<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFieldValue;
use App\Models\Hte;
use App\Support\AgencyEvaluations;
use App\Support\DocumentFormRules;
use App\Support\EvaluationRouting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Form 23a - the Subject Instructor evaluates a partner agency, once per
 * agency (like the PSU faculty evaluation: pick the name, rate it). The
 * evaluation stays with the instructor; the agency never sees it.
 */
class FormController extends Controller
{
    private const CODE = 'PARTNER_AGENCY_EVAL_INSTRUCTOR';

    public function create(Hte $hte): View
    {
        [$instructor, $requirement, $deployment] = $this->context($hte);

        $document = AgencyEvaluations::find($requirement, $hte->id, $instructor->user_id);
        abort_if(EvaluationRouting::isSubmitted($document), 403, 'You already evaluated this agency.');

        $fieldDefaults = [
            'rater_name' => auth()->user()->name,
            'agency_name' => $hte->name,
            'unit_division' => '',
            'sex_gender' => '',
            'rater_type' => 'Subject Instructor',
        ];
        $student = null;
        $formAction = route('instructor.agency-evaluations.store', $hte);
        $subtitle = 'Partner agency: '.$hte->name;

        return view('instructor.forms.fill', compact('requirement', 'deployment', 'document', 'fieldDefaults', 'instructor', 'student', 'formAction', 'subtitle'));
    }

    public function store(Request $request, Hte $hte): RedirectResponse
    {
        [$instructor, $requirement, $deployment] = $this->context($hte);

        $validated = DocumentFormRules::coerceCheckboxes($request->validate(DocumentFormRules::rules($requirement, $instructor->campus_id)));

        abort_if(EvaluationRouting::isSubmitted(AgencyEvaluations::find($requirement, $hte->id, $instructor->user_id)), 403,
            'You already evaluated this agency.');

        DB::transaction(function () use ($deployment, $requirement, $validated, $instructor, $hte) {
            $document = Document::create([
                'internship_id' => $deployment->internship_id,
                'hte_id' => $hte->id,
                'document_requirement_id' => $requirement->id,
                'uploaded_by_user_id' => $instructor->user_id,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
            foreach ($validated as $key => $value) {
                DocumentFieldValue::create([
                    'document_id' => $document->id, 'field_key' => $key,
                    'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                ]);
            }
        });

        return redirect()->route('instructor.evaluations')->with('status', "Your evaluation of {$hte->name} (Form 23a) is submitted.");
    }

    /** @return array{0: \App\Models\Instructor, 1: \App\Models\DocumentRequirement, 2: \App\Models\Deployment} */
    private function context(Hte $hte): array
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor, 403);
        $requirement = AgencyEvaluations::requirement(self::CODE, $instructor->campus_id);
        abort_unless($requirement, 404);
        // Only agencies where this instructor's students finished their internship.
        $deployment = AgencyEvaluations::anchor($hte, $instructor);
        abort_unless($deployment, 403, 'None of your students has completed an internship at this agency yet.');

        return [$instructor, $requirement, $deployment];
    }
}
