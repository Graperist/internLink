<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The HTE representative's own remarks when verifying or flagging an
     * entry - kept separate from the existing "notes" column, which is
     * the student's own "task done today" text. Reusing that column
     * would let the HTE representative's flag reason silently overwrite
     * what the student wrote, or vice versa.
     */
    public function up(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            $table->text('verification_remarks')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            $table->dropColumn('verification_remarks');
        });
    }
};
