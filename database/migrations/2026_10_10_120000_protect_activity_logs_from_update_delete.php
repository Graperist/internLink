<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Production uses MariaDB/MySQL. Keep SQLite-based test environments
        // portable; the ActivityLog model still blocks normal Eloquent edits.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared("CREATE TRIGGER activity_logs_block_update BEFORE UPDATE ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Activity logs are append-only'");
        DB::unprepared("CREATE TRIGGER activity_logs_block_delete BEFORE DELETE ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Activity logs are append-only'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS activity_logs_block_update');
        DB::unprepared('DROP TRIGGER IF EXISTS activity_logs_block_delete');
    }
};
