<?php

namespace App\Support;

use App\Models\Deployment;
use App\Models\Dtr;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Closes deployments whose end date has passed.
 *
 * Without this, a deployment stays "Active" forever unless the coordinator
 * remembers to edit it, and the student never gets their post-deployment
 * requirements. Marking it Completed also updates the internship (the two
 * always move together, exactly as the Coordinator's Deployment form does).
 */
class DeploymentLifecycle
{
    /**
     * Complete every active deployment (optionally limited to one campus or
     * one student's internship) whose end date is before today.
     *
     * @return int how many deployments were completed
     */
    public static function completeEnded(?int $campusId = null, ?int $internshipId = null): int
    {
        $query = Deployment::query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', today())
            ->with('internship.student.user');

        if ($campusId) {
            $query->whereHas('internship', fn ($q) => $q->where('campus_id', $campusId));
        }
        if ($internshipId) {
            $query->where('internship_id', $internshipId);
        }

        $completed = 0;

        foreach ($query->get() as $deployment) {
            $internship = $deployment->internship;
            if (! $internship) {
                continue;
            }

            // Don't pull the rug out from under a student who is clocked in
            // right now - they still need to clock out. Next run picks it up.
            $clockedInNow = Dtr::where('deployment_id', $deployment->id)
                ->whereDate('log_date', today())
                ->whereNotNull('time_in')
                ->whereNull('time_out')
                ->exists();
            if ($clockedInNow) {
                continue;
            }

            DB::transaction(function () use ($deployment, $internship) {
                $deployment->update(['status' => 'completed']);

                // Only move a still-running internship; never overwrite
                // 'terminated' or an already 'completed' one.
                if ($internship->status === 'deployed') {
                    $internship->update(['status' => 'completed']);
                }
            });

            $userId = $internship->student?->user_id;
            if ($userId) {
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'deployment_completed',
                    'title' => 'Deployment completed',
                    'message' => "Your deployment ended on {$deployment->end_date->format('M d, Y')} and is now marked Completed. Your post-deployment requirements are available under My Documents.",
                    'url' => route('student.documents'),
                ]);
            }

            AuditLogger::record(
                'deployment.auto_completed',
                "Deployment #{$deployment->id} marked Completed automatically (end date {$deployment->end_date->toDateString()} passed).",
                null,
                null,
                $deployment,
                $internship->campus_id,
            );

            $completed++;
        }

        return $completed;
    }
}
