<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_requirements')
            ->whereIn('code', ['WEEKLY_JOURNAL', 'DAILY_JOURNAL'])
            ->update([
                'is_active' => false,
                'is_required' => false,
            ]);

        DB::table('document_requirements')
            ->where('code', 'WEEKLY_REPORT')
            ->update([
                'name' => 'Form 19 – Practicum/Internship Weekly Report',
                'form_number' => '19',
                'stage' => 'active',
                'is_active' => true,
                'is_required' => true,
                'is_repeating' => true,
                'entry_cadence' => 'daily',
                'workflow_mode' => 'print_sign',
                'audience' => 'student',
            ]);
    }

    public function down(): void
    {
        DB::table('document_requirements')
            ->where('code', 'WEEKLY_REPORT')
            ->update([
                'stage' => 'post_deployment',
                'entry_cadence' => 'weekly',
            ]);
    }
};
