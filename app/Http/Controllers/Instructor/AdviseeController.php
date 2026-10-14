<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\DocumentRequirement;
use App\Models\Dtr;
use App\Models\Notification;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdviseeController extends Controller
{
    /**
     * My Students - matches the manuscript prototype's columns (Required
     * Hours / HTE Assignment / Doc Status / Deployment Status / Action)
     * rather than the earlier generic version. Scoped to every student in
     * this instructor's program at their campus (Instructor::advisees()).
     */
    public function index(Request $request): View
    {
        $instructor = auth()->user()->instructor;

        $requiredDocsCount = DocumentRequirement::where('campus_id', $instructor->campus_id)
            ->where('is_required', true)
            ->count();

        $query = $this->filteredAdvisees($instructor, $request);

        $advisees = $query->latest('id')->paginate(20)->withQueryString();

        return view('instructor.advisees.index', compact('advisees', 'requiredDocsCount'));
    }

    /**
     * Confirm internship completion only after the scheduled end date and
     * verified DTR hours satisfy the internship requirement. This action is
     * instructor-only; the end date alone never completes a deployment.
     */
    public function confirmCompletion(Deployment $deployment): RedirectResponse
    {
        $user = auth()->user();
        $instructor = $user->instructor;
        abort_unless($instructor, 403);

        $result = DB::transaction(function () use ($deployment, $instructor, $user) {
            $locked = Deployment::query()->whereKey($deployment->id)->lockForUpdate()->firstOrFail();
            $locked->load(['internship.student.program', 'internship.program']);
            $internship = $locked->internship;
            abort_unless($internship && $internship->campus_id === $instructor->campus_id
                && $internship->program_id === $instructor->program_id, 403);

            if ($locked->status !== 'active' || $internship->status !== 'deployed') {
                throw ValidationException::withMessages([
                    'completion' => 'Only an active internship can be confirmed as completed.',
                ]);
            }

            if (! $locked->start_date || ! $locked->end_date) {
                throw ValidationException::withMessages([
                    'completion' => 'A start date and end date must be set before completion can be confirmed.',
                ]);
            }

            if ($locked->start_date->isAfter($locked->end_date)) {
                throw ValidationException::withMessages([
                    'completion' => 'The deployment dates are invalid. The start date cannot be after the end date.',
                ]);
            }

            if ($locked->end_date->isFuture()) {
                throw ValidationException::withMessages([
                    'completion' => 'The scheduled end date has not been reached yet.',
                ]);
            }

            $requiredHours = (float) ($internship->program?->required_hours ?? 0);

            if ($requiredHours <= 0) {
                throw ValidationException::withMessages([
                    'completion' => 'The required internship hours are not configured. Ask the coordinator to correct the internship record.',
                ]);
            }

            $dtrsInPeriod = Dtr::query()
                ->where('deployment_id', $locked->id)
                ->whereDate('log_date', '>=', $locked->start_date->toDateString())
                ->whereDate('log_date', '<=', $locked->end_date->toDateString())
                ->whereDate('log_date', '<=', today());

            $verifiedHours = (float) (clone $dtrsInPeriod)
                ->where('status', 'approved')
                ->whereNotNull('time_in')
                ->whereNotNull('time_out')
                ->whereNotNull('clock_in_verified_at')
                ->whereNotNull('clock_out_verified_at')
                ->sum('hours_logged');

            $hasUnverifiedEntries = (clone $dtrsInPeriod)
                ->where(function ($query) {
                    $query->where('status', '!=', 'approved')
                        ->orWhereNull('time_in')
                        ->orWhereNull('time_out')
                        ->orWhereNull('clock_in_verified_at')
                        ->orWhereNull('clock_out_verified_at');
                })
                ->exists();

            if ($hasUnverifiedEntries) {
                throw ValidationException::withMessages([
                    'completion' => 'There are DTR entries within the internship period that are still pending, flagged, incomplete, or not fully verified. Resolve them before confirming completion.',
                ]);
            }

            if ($verifiedHours < $requiredHours) {
                $remaining = number_format(max($requiredHours - $verifiedHours, 0), 2);
                throw ValidationException::withMessages([
                    'completion' => "The student has {$verifiedHours} verified hours out of {$requiredHours} required hours. {$remaining} more verified hours are needed.",
                ]);
            }

            $locked->update(['status' => 'completed']);
            $internship->update(['status' => 'completed']);

            $studentUserId = $internship->student?->user_id;
            if ($studentUserId) {
                Notification::create([
                    'user_id' => $studentUserId,
                    'type' => 'deployment_completed',
                    'title' => 'Internship completion confirmed',
                    'message' => 'Your instructor confirmed your internship completion after checking the end date and verified DTR hours. Your post-OJT requirements are now available under My Documents.',
                    'url' => route('student.documents'),
                ]);
            }

            return ['hours' => $verifiedHours, 'required' => $requiredHours, 'campus_id' => $internship->campus_id];
        });

        AuditLogger::record(
            'deployment.instructor_completed',
            "Instructor confirmed internship completion with {$result['hours']} of {$result['required']} verified DTR hours.",
            $user,
            request(),
            $deployment,
            $result['campus_id'],
        );

        return redirect()->route('instructor.advisees')
            ->with('status', 'Internship completion confirmed. Post-OJT requirements are now available to the student.');
    }

    /**
     * Same filtered list as index(), unpaginated, as a downloadable CSV -
     * the "Improve via Excel" link in the prototype (read as "Export via
     * Excel"; the mockup's own label appears to be a typo, since nothing
     * on this page is editable).
     */
    public function export(Request $request): StreamedResponse
    {
        $instructor = auth()->user()->instructor;

        $requiredDocsCount = DocumentRequirement::where('campus_id', $instructor->campus_id)
            ->where('is_required', true)
            ->count();

        $advisees = $this->filteredAdvisees($instructor, $request)->get();

        $callback = function () use ($advisees, $requiredDocsCount) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name', 'ID Number', 'Required Hours', 'HTE Assignment',
                'Documents Approved', 'Documents Required', 'Deployment Status',
            ]);

            foreach ($advisees as $student) {
                $internship = $student->currentInternship;
                $deployment = $internship?->deployment;

                fputcsv($handle, [
                    $student->user->name,
                    $student->user->id_number,
                    $internship?->required_hours ?? $student->program->required_hours,
                    $deployment?->hte?->name ?? '',
                    $student->approved_documents_count,
                    $requiredDocsCount,
                    $deployment?->status ? ucfirst($deployment->status) : 'Not deployed',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="my-students-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    private function filteredAdvisees($instructor, Request $request)
    {
        $query = $instructor->advisees()
            ->with(['user', 'program', 'currentInternship.deployment.hte', 'currentInternship.deployment.dtrs'])
            ->withCount(['documents as approved_documents_count' => fn ($q) => $q->where('documents.status', 'approved')]);

        if ($search = $request->query('search')) {
            $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%")
            );
        }

        if ($status = $request->query('status')) {
            $status === 'not_deployed'
                ? $query->whereDoesntHave('currentInternship.deployment')
                : $query->whereHas('currentInternship.deployment', fn ($q) => $q->where('status', $status));
        }

        return $query;
    }
}
