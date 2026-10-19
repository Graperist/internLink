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

        // Evaluations to do: Forms 13 and 21 for every intern who finished,
        // plus the agency's own Form 23b once.
        $hteFormRequirementIds = DocumentRequirement::where('campus_id', $representative->hte->campus_id)
            ->where('is_active', true)->where('audience', 'hte')->whereIn('code', \App\Support\EvaluationRouting::HTE_INTERN_CODES)->pluck('id');
        $completedInternshipIds = $assignedDeployments->where('status', 'completed')->pluck('internship_id');
        $evaluationsSubmitted = Document::whereIn('document_requirement_id', $hteFormRequirementIds)
            ->whereIn('status', \App\Support\EvaluationRouting::SUBMITTED)
            ->whereIn('internship_id', $completedInternshipIds)
            ->count();
        $evaluationsPending = max(0, $completedInternshipIds->count() * $hteFormRequirementIds->count() - $evaluationsSubmitted);
        $selfRequirement = \App\Support\AgencyEvaluations::requirement('PARTNER_AGENCY_EVAL_SELF', (int) $representative->hte->campus_id);
        if ($selfRequirement && $completedInternshipIds->isNotEmpty()) {
            $selfDone = \App\Support\EvaluationRouting::isSubmitted(\App\Support\AgencyEvaluations::find($selfRequirement, $hteId));
            $evaluationsSubmitted += $selfDone ? 1 : 0;
            $evaluationsPending += $selfDone ? 0 : 1;
        }
        $todayAttendance = Dtr::whereHas('deployment', fn($q) => $q->where('hte_id',$hteId))->where('status','pending')->with(['deployment.internship.student.user','deployment.internship.student.program','deployment.hte'])->orderBy('log_date')->limit(10)->get();
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
     * Approve a day of the student's Internship Time Frame (Form 17). The
     * hours count right away and the day shows on the instructor's side.
     */
    public function verifyDtr(Request $request, Dtr $dtr): RedirectResponse
    {
        $representative = $this->representative();

        $message = DB::transaction(function () use ($dtr, $representative) {
            // Lock the row so two quick clicks can't credit the hours twice.
            $dtr = Dtr::whereKey($dtr->id)->lockForUpdate()->firstOrFail();
            $dtr->load('deployment.internship.student.user');

            abort_unless($dtr->deployment && $dtr->deployment->hte_id === $representative->hte_id, 403);
            abort_if($dtr->status === 'approved', 422, 'This day is already approved.');
            abort_if($dtr->status === 'flagged', 422, 'This day was declined. The student has to correct it first.');

            $dtr->update([
                'status' => 'approved',
                'verified_by' => $representative->id,
                'clock_in_verified_by' => $representative->id, 'clock_in_verified_at' => now(),
                'clock_out_verified_by' => $representative->id, 'clock_out_verified_at' => now(),
            ]);
            $dtr->deployment->internship->student->increment('hours_rendered', (float) $dtr->hours_logged);

            return 'Approved '.$dtr->deployment->internship->student->user->name.' · '.$dtr->log_date->format('M d, Y')
                .' ('.\App\Support\TimeFrame::format($dtr->hours_logged).' hours).';
        });

        return redirect()->route('hte.dtr')->with('status', $message);
    }

    /** Decline a day: it goes back to the student to be corrected. */
    public function flagDtr(Request $request, Dtr $dtr): RedirectResponse
    {
        $representative = $this->representative();
        $dtr->load(['deployment.internship.student.user', 'deployment.internship.instructor.user', 'deployment.internship.student.instructor.user', 'deployment.hte']);
        abort_unless($dtr->deployment && $dtr->deployment->hte_id === $representative->hte_id, 403);
        abort_if($dtr->status === 'approved', 422, 'An approved day can no longer be declined.');

        $validated = $request->validate([
            'flag_reason' => ['required', 'string', 'max:255'],
            'flag_note' => ['nullable', 'string', 'max:1000'],
        ], ['flag_reason.required' => 'Tell the student why the time is declined.']);

        DB::transaction(function () use ($dtr, $representative, $validated) {
            $dtr->update([
                'status' => 'flagged',
                'verified_by' => null,
                'flagged_by' => $representative->id,
                'flagged_at' => now(),
                'flag_reason' => $validated['flag_reason'],
                'flag_note' => $validated['flag_note'] ?? null,
                'flag_target' => 'both',
                'declined_by_user_id' => auth()->id(),
                'resolved_by' => null,
                'resolved_at' => null,
            ]);

            $student = $dtr->deployment->internship->student;
            $date = $dtr->log_date->format('M d, Y');
            Notification::create([
                'user_id' => $student->user_id,
                'type' => 'dtr',
                'resolve_key' => 'dtr:'.$dtr->id,
                'title' => "Time declined: {$date}",
                'message' => 'Your supervisor declined this day: '.$validated['flag_reason'].'. Correct the time and send it again.',
                'url' => route('student.dtr', ['edit' => $dtr->id], false),
            ]);
        });

        return redirect()->route('hte.dtr')->with('status', 'Declined. The day went back to the student to be corrected.');
    }

    /**
     * Evaluate Interns - like the PSU faculty evaluation: the list of this
     * agency's interns who finished their internship, click a name and rate
     * them (Form 13 Internship Evaluation, Form 21 Supervisor's Feedback),
     * plus the agency's own rating of itself, once (Form 23b). Every one goes
     * to the Subject Instructor; the intern never sees it.
     */
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
