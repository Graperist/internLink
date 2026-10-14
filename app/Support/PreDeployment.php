<?php

namespace App\Support;

use App\Models\DocumentRequirement;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single definition of "pre-deployment requirements" used by the coordinator
 * Intern List / Dashboard / Reports and by the completion notification.
 *
 * A student's pre-deployment requirements are the live (active + required)
 * pre-deployment documents - the same set the instructor reviews. A student
 * has completed them once every one of those documents is approved.
 */
class PreDeployment
{
    /** @return Collection<int,int> requirement ids for the campus */
    public static function requirementIds(int $campusId): Collection
    {
        return DocumentRequirement::where('campus_id', $campusId)
            ->where('is_active', true)
            ->where('is_required', true)
            ->where('stage', 'pre_deployment')
            ->where('code', '!=', 'MOA')
            ->whereIn('audience', ['student', 'hte', 'coordinator'])
            ->pluck('id');
    }

    /**
     * SQL condition (for Student queries) true when the student has FEWER
     * approved pre-deployment documents than required, i.e. is incomplete.
     */
    public static function incompleteSql(Collection $ids): string
    {
        if ($ids->isEmpty()) {
            // Fail closed: if checklist configuration is missing, every student
            // remains incomplete instead of appearing cleared by default.
            return '1 = 1';
        }

        $list = $ids->map(fn ($id) => (int) $id)->implode(',');

        return '(
            select count(distinct documents.document_requirement_id) from documents
            inner join internships on internships.id = documents.internship_id
            where internships.student_id = students.id
            and documents.status = \'approved\'
            and documents.document_requirement_id in ('.$list.')
        ) < '.$ids->count();
    }

    public static function isComplete(Internship $internship): bool
    {
        $ids = self::requirementIds($internship->campus_id);

        if ($ids->isEmpty()) {
            return false;
        }

        $approved = $internship->documents()
            ->where('status', 'approved')
            ->whereIn('document_requirement_id', $ids)
            ->distinct()
            ->count('document_requirement_id');

        return $approved >= $ids->count();
    }

    /**
     * Call after any document becomes approved. When that completes the
     * student's pre-deployment requirements, notify the campus coordinators
     * (once per student).
     */
    public static function notifyCoordinatorsIfComplete(Internship $internship): void
    {
        if (! self::isComplete($internship)) {
            return;
        }

        $student = $internship->student;
        $name = $student?->user?->name ?? 'A student';
        $idNumber = $student?->user?->id_number;

        $title = 'Pre-deployment requirements complete';
        $message = $name.($idNumber ? " ({$idNumber})" : '')
            .' has completed all pre-deployment requirements and is ready for deployment.';

        $coordinators = User::where('campus_id', $internship->campus_id)
            ->whereHas('role', fn ($q) => $q->where('code', 'coordinator'))
            ->where('status', 'active')
            ->get();

        foreach ($coordinators as $coordinator) {
            $alreadyNotified = Notification::where('user_id', $coordinator->id)
                ->where('type', 'pre_deployment_complete')
                ->where('message', $message)
                ->exists();

            if (! $alreadyNotified) {
                Notification::create([
                    'user_id' => $coordinator->id,
                    'type' => 'pre_deployment_complete',
                    'resolve_key' => 'predeploy:'.$student->id,
                    'title' => $title,
                    'message' => $message,
                ]);
            }
        }
    }
}
