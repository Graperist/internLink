<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing activity_logs table so it can serve as a full audit
 * trail: who did it (user + role + campus), what they did (action +
 * description), and where it came from (IP, method, path).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('campus_id')->nullable()->after('user_id');
            $table->string('role_code', 30)->nullable()->after('campus_id');
            $table->string('route_name')->nullable()->after('subject_id');
            $table->string('method', 10)->nullable()->after('route_name');
            $table->string('path', 500)->nullable()->after('method');
            $table->string('ip_address', 45)->nullable()->after('path');
            $table->string('user_agent', 255)->nullable()->after('ip_address');

            // The feed always reads "newest first, scoped to a campus".
            $table->index('campus_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['campus_id']);
            $table->dropIndex(['created_at']);
            $table->dropColumn([
                'campus_id', 'role_code', 'route_name', 'method', 'path', 'ip_address', 'user_agent',
            ]);
        });
    }
};
