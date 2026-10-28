<?php

namespace App\Support;

use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\Instructor;
use Illuminate\Support\Collection;

/**
 * One-per-agency evaluations (Form 23a by the Subject Instructor, Form 23b by
 * the agency itself). Each is stored as a document "about" the agency
 * (documents.hte_id), filed under one completed deployment there so the
 * official printouts have the campus/program context they need.
 */
class AgencyEvaluations
{
    /**
     * The agencies where the instructor's students finished their internship,
     * each with those deployments: [hte_id => ['hte' => Hte, 'deployments' => Collection]].
     */
    public static function agenciesFor(Instructor $instructor, ?int $programId = null): Collection
    {
        // The coordinator: every program of the campus (or the one picked); an instructor: their own program.
        return Deployment::where('status', 'completed')
            ->whereHas('internship', fn ($q) => $instructor->limitToEvaluationScope($q)->when($programId, fn ($q) => $q->where('program_id', $programId)))
            ->with(['hte', 'internship.student.user', 'internship.student.program'])
            ->get()
            ->filter(fn ($d) => $d->hte)
            ->groupBy('hte_id')
            ->map(fn ($deployments) => ['hte' => $deployments->first()->hte, 'deployments' => $deployments->sortBy(fn ($d) => $d->internship->student->user->name)->values()])
            ->sortBy(fn ($row) => $row['hte']->name)
            ->values()
            ->keyBy(fn ($row) => $row['hte']->id);
    }

    public static function requirement(string $code, int $campusId): ?DocumentRequirement
    {
        return DocumentRequirement::where('campus_id', $campusId)->where('code', $code)->where('is_active', true)->first();
    }

    /** The agency's evaluation on this form (for Form 23a: the one this user made). */
    public static function find(DocumentRequirement $requirement, int $hteId, ?int $byUserId = null): ?Document
    {
        return Document::where('document_requirement_id', $requirement->id)
            ->where('hte_id', $hteId)
            ->when($byUserId, fn ($q) => $q->where('uploaded_by_user_id', $byUserId))
            ->latest('id')
            ->first();
    }

    /** A completed deployment at the agency to file the evaluation under (within the instructor's scope, if given). */
    public static function anchor(Hte $hte, ?Instructor $instructor = null): ?Deployment
    {
        return Deployment::where('hte_id', $hte->id)
            ->where('status', 'completed')
            ->when($instructor, fn ($q) => $q->whereHas('internship', fn ($i) => $instructor->limitToOwnProgram($i)))
            ->latest('end_date')
            ->latest('id')
            ->first();
    }
}
