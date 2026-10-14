<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Synchronize Form 19's repeating-entry field definitions with the
     * official Practicum/Internship Weekly Report.
     *
     * The student enters one training day at a time, but those rows are
     * grouped into a weekly Form 19 report. Each row has only the two
     * narrative columns printed on the official form.
     */
    public function up(): void
    {
        $requirementId = DB::table('document_requirements')
            ->where('code', 'WEEKLY_REPORT')
            ->value('id');

        if (! $requirementId) {
            return;
        }

        // Remove stale definitions from older versions of the weekly report
        // (job_description, start_date, end_date, total_hours, etc.).
        DB::table('document_field_definitions')
            ->where('document_requirement_id', $requirementId)
            ->delete();

        DB::table('document_field_definitions')->insert([
            [
                'document_requirement_id' => $requirementId,
                'field_key' => 'tasks_accomplished',
                'label' => 'Tasks Accomplished',
                'field_type' => 'textarea',
                'is_required' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_requirement_id' => $requirementId,
                'field_key' => 'knowledge_skills_learned',
                'label' => 'Knowledge, Skills, Values Learned',
                'field_type' => 'textarea',
                'is_required' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        $requirementId = DB::table('document_requirements')
            ->where('code', 'WEEKLY_REPORT')
            ->value('id');

        if (! $requirementId) {
            return;
        }

        DB::table('document_field_definitions')
            ->where('document_requirement_id', $requirementId)
            ->delete();
    }
};
