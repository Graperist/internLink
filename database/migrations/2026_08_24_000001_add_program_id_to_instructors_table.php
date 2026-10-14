<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            // Subject Instructors handle a program, not individually-assigned
            // students - the Instructor Portal mockup labels the account
            // "Subject Instructor - BSIT" and scopes "My Students" to every
            // student in that program. Nullable because existing instructor
            // rows predate this column; a coordinator (or the seeder, for the
            // test account) needs to backfill it per instructor.
            //
            // Note: students.instructor_id still exists and is still set
            // during student provisioning, but it's no longer what determines
            // an instructor's advisee list - that's program_id + campus_id
            // now. Left in place rather than dropped since removing it would
            // also mean reworking the Coordinator's Add Intern form, which
            // wasn't part of this change.
            $table->foreignId('program_id')->nullable()->after('campus_id')->constrained('programs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
    }
};
