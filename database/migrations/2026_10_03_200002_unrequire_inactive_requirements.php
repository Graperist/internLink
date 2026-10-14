<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Inactive requirements (legacy forms, seminars) must never count
     * toward "required documents" totals.
     */
    public function up(): void
    {
        DB::table('document_requirements')
            ->where('is_active', false)
            ->update(['is_required' => false]);

        // The legacy forms and seminars should also be inactive everywhere.
        DB::table('document_requirements')
            ->whereIn('code', [
                'DAILY_JOURNAL',
                'INTERNSHIP_ACADEMIC_PRACTICUM_COURSE',
                'CERTIFICATE_OF_COMPLETION',
                'PRE_OJT_SEMINAR',
                'POST_OJT_SEMINAR',
            ])
            ->update(['is_active' => false, 'is_required' => false]);
    }

    public function down(): void
    {
        // Intentionally left as a no-op: restoring is_required on inactive
        // requirements would re-inflate the document totals.
    }
};
