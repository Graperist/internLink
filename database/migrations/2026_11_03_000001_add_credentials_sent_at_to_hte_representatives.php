<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HTE accounts are made without emailing anything; the sign-in details are
 * sent later (mail icon) once the agency's interns finished. This records
 * when they were last sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hte_representatives', function (Blueprint $table) {
            $table->timestamp('credentials_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hte_representatives', function (Blueprint $table) {
            $table->dropColumn('credentials_sent_at');
        });
    }
};
