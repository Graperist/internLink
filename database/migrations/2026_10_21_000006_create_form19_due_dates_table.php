<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A due date and time the instructor picked for one week of an intern's
 * Form 19 (like an MS Teams assignment). Weeks without one use the
 * program's default (N days after the week ends, 11:59 PM).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('form19_due_dates')) {
            return;
        }
        Schema::create('form19_due_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number');
            $table->dateTime('due_at');
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['internship_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form19_due_dates');
    }
};
