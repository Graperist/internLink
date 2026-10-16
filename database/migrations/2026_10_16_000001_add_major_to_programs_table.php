<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinators can now add courses and majors per campus (Programs page).
 * A program row is one course + optional major, e.g.
 *   code BSBA-MM, name "Bachelor of Science in Business Administration",
 *   major "Marketing Management".
 * Existing rows (including the seeded BSBA-OM / BSBA-FM) are left as they
 * are; Program::courseTitle() / majorLabel() keep printing them exactly as
 * before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('major')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('major');
        });
    }
};
