<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requirements', function (Blueprint $table) {
            // Repeating forms (Daily Journal, Internship Time Frame, Weekly
            // Report) are filled as an ongoing log of entries over the
            // internship, rather than once in a single sitting - this flag
            // is what tells the student/instructor controllers and views
            // which UI to use. False/default for every existing requirement.
            $table->boolean('is_repeating')->default(false)->after('is_required');

            // Weekly-cadence entries (Weekly Report) cover a date range,
            // not a single day - nullable, only used when relevant.
            $table->string('entry_cadence')->nullable()->after('is_repeating'); // daily | weekly
        });

        Schema::table('document_field_definitions', function (Blueprint $table) {
            // Purely for grouping related rating-scale criteria under a
            // shared heading on the fill form (e.g., "Communication",
            // "Ethical Dealings") - display only, no behavior change.
            $table->string('group_label')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->dropColumn(['is_repeating', 'entry_cadence']);
        });

        Schema::table('document_field_definitions', function (Blueprint $table) {
            $table->dropColumn('group_label');
        });
    }
};
