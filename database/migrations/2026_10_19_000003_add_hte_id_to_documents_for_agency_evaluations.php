<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evaluations of a partner agency are one per agency (like the PSU faculty
     * evaluation: one per instructor rated), not one per student:
     *   Form 23a - the Subject Instructor rates each agency their students were at
     *   Form 23b - the agency rates itself once
     * hte_id is the agency the evaluation is about. Existing 23a / 23b records
     * get it from the deployment they were filed under.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('hte_id')->nullable()->after('internship_id')->constrained('htes')->nullOnDelete();
        });

        $requirementIds = DB::table('document_requirements')
            ->whereIn('code', ['PARTNER_AGENCY_EVAL_INSTRUCTOR', 'PARTNER_AGENCY_EVAL_SELF'])
            ->pluck('id');
        DB::table('documents')->whereIn('document_requirement_id', $requirementIds)->whereNull('hte_id')->orderBy('id')
            ->each(function ($document) {
                $hteId = DB::table('deployments')->where('internship_id', $document->internship_id)->value('hte_id');
                if ($hteId) {
                    DB::table('documents')->where('id', $document->id)->update(['hte_id' => $hteId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hte_id');
        });
    }
};
