<?php

namespace App\Http\Controllers\Hte;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Instructor;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function assignedStudents(): View
    {
        $representative = $this->representative();
        $deployments = Deployment::where('hte_id', $representative->hte_id)
            ->with(['internship.student.user', 'internship.student.program', 'internship.documents', 'dtrs'])
            ->whereIn('status', ['pending', 'active', 'completed'])->latest('id')->get();

        // The same sources as the rest of the system: hours from the Time Frame
        // days, evaluation from the HTE's Forms 13 and 21.
        $hteForms = DocumentRequirement::where('campus_id', $representative->hte->campus_id)
            ->whereIn('code', \App\Support\EvaluationRouting::HTE_INTERN_CODES)->where('is_active', true)->pluck('id');
        $deployments->each(function ($deployment) use ($hteForms) {
            $deployment->setAttribute('rendered_hours', (float) $deployment->dtrs->sum(fn ($dtr) => $dtr->countedHours()));
            $deployment->setAttribute('hte_forms_total', $hteForms->count());
            $deployment->setAttribute('hte_forms_done', $deployment->internship->documents
                ->whereIn('document_requirement_id', $hteForms)
                ->whereIn('status', \App\Support\EvaluationRouting::SUBMITTED)->count());
        });

        return view('hte.assigned-students', compact('representative', 'deployments'));
    }

    public function evaluations(): View
    {
        $representative = $this->representative();
        $deployments = Deployment::where('hte_id', $representative->hte_id)
            ->where('status', 'completed')
            ->with(['internship.student.user', 'internship.student.program', 'internship.documents'])
            ->get()
            ->sortBy(fn ($d) => $d->internship->student->user->name)
            ->values();

        $requirements = \App\Models\DocumentRequirement::where('campus_id', $representative->hte->campus_id)
            ->where('is_active', true)
            ->whereIn('code', ['EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK', 'PARTNER_AGENCY_EVAL_SELF'])
            ->get()
            ->keyBy('code');

        $selfRequirement = $requirements->get('PARTNER_AGENCY_EVAL_SELF');
        $selfEvaluation = $selfRequirement ? \App\Support\AgencyEvaluations::find($selfRequirement, $representative->hte_id) : null;

        return view('hte.evaluations', compact('representative', 'deployments', 'requirements', 'selfRequirement', 'selfEvaluation'));
    }

    /**
     * The HTE's own submitted evaluation, read only: Form 13 / 21 of an
     * intern placed at their agency, or their agency's Form 23b.
     */
    public function viewEvaluation(\App\Models\Document $document): \Illuminate\Http\Response
    {
        $representative = $this->representative();
        $document->loadMissing('requirement', 'internship.deployment');
        $code = $document->requirement?->code;

        $ours = match ($code) {
            'EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK' => (int) $document->internship?->deployment?->hte_id === (int) $representative->hte_id,
            'PARTNER_AGENCY_EVAL_SELF' => (int) $document->hte_id === (int) $representative->hte_id,
            default => false,
        };
        abort_unless($ours && \App\Support\EvaluationRouting::isSubmitted($document), 404);

        return \App\Support\EvaluationView::response($document, route('hte.evaluations'));
    }

    private function representative()
    {
        $representative = auth()->user()->hteRepresentative()->with('hte')->first();
        abort_unless($representative, 403, 'Your HTE representative profile is not linked to a Host Training Establishment.');

        return $representative;
    }
}
