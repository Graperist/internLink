<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // A document with structured fields now exists (status =
            // submitted/returned/printed/completed) before any physical
            // file is ever uploaded - the one file upload only happens at
            // the very end, once signed copies come back. Previously
            // required, since every document used to begin life as a file.
            $table->string('file_path')->nullable()->change();

            // New workflow checkpoints. Both nullable/absent for plain
            // upload-only requirements, which skip this workflow entirely.
            $table->dateTime('printed_at')->nullable()->after('submitted_at');
            $table->dateTime('completed_at')->nullable()->after('printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['printed_at', 'completed_at']);
            $table->string('file_path')->nullable(false)->change();
        });
    }
};
