<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Practicum Internship Weekly Report belongs under Post-Deployment,
     * not Active - updates the existing requirement row directly, since
     * firstOrCreate in the seeder only sets attributes when a row is
     * first created, never on an existing match.
     */
    public function up(): void
    {
        DB::table('document_requirements')
            ->where('code', 'WEEKLY_REPORT')
            ->update(['stage' => 'post_deployment']);
    }

    public function down(): void
    {
        DB::table('document_requirements')
            ->where('code', 'WEEKLY_REPORT')
            ->update(['stage' => 'active']);
    }
};
