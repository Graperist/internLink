<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Partner agencies are archived (and can be restored) instead of deleted. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hte_listings', 'archived_at')) {
            Schema::table('hte_listings', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hte_listings', 'archived_at')) {
            Schema::table('hte_listings', function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
