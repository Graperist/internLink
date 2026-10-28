<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Students and HTEs can edit their evaluations until the deadline (a number
 * of days after the internship ends, set per campus) or until the Subject
 * Instructor locks the evaluation - whichever comes first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->unsignedSmallInteger('evaluation_edit_days')->default(7);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by_user_id');
            $table->dropColumn(['locked_at', 'unlocked_at']);
        });
        Schema::table('campuses', function (Blueprint $table) {
            $table->dropColumn('evaluation_edit_days');
        });
    }
};
