<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Form 4 Weekly Journal is retired. Its weekly activity/logging role
     * is now handled by Form 19 – Practicum/Internship Weekly Report.
     *
     * Form 19 is available during the internship and stores one daily row
     * per training day; the printable output groups those rows into weekly
     * Form 19 pages matching the official paper layout.
     */
    public function up(): void
    {
        DB::table('document_requirements')
            ->where('code', 'WEEKLY_JOURNAL')
            ->update([
                'is_active' => false,
                'is_required' => false,
            ]);

        DB::table('document_requirements')
            ->where('code', 'WEEKLY_REPORT')
            ->update([
                'name' => 'Form 19 – Practicum/Internship Weekly Report',
                'stage' => 'active',
                'is_active' => true,
                'is_required' => true,
                'is_repeating' => true,
                'entry_cadence' => 'daily',
                'workflow_mode' => 'print_sign',
                'form_number' => '19',
                'display_order' => 230,
            ]);
    }

    public function down(): void
    {
        DB::table('document_requirements')
            ->where('code', 'WEEKLY_JOURNAL')
            ->update([
                'is_active' => true,
                'is_required' => true,
            ]);

        DB::table('document_requirements')
            ->where('code', 'WEEKLY_REPORT')
            ->update([
                'stage' => 'post_deployment',
                'entry_cadence' => 'weekly',
                'display_order' => 260,
            ]);
    }
};
