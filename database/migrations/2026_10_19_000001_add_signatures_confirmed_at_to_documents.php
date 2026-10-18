<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Print-and-sign forms: after the student brings the physically signed
     * form back, the instructor confirms the signatures, which opens the
     * signed-copy upload for the student.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('signatures_confirmed_at')->nullable()->after('printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('signatures_confirmed_at');
        });
    }
};
