<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('packet_number')->nullable()->after('document_requirement_id');
            $table->unique(
                ['internship_id', 'document_requirement_id', 'packet_number'],
                'documents_internship_requirement_packet_unique'
            );
        });

        // Preserve existing Form 19 data as the first four-week packet.
        // Existing daily entries are not deleted or rewritten by this migration.
        $weeklyReportRequirementIds = DB::table('document_requirements')
            ->where('code', 'WEEKLY_REPORT')
            ->select('id');

        DB::table('documents')
            ->whereIn('document_requirement_id', $weeklyReportRequirementIds)
            ->update(['packet_number' => 1]);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('documents_internship_requirement_packet_unique');
            $table->dropColumn('packet_number');
        });
    }
};
