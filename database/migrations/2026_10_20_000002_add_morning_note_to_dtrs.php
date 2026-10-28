<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The morning gets its own note for the supervisor (`notes` stays the afternoon's). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dtrs', 'am_notes')) {
            Schema::table('dtrs', function (Blueprint $table) {
                $table->string('am_notes', 500)->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dtrs', 'am_notes')) {
            Schema::table('dtrs', function (Blueprint $table) {
                $table->dropColumn('am_notes');
            });
        }
    }
};
