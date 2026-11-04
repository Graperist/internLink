<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluation editing deadlines set by the Subject Instructor, like an
 * assignment's due date: one for a partner agency (the forms the HTE fills:
 * 13, 21, 23b) per program, and one for a student (the forms the intern fills:
 * 23c, 20). Without one, the program's "days after the internship ends" applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_deadlines')) {
            Schema::create('evaluation_deadlines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hte_id')->constrained('htes')->cascadeOnDelete();
                $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
                $table->dateTime('due_at');
                $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['hte_id', 'program_id']);
            });
        }
        if (! Schema::hasColumn('internships', 'evaluation_deadline_at')) {
            Schema::table('internships', function (Blueprint $table) {
                $table->dateTime('evaluation_deadline_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_deadlines');
        if (Schema::hasColumn('internships', 'evaluation_deadline_at')) {
            Schema::table('internships', function (Blueprint $table) {
                $table->dropColumn('evaluation_deadline_at');
            });
        }
    }
};
