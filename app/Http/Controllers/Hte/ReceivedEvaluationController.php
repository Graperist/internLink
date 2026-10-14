<?php

namespace App\Http\Controllers\Hte;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Support\EvaluationRouting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Read-only "Evaluations Received" for HTE supervisors. Three official forms
 * are delivered here, each filled in by someone else about this agency or
 * its trainee:
 *
 *   Form 20  - Student-Intern's Feedback Form                 (from the student)
 *   Form 23a - Evaluation Instrument of PSU Partner Agencies  (from the Subject Instructor)
 *   Form 23c - Evaluation Instrument of PSU Partner Agencies  (from the student)
 *
 * The supervisor can open the full form and print / save it as PDF, but cannot
 * edit or return it. Only deployments hosted by the supervisor's own HTE are
 * ever listed or opened.
 */
class ReceivedEvaluationController extends Controller
{
    public function index(Request $request): View
    {
        $representative = $this->representative();
        $requirements = $this->requirements($representative);   // keyed by code

        $filter = $request->query('form');
        $filter = is_string($filter) && $requirements->has($filter) ? $filter : null;

        $received = $this->receivedQuery($representative, $requirements->pluck('id'))
            ->with(['internship.student.user', 'internship.student.program', 'uploader', 'requirement'])
            ->latest('submitted_at')
            ->get();

        // Chip counts always cover all three forms; the table honours the filter.
        $counts = $received->groupBy(fn ($d) => $d->requirement->code)->map->count();
        $documents = $filter ? $received->filter(fn ($d) => $d->requirement->code === $filter)->values() : $received;

        return view('hte.evaluations-received', compact('representative', 'documents', 'requirements', 'counts', 'filter'));
    }

    /**
     * The completed form, exactly as the university prints it. With
     * ?download=1 the browser's print dialog opens straight away, where
     * "Save as PDF" downloads the file.
     */
    public function show(Request $request, Document $document): View
    {
        $representative = $this->representative();
        $requirements = $this->requirements($representative);

        $code = $requirements->search(fn ($r) => $r->id === $document->document_requirement_id);
        abort_if($code === false, 404);
        abort_unless(in_array($document->status, EvaluationRouting::SUBMITTED, true), 404);
        abort_unless($document->internship?->deployment?->hte_id === $representative->hte_id, 403);

        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user', 'uploader');

        return view(EvaluationRouting::view($code), [
            'document' => $document,
            'autoPrint' => $request->boolean('download'),
            'backUrl' => route('hte.evaluations.received'),
        ]);
    }

    private function receivedQuery($representative, Collection $requirementIds)
    {
        return Document::whereIn('document_requirement_id', $requirementIds)
            ->whereIn('status', EvaluationRouting::SUBMITTED)
            ->whereHas('internship.deployment', fn ($q) => $q->where('hte_id', $representative->hte_id));
    }

    /** @return Collection<string, DocumentRequirement> keyed by code, in the display order of EvaluationRouting */
    private function requirements($representative): Collection
    {
        $codes = EvaluationRouting::codesTo('hte');

        return DocumentRequirement::where('campus_id', $representative->hte->campus_id)
            ->where('is_active', true)
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code')
            ->sortBy(fn ($r) => array_search($r->code, $codes));
    }

    private function representative()
    {
        $representative = auth()->user()->hteRepresentative()->with('hte')->first();
        abort_unless($representative, 403, 'Your HTE representative profile is not linked to a Host Training Establishment.');

        return $representative;
    }
}
