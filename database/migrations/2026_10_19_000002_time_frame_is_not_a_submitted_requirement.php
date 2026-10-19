<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Form 17 – Internship Time Frame is the daily time entry the HTE
     * supervisor approves - not a form the instructor reviews, approves for
     * printing or checks a signed upload of. It stays listed for the student
     * (and downloadable), but no longer counts as a requirement to submit.
     */
    public function up(): void
    {
        DB::table('document_requirements')->where('code', 'INTERNSHIP_TIME_FRAME')->update(['is_required' => false]);
    }

    public function down(): void
    {
        DB::table('document_requirements')->where('code', 'INTERNSHIP_TIME_FRAME')->update(['is_required' => true]);
    }
};
