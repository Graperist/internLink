<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Campus Executive Director is an institutional fact about a
     * campus, not something that should be hardcoded into a printable
     * template - a system with more than one PSU campus (the login
     * screen already offers a campus picker) would otherwise print the
     * same CED's name on every campus's documents regardless of who
     * actually holds that role there. Nullable: a campus without this
     * set yet just prints a blank line, same as any other unresolved
     * signatory field.
     */
    public function up(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->string('ced_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->dropColumn('ced_name');
        });
    }
};
