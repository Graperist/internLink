<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interns can be enrolled in the 1st or the 2nd Semester. Students added
 * before this were all in the 2nd Semester (the only term the system had).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('semester', 20)->nullable()->index();
        });

        DB::table('students')->whereNull('semester')->update(['semester' => '2nd Semester']);
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['semester']);
            $table->dropColumn('semester');
        });
    }
};
