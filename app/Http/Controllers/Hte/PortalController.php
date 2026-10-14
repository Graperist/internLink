<?php

namespace App\Http\Controllers\Hte;

use App\Http\Controllers\Concerns\ManagesProfilePhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hte\UpdateHteProfileRequest;
use App\Models\Deployment;
use App\Models\Dtr;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Instructor;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalController extends Controller
{
    use ManagesProfilePhoto;

    protected function profileRouteName(): string
    {
        return 'hte.profile';
    }

    public function dashboard(): View
    {
        $representative = $this->representative();
        $hteId = $representative->hte_id;
        $assignedDeployments = Deployment::where('hte_id', $hteId)->whereIn('status', ['pending','active','completed'])->with(['internship.student.user','internship.student.program','internship','hte'])->get();
        $pendingDtrCount = Dtr::whereHas('deployment', fn($q) => $q->where('hte_id',$hteId))->where('status','pending')->count();

        // Dashboard evaluation counts come only from official HTE forms. The legacy
        // numeric score record is not an independent evaluation workflow.
        $hteFormCodes = ['EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK', 'PARTNER_AGENCY_EVAL_SELF'];
        $hteFormRequirementIds = DocumentRequirement::where('campus_id', $representative->hte->campus_id)
            ->where('is_active', true)->where('audience', 'hte')->whereIn('code', $hteFormCodes)->pluck('id');
        $submittedDocumentStatuses = ['submitted', 'for_review', 'approved', 'printed', 'completed'];
        $evaluationsSubmitted = Document::whereIn('document_requirement_id', $hteFormRequirementIds)
            ->whereIn('status', $submittedDocumentStatuses)
            ->whereHas('internship.deployment', fn ($q) => $q->where('hte_id', $hteId)->where('status', 'completed'))
            ->count();
        $eligibleDeploymentCount = $assignedDeployments->where('status', 'completed')->count();
        $evaluationsPending = max(0, ($eligibleDeploymentCount * $hteFormRequirementIds->count()) - $evaluationsSubmitted);
        $todayAttendance = Dtr::whereHas('deployment', fn($q) => $q->where('hte_id',$hteId))->whereDate('log_date',today())->with(['deployment.internship.student.user','deployment.internship.student.program','deployment.hte'])->orderBy('time_in')->get();
        return view('hte.dashboard', compact('representative','assignedDeployments','pendingDtrCount','evaluationsSubmitted','evaluationsPending','todayAttendance'));
    }

    public function assignedStudents(): View
    {
        $representative = $this->representative();
        $deployments = Deployment::where('hte_id',$representative->hte_id)->with(['internship.student.user','internship.student.program','internship.student','evaluation'])->whereIn('status',['pending','active','completed'])->latest('id')->get();
        return view('hte.assigned-students', compact('representative','deployments'));
    }

    public function studentDtrHistory(Deployment $deployment): View
    {
        $representative = $this->representative();
        abort_unless($deployment->hte_id === $representative->hte_id, 403);

        $deployment->load(['internship.student.user', 'internship.student.program', 'hte']);
        $student = $deployment->internship->student;

        $entries = Dtr::where('deployment_id', $deployment->id)
            ->latest('log_date')
            ->latest('id')
            ->get();

        $totalHours = $entries->sum('hours_logged');

        return view('hte.student-dtr-history', compact('representative', 'deployment', 'student', 'entries', 'totalHours'));
    }

    public function dtrVerification(): View
    {
        $representative = $this->representative();
        // Show every DTR record belonging to interns assigned to this HTE.
        // Verification actions are still restricted by status in the view/controller.
        // Previously this page only queried pending/flagged records, so an already
        // approved DTR disappeared from the HTE portal.
        $entries = Dtr::whereHas('deployment', fn($q) => $q->where('hte_id',$representative->hte_id))
            ->with(['deployment.internship.student.user','deployment.internship.student.program','deployment.hte'])
            ->latest('log_date')
            ->latest('id')
            ->get();
        return view('hte.dtr-verification', compact('representative','entries'));
    }

    /**
     * Verify the clock-in, the clock-out, or both events of a DTR entry.
     *
     * The clock-in can be verified as soon as the student has clocked in; the
     * clock-out only once it exists. The entry becomes 'approved' (and the
     * hours are credited to the student) exactly once, when both events
     * have been verified.
     */
    public function verifyDtr(Request $request, Dtr $dtr): RedirectResponse
    {
        $representative = $this->representative();
        $validated = $request->validate([
            'target' => ['required', Rule::in(['clock_in', 'clock_out', 'both'])],
        ]);
        $target = $validated['target'];

        $message = DB::transaction(function () use ($dtr, $representative, $target) {
            // Lock the row so two quick clicks can't credit the hours twice.
            $dtr = Dtr::whereKey($dtr->id)->lockForUpdate()->firstOrFail();
            $dtr->load('deployment.internship.student');

            abort_unless($dtr->deployment && $dtr->deployment->hte_id === $representative->hte_id, 403);
            abort_if($dtr->status === 'approved', 422, 'This DTR entry is already verified.');
            abort_if($dtr->status === 'flagged', 422, 'A flagged DTR must be resolved by the student before re-verification.');

            $wantsIn = in_array($target, ['clock_in', 'both'], true);
            $wantsOut = in_array($target, ['clock_out', 'both'], true);

            abort_if($wantsIn && ! $dtr->time_in, 422, 'There is no clock-in to verify yet.');
            abort_if($wantsOut && ! $dtr->time_out, 422, 'There is no clock-out to verify yet. The student has not clocked out.');
            abort_if($wantsIn && $dtr->isClockInVerified(), 422, 'The clock-in is already verified.');
            abort_if($wantsOut && $dtr->isClockOutVerified(), 422, 'The clock-out is already verified.');

            $updates = [];
            if ($wantsIn) {
                $updates['clock_in_verified_by'] = $representative->id;
                $updates['clock_in_verified_at'] = now();
            }
            if ($wantsOut) {
                $updates['clock_out_verified_by'] = $representative->id;
                $updates['clock_out_verified_at'] = now();
            }
            $dtr->fill($updates);

            if ($dtr->isFullyVerified()) {
                $dtr->verified_by = $representative->id;
                $dtr->status = 'approved';
                $dtr->save();
                $dtr->deployment->internship->student->increment('hours_rendered', (float) $dtr->hours_logged);

                return 'Clock-in and clock-out verified. DTR approved and hours credited.';
            }

            $dtr->save();

            return $wantsIn
                ? 'Clock-in verified. Hours will be credited once the clock-out is verified too.'
                : 'Clock-out verified. Hours will be credited once the clock-in is verified too.';
        });

        return redirect()->route('hte.dtr')->with('status', $message);
    }

    public function flagDtr(Request $request, Dtr $dtr): RedirectResponse
    {
        $representative = $this->representative();
        $dtr->load(['deployment.internship.student.user','deployment.internship.student.program','deployment.internship.student.instructor.user','deployment.hte']);
        abort_unless($dtr->deployment && $dtr->deployment->hte_id === $representative->hte_id,403);
        abort_if($dtr->status === 'approved',422,'An approved DTR entry cannot be flagged.');

        // When the entry hasn't been clocked out yet, only "Clock-In" is a valid target
        // (there is no clock-out to flag). Otherwise the HTE can flag either event or both.
        $allowedTargets = $dtr->time_out ? ['clock_in', 'clock_out', 'both'] : ['clock_in'];

        $validated = $request->validate([
            'flag_reason' => ['required','string','max:255'],
            'flag_note' => ['nullable','string','max:1000'],
            'flag_target' => ['required', Rule::in($allowedTargets)],
        ]);

        DB::transaction(function() use($dtr,$representative,$validated){
            // Flagging withdraws verification of the flagged event(s) only;
            // an event that was verified and not flagged stays verified.
            $clearVerification = [];
            if (in_array($validated['flag_target'], ['clock_in', 'both'], true)) {
                $clearVerification += ['clock_in_verified_by' => null, 'clock_in_verified_at' => null];
            }
            if (in_array($validated['flag_target'], ['clock_out', 'both'], true)) {
                $clearVerification += ['clock_out_verified_by' => null, 'clock_out_verified_at' => null];
            }

            $dtr->update($clearVerification + [
                'original_time_in' => $dtr->original_time_in ?: $dtr->time_in,
                'original_time_out' => $dtr->original_time_out ?: $dtr->time_out,
                'verified_by' => null,
                'flagged_by' => $representative->id,
                'flagged_at' => now(),
                'flag_reason' => $validated['flag_reason'],
                'flag_note' => $validated['flag_note'] ?? null,
                'flag_target' => $validated['flag_target'],
                'status' => 'flagged',
            ]);

            $student = $dtr->deployment->internship->student;
            $studentName = $student?->user?->name ?? 'Student';
            $date = $dtr->log_date?->format('F d, Y') ?? 'Unknown date';
            $hteName = $dtr->deployment->hte?->name ?? 'Assigned HTE';
            $targetLabel = $dtr->flagTargetLabel() ?? 'Entry';
            $message = "Student: {$studentName}\nDTR Date: {$date}\nHTE: {$hteName}\nStatus: Flagged\nFlagged: {$targetLabel}\nReason: {$validated['flag_reason']}";
            if (trim((string)($validated['flag_note'] ?? '')) !== '') $message .= "\nHTE Note: ".trim($validated['flag_note']);

            $recipientIds = collect([$student?->user_id])->filter();

            // Notify only the student's actual Subject Instructor. If the
            // internship has no direct instructor assignment, use one active
            // campus/program instructor as a deterministic fallback.
            $assignedInstructor = $dtr->deployment?->internship?->instructor ?: $student?->instructor;
            $instructorUserId = null;
            if ($assignedInstructor?->user?->status === 'active') {
                $instructorUserId = $assignedInstructor->user_id;
            } else {
                $instructorUserId = Instructor::where('campus_id', $student->campus_id)
                    ->where('program_id', $student->program_id)
                    ->whereHas('user', fn ($q) => $q->where('status', 'active'))
                    ->orderBy('id')
                    ->value('user_id');
            }
            if ($instructorUserId) {
                $recipientIds->push($instructorUserId);
            }

            User::where('campus_id', $student->campus_id)
                ->whereHas('role', fn ($q) => $q->where('code', 'coordinator'))
                ->where('status', 'active')
                ->pluck('id')
                ->each(fn ($id) => $recipientIds->push($id));

            $recipientIds->unique()->each(fn ($id) => Notification::create([
                'user_id' => $id,
                'type' => 'dtr',
                'resolve_key' => 'dtr:'.$dtr->id,
                'title' => "DTR Flagged — {$studentName}",
                'message' => $message,
            ]));
        });

        return redirect()->route('hte.dtr')->with('status','DTR entry flagged. Student, Subject Instructor, and OJT Coordinator were notified.');
    }

    public function evaluations(): View
    {
        $representative = $this->representative();
        $deployments = Deployment::where('hte_id', $representative->hte_id)
            ->whereIn('status', ['active', 'completed'])
            ->with(['internship.student.user', 'internship.student.program', 'evaluation', 'internship.documents.requirement'])
            ->latest('id')
            ->get();

        $officialFormRequirements = \App\Models\DocumentRequirement::where('campus_id', $representative->hte->campus_id)
            ->where('is_active', true)
            ->whereIn('code', ['EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK', 'PARTNER_AGENCY_EVAL_SELF'])
            ->orderBy('display_order')
            ->get()
            ->keyBy('code');

        return view('hte.evaluations', compact('representative', 'deployments', 'officialFormRequirements'));
    }

    /**
     * My Profile - two independent forms: the HTE's own company/site
     * details (including the DTR location-security fields the coordinator
     * also manages, so the on-site supervisor can correct or capture them
     * without going through the coordinator), and the representative's
     * own account name/email. moa_status is never exposed here - that
     * partnership approval stays coordinator-only.
     */
    public function profile(): View
    {
        $representative = $this->representative();

        return view('hte.profile', compact('representative'));
    }

    public function updateCompanyProfile(UpdateHteProfileRequest $request): RedirectResponse
    {
        $representative = $this->representative();
        $representative->hte->update($request->validated());

        return redirect()->route('hte.profile')->with('status', 'Company profile updated.');
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $representative = $this->representative();
        $user = $representative->user;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return redirect()->route('hte.profile')->with('status', 'Account details updated.');
    }

    private function representative()
    {
        $representative=auth()->user()->hteRepresentative()->with('hte')->first();
        abort_unless($representative,403,'Your HTE representative profile is not linked to a Host Training Establishment.');
        return $representative;
    }
}
