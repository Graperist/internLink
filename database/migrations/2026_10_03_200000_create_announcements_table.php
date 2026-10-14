<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instructor announcements (e.g. Pre-/Post-OJT Seminar schedules).
     * Each announcement fans out as an in-app notification to the
     * instructor's advisees; this table is the instructor's own record of
     * what was sent and to how many students.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // the instructor who posted it
            $table->string('category'); // pre_ojt_seminar | post_ojt_seminar | general
            $table->string('title');
            $table->dateTime('event_at')->nullable();
            $table->string('venue')->nullable();
            $table->text('message')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
