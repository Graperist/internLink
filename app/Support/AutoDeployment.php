<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Hte;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deployments are no longer assigned by hand. The student already chooses
 * their firm / agency on the Application for OJT (Form 1), so once the
 * subject instructor accepts that application the student gets a pending
 * deployment at that HTE automatically.
 *
 *   - Print-and-sign mode (the official Form 1): "accepted" = the
 *     instructor clicks Print for Signing.
 *   - Direct-review mode: "accepted" = the instructor approves it.
 *
 * Accepting a corrected application again moves a still-pending
 * deployment to the newly chosen HTE. An active, completed or terminated
 * deployment is never touched - by then the placement is real.
 *
 * Starting the OJT (dates + status "active") stays a separate, deliberate
 * step for the instructor in My Students - see AdviseeController@activate.
 */
class AutoDeployment
{
    public const APPLICATION_CODE = 'APPLICATION_FOR_OJT';

    /**
     * @return string|null a short message for the instructor, or null when
     *                     the document is not an application form
     */
    public static function fromAcceptedApplication(Document $document): ?string
    {
        $document->loadMissing('requirement', 'fieldValues', 'internship.student.user', 'internship.deployment');

        if ($document->requirement?->code !== self::APPLICATION_CODE) {
            return null;
        }

        $internship = $document->internship;
        $hteName = trim((string) $document->fieldValue('hte_name'));

        $hte = $hteName === '' ? null : Hte::where('campus_id', $internship->campus_id)
            ->where('name', $hteName)
            ->first();

        if (! $hte) {
            return 'No deployment was created: the firm on this application ("'.($hteName ?: 'blank')
                .'") is not on the campus partner HTE list.';
        }

        $existing = $internship->deployment;

        if ($existing && $existing->status !== 'pending') {
            return null; // placement already started or finished - leave it alone
        }

        if ($existing && (int) $existing->hte_id === (int) $hte->id) {
            return null; // already pending at this HTE
        }

        // Respect the slots the HTE gave the program this term (normally
        // already checked by blockedReason() before the form was accepted).
        if ($full = self::fullMessage($internship, $hte)) {
            return 'No deployment was created: '.$full;
        }

        DB::transaction(function () use ($internship, $existing, $hte) {
            if ($existing) {
                $existing->update(['hte_id' => $hte->id]);
            } else {
                $internship->deployment()->create(['hte_id' => $hte->id, 'status' => 'pending']);
            }
        });

        $studentName = $internship->student?->user?->name ?? 'A student';

        if ($studentUserId = $internship->student?->user_id) {
            Notification::create([
                'user_id' => $studentUserId,
                'type' => 'deployment',
                'title' => "Placement recorded: {$hte->name}",
                'message' => "Your application was accepted. You are set for deployment at {$hte->name} once all pre-OJT requirements are approved.",
                'url' => route('student.hte', [], false),
            ]);
        }

        User::where('campus_id', $internship->campus_id)
            ->whereHas('role', fn ($q) => $q->where('code', 'coordinator'))
            ->where('status', 'active')
            ->pluck('id')
            ->each(fn ($id) => Notification::create([
                'user_id' => $id,
                'type' => 'deployment',
                'title' => "{$studentName} placed at {$hte->name}",
                'message' => 'Pending deployment created from the accepted Application for OJT.',
                'url' => route('instructor.advisees', ['search' => $internship->student?->user?->id_number], false),
            ]));

        AuditLogger::record(
            'deployment.auto_created',
            ($existing ? 'Pending deployment moved' : 'Pending deployment created')." at {$hte->name} from the accepted Application for OJT.",
            auth()->user(),
            request(),
            $internship->deployment()->first(),
            $internship->campus_id,
        );

        return ($existing ? 'Placement updated' : 'Pending deployment created')." at {$hte->name}.";
    }

    /**
     * Why an Application for OJT can't be accepted right now, or null.
     * Checked BEFORE the instructor's Print for Signing / Approve changes
     * the form, so a full agency never gets a printed application.
     */
    public static function blockedReason(Document $document): ?string
    {
        $document->loadMissing('requirement', 'fieldValues', 'internship.deployment');

        if ($document->requirement?->code !== self::APPLICATION_CODE) {
            return null;
        }

        $internship = $document->internship;
        $existing = $internship->deployment;
        if ($existing && $existing->status !== 'pending') {
            return null;
        }

        $hte = Hte::where('campus_id', $internship->campus_id)
            ->where('name', trim((string) $document->fieldValue('hte_name')))
            ->first();

        if (! $hte || ($existing && (int) $existing->hte_id === (int) $hte->id)) {
            return null;
        }

        $full = self::fullMessage($internship, $hte);

        return $full ? $full.' Return the application so the student can choose another partner agency, or add slots in Partner Agencies.' : null;
    }

    private static function fullMessage($internship, Hte $hte): ?string
    {
        [$academicYear, $semester] = Term::current();
        $slot = \App\Http\Controllers\Instructor\HteController::availability(
            $internship->campus_id, $internship->program_id, $academicYear, $semester
        )[$hte->id] ?? null;

        return $slot && $slot['full']
            ? "{$hte->name} has no slots left this term ({$slot['taken']} of {$slot['slots']} taken)."
            : null;
    }
}
