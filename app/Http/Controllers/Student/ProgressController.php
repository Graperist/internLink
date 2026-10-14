<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Support\EvaluationRouting;
use Illuminate\View\View;

class ProgressController extends Controller
{
    /**
     * Figure 24 - My Progress. The specific due-dates shown in the mockup
     * ("Due Apr 15") aren't real data - there's no due-date field on
     * document_requirements, so the timeline shows status only, not dates
     * that would be fabricated.
     */
    public function index(): View
    {
        $student = auth()->user()->student;
        $internship = $student->currentInternship;
        $deployment = $internship?->deployment;

        $requiredHours = $student->program?->required_hours;
        $requiredHours = is_numeric($requiredHours) && (float) $requiredHours > 0 ? (float) $requiredHours : null;
        $hoursPercent = $requiredHours !== null ? min(100, (int) round(($student->hours_rendered / $requiredHours) * 100)) : 0;

        $requirementsCount = DocumentRequirement::where('campus_id', $student->campus_id)->where('is_active', true)->whereIn('audience', ['student', 'coordinator'])->count();
        $submittedCount = $internship ? $internship->documents()->count() : 0;
        $docsPercent = $requirementsCount > 0 ? min(100, (int) round(($submittedCount / $requirementsCount) * 100)) : 0;

        // DTR compliance: weeks with at least one entry vs. weeks elapsed since deployment start.
        $dtrPercent = 0;
        $weeksElapsed = 0;
        $weeksWithDtr = 0;
        if ($deployment && $deployment->start_date) {
            $weeksElapsed = max(1, now()->diffInWeeks($deployment->start_date) + 1);
            $weeksWithDtr = $deployment->dtrs()
                ->get()
                ->groupBy(fn ($dtr) => $dtr->log_date->format('o-W'))
                ->count();
            $dtrPercent = min(100, (int) round(($weeksWithDtr / $weeksElapsed) * 100));
        }

        $overallPercent = (int) round(($hoursPercent + $docsPercent + $dtrPercent) / 3);

        $timeline = $this->buildTimeline($internship, $deployment);

        return view('student.progress', compact(
            'hoursPercent', 'docsPercent', 'dtrPercent', 'overallPercent',
            'student', 'requiredHours', 'submittedCount', 'requirementsCount',
            'weeksWithDtr', 'weeksElapsed', 'timeline'
        ));
    }

    private function buildTimeline($internship, $deployment): array
    {
        $steps = [];

        $steps[] = [
            'label' => 'Pre-OJT Requirements',
            'status' => $internship && $internship->status !== 'pre_deployment' ? 'Completed' : 'In Progress',
        ];

        $steps[] = [
            'label' => 'Deployment',
            'status' => match (true) {
                ! $deployment => 'Upcoming',
                $deployment->status === 'pending' => 'In Progress',
                default => 'Completed',
            },
        ];

        $steps[] = [
            'label' => 'Active OJT Period',
            'status' => match ($deployment?->status) {
                'active' => 'In Progress',
                'completed', 'terminated' => 'Completed',
                default => 'Upcoming',
            },
        ];

        $steps[] = [
            'label' => 'OJT Completion',
            'status' => $internship?->status === 'completed' ? 'Completed' : 'Upcoming',
        ];

        $steps[] = [
            'label' => 'Narrative Report Submission',
            'status' => $internship?->documents()->whereHas('requirement', fn ($q) => $q->where('code', 'NARRATIVE_REPORT'))->exists()
                ? 'Completed' : 'Upcoming',
        ];

        // Done once the host company has sent the student its evaluation form(s)
        // (Form 21 - Training Supervisor's Feedback, shown under My HTE Info).
        $evaluationReceived = $internship?->documents()
            ->whereIn('status', EvaluationRouting::SUBMITTED)
            ->whereHas('requirement', fn ($q) => $q->whereIn('code', EvaluationRouting::codesTo('student')))
            ->exists();

        $steps[] = [
            'label' => 'HTE Evaluation',
            'status' => ($evaluationReceived || $deployment?->evaluation) ? 'Completed' : 'Upcoming',
        ];

        return $steps;
    }
}
