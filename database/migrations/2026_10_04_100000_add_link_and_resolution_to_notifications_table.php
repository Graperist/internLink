<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clickable + self-clearing notifications.
     *  - url:         where clicking the notification goes (falls back to a sensible page per role/type)
     *  - resolve_key: groups notifications about the same open issue (e.g. "document:15", "dtr:42");
     *                 when that issue is resolved, every notification with the key is removed
     *  - expires_at:  info notices that lapse on their own (e.g. a seminar announcement after the event)
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('url', 500)->nullable()->after('message');
            $table->string('resolve_key', 120)->nullable()->after('url')->index();
            $table->timestamp('expires_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['resolve_key']);
            $table->dropColumn(['url', 'resolve_key', 'expires_at']);
        });
    }
};
