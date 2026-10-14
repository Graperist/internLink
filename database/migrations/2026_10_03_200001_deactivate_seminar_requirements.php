<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Pre-/Post-OJT Seminars are not document requirements - they are
     * announced by the Subject Instructor instead (see Announcements).
     * Deactivated rather than deleted so any existing rows stay readable,
     * and not counted as required so progress/document totals are correct.
     */
    public function up(): void
    {
        DB::table('document_requirements')
            ->whereIn('code', ['PRE_OJT_SEMINAR', 'POST_OJT_SEMINAR'])
            ->update(['is_active' => false, 'is_required' => false]);
    }

    public function down(): void
    {
        DB::table('document_requirements')
            ->whereIn('code', ['PRE_OJT_SEMINAR', 'POST_OJT_SEMINAR'])
            ->update(['is_active' => true, 'is_required' => true]);
    }
};
