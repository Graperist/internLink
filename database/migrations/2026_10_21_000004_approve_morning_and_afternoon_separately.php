<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The HTE supervisor approves (or declines) the morning and the afternoon of
 * a day separately. Each half keeps its own status and decline reason;
 * approved_hours is the hours of the approved halves, which is what counts.
 * `status` stays as the day's overall state: declined if a half is declined,
 * approved when every half entered is approved, otherwise waiting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            foreach (['am', 'pm'] as $p) {
                if (! Schema::hasColumn('dtrs', $p.'_status')) {
                    $table->string($p.'_status', 20)->nullable();
                }
                if (! Schema::hasColumn('dtrs', $p.'_decline_reason')) {
                    $table->string($p.'_decline_reason')->nullable();
                }
                if (! Schema::hasColumn('dtrs', $p.'_decline_note')) {
                    $table->text($p.'_decline_note')->nullable();
                }
            }
            if (! Schema::hasColumn('dtrs', 'approved_hours')) {
                $table->decimal('approved_hours', 6, 2)->default(0);
            }
        });

        // Existing days: each half that has times takes the day's status.
        DB::table('dtrs')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $hasAm = filled($row->am_in) || filled($row->am_out);
                $hasPm = filled($row->pm_in) || filled($row->pm_out);
                if (! $hasAm && ! $hasPm && filled($row->time_in)) {
                    // An old single time in/out: the morning or afternoon by its time in.
                    $hasAm = (int) substr((string) $row->time_in, 0, 2) < 12;
                    $hasPm = ! $hasAm;
                }
                $update = ['approved_hours' => $row->status === 'approved' ? (float) $row->hours_logged : 0];
                foreach (['am' => $hasAm, 'pm' => $hasPm] as $p => $has) {
                    $update[$p.'_status'] = $has ? $row->status : null;
                    if ($has && $row->status === 'flagged') {
                        $update[$p.'_decline_reason'] = $row->flag_reason;
                        $update[$p.'_decline_note'] = $row->flag_note;
                    }
                }
                DB::table('dtrs')->where('id', $row->id)->update($update);
            }
        });
    }

    public function down(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            foreach (['am_status', 'pm_status', 'am_decline_reason', 'pm_decline_reason', 'am_decline_note', 'pm_decline_note', 'approved_hours'] as $column) {
                if (Schema::hasColumn('dtrs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
