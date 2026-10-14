<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinator\StoreDeploymentRequest;
use App\Models\Hte;
use App\Models\Internship;
use App\Models\Student;
use App\Support\PreDeployment;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeploymentController extends Controller
{
    /**
     * HTE assignment and deployment status per student, scoped to the coordinator's campus.
     * MOA processing is outside InternLink's scope.
     */
    public function index(): View
    {
        $coordinator = auth()->user();

        $students = Student::with(['user', 'program', 'currentInternship.deployment.hte', 'currentInternship.documents.requirement'])
            ->where('campus_id', $coordinator->campus_id)
            ->latest('id')
            ->paginate(20);

        $htes = Hte::where('campus_id', $coordinator->campus_id)->orderBy('name')->get();

        return view('coordinator.deployments.index', compact('students', 'htes'));
    }

    /**
     * Assign or update a student's HTE placement. Creates the underlying
     * internship record on first assignment if the student doesn't have
     * an open one yet, then creates/updates the one deployment tied to it.
     */
    public function store(StoreDeploymentRequest $request, Student $student): RedirectResponse
    {
        $coordinator = auth()->user();

        abort_unless($student->campus_id === $coordinator->campus_id, 403);

        DB::transaction(function () use ($request, $student, $coordinator) {
            // A brand-new HTE typed directly into the assign form.
            $hteId = $request->validated('hte_id');
            if (! $hteId) {
                $hte = Hte::create([
                    'campus_id' => $coordinator->campus_id,
                    'name' => $request->validated('new_hte_name'),
                    'address' => $request->validated('new_hte_address'),
                    'moa_status' => 'pending',
                ]);
                $hteId = $hte->id;
            }

            // Reuse the student's open internship record, or start one -
            // an internship can exist before an HTE is assigned (pre-deployment).
            $internship = $student->internships()
                ->whereIn('status', ['pre_deployment', 'deployed'])
                ->latest('id')
                ->first();

            if (! $internship) {
                $internship = Internship::create([
                    'student_id' => $student->id,
                    'campus_id' => $student->campus_id,
                    'program_id' => $student->program_id,
                    'instructor_id' => $student->instructor_id,
                    'required_hours' => $student->program->required_hours,
                    'status' => 'pre_deployment',
                ]);
            }

            $requestedStatus = $request->validated('status');

            // Activation is a guarded transition. The coordinator may assign an HTE
            // while pending, but cannot bypass the required pre-OJT document clearance.
            if ($requestedStatus === 'active' && ! PreDeployment::isComplete($internship)) {
                throw ValidationException::withMessages([
                    'status' => 'Deployment cannot be activated until every active, required pre-OJT student document has been approved. MOA processing is not part of this digital readiness check.',
                ]);
            }

            $deployment = $internship->deployment ?: $internship->deployment()->make();
            abort_if($deployment->exists && $deployment->status === 'completed', 422, 'A completed deployment cannot be reopened or edited.');
            $deployment->hte_id = $hteId;
            $deployment->start_date = $request->validated('start_date');
            $deployment->end_date = $request->validated('end_date');
            $deployment->status = $requestedStatus;
            $deployment->save();

            $internship->update([
                'status' => match ($deployment->status) {
                    'active' => 'deployed',
                    'terminated' => 'terminated',
                    default => 'pre_deployment',
                },
            ]);
        });

        return redirect()
            ->route('coordinator.deployments.index')
            ->with('status', $student->user->name.'\'s deployment was saved.');
    }
}
