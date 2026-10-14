<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per student per week of the deployment (Form 4 - Weekly
     * Journal). Rows exist only once the student submits; the list of
     * weeks and their due dates is calculated, not stored.
     */
    public function up(): void
    {
        Schema::create('weekly_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
            $table->unsignedInteger('week_number');
            $table->date('week_start');
            $table->date('week_end');
            $table->date('due_date');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('status')->default('submitted'); // submitted | approved | returned
            $table->boolean('is_late')->default(false);
            $table->timestamp('submitted_at');
            $table->text('instructor_feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['internship_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_journals');
    }
};
