<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Super Admin can delete an instructor account. The document reviews
 * they made are part of the students' records, so they stay - they just
 * lose the link to the removed instructor (like evaluations do when an HTE
 * account is removed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_reviews', function (Blueprint $table) {
            $table->foreignId('instructor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // left nullable: reviews of removed instructors have no instructor
    }
};
