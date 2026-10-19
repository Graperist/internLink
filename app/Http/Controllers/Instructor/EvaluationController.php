<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Support\AgencyEvaluations;
use App\Support\EvaluationRouting;
use App\Support\EvaluationView;
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
    public function index(): View
    {
        $instructor = $this->subjectInstructor();
        $agencies = AgencyEvaluations::agenciesFor($instructor);

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

        return view('instructor.evaluations.index', compact('agencyRows', 'internRows', 'internCodes', 'requirements'));
    }

    /** Read or (?download=1) download a received evaluation, or your own Form 23a. */
    public function show(Document $document): Response
    {
        $instructor = $this->subjectInstructor();
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
            abort_unless($instructor->canView($document->internship), 403);
        }

        return EvaluationView::response($document, route('instructor.evaluations'));
    }

    /** Evaluations are the Subject Instructor's only - not the coordinator's. */
    private function subjectInstructor()
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor && ! $instructor->isCoordinator(), 403, 'Evaluations go to the Subject Instructor only.');

        return $instructor;
    }

    private function submitted(?Document $document): ?Document
    {
        return EvaluationRouting::isSubmitted($document) ? $document : null;
    }
}
