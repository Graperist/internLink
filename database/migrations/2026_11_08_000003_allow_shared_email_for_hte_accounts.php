<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An HTE account may use an email another account already uses (the
 * agency's contact email is often a staff member's own address). Sign-in
 * and password reset go by ID number, so emails don't have to be unique;
 * students, instructors and coordinators are still kept unique by the
 * forms that create them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->unique('email');
        });
    }
};
