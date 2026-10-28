<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The time frame is now the hours the student finished each day (no time in /
 * time out, no HTE approval): every day entered counts. Days still waiting or
 * declined become counted, and each student's rendered hours are recalculated
 * from their days.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('dtrs')->update([
            'status' => 'approved',
            'approved_hours' => DB::raw('hours_logged'),
            'am_status' => null,
            'pm_status' => null,
        ]);

        $totals = DB::table('dtrs')
            ->join('deployments', 'deployments.id', '=', 'dtrs.deployment_id')
            ->join('internships', 'internships.id', '=', 'deployments.internship_id')
            ->groupBy('internships.student_id')
            ->selectRaw('internships.student_id as student_id, SUM(dtrs.hours_logged) as hours')
            ->pluck('hours', 'student_id');

        foreach ($totals as $studentId => $hours) {
            DB::table('students')->where('id', $studentId)->update(['hours_rendered' => round((float) $hours, 2)]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the days and hours stay as they are.
    }
};
