<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Week 1 of Form 19 gets a fixed start date once a week has been sent to
     * the instructor, so week numbers never shift afterwards (an earlier DTR
     * day or entry used to renumber weeks that were already reviewed).
     *
     * For students who already sent weeks, the start date is the one that
     * matches the week numbers those documents already carry.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('internships', 'week_one_date')) {
            Schema::table('internships', function (Blueprint $table) {
                $table->date('week_one_date')->nullable()->after('required_hours');
            });
        }

        $requirementIds = DB::table('document_requirements')->where('code', 'WEEKLY_REPORT')->pluck('id');
        if ($requirementIds->isEmpty()) {
            return;
        }

        $locked = DB::table('documents')->whereIn('document_requirement_id', $requirementIds)
            ->whereNotIn('status', ['draft'])->whereNotNull('packet_number')
            ->get()->groupBy('internship_id');

        foreach ($locked as $internshipId => $docs) {
            $firsts = []; // packet number => its earliest entry date
            foreach ($docs as $doc) {
                $min = DB::table('document_entries')->where('document_id', $doc->id)->min('entry_date');
                if ($min) {
                    $firsts[(int) $doc->packet_number] = Carbon::parse($min)->startOfDay();
                }
            }
            if (! $firsts) {
                continue;
            }

            $candidates = [];
            $firstDtr = DB::table('dtrs')->join('deployments', 'deployments.id', '=', 'dtrs.deployment_id')
                ->where('deployments.internship_id', $internshipId)->min('dtrs.log_date');
            if ($firstDtr) {
                $candidates[] = Carbon::parse($firstDtr)->startOfDay();
            }
            $allEntries = DB::table('document_entries')->join('documents', 'documents.id', '=', 'document_entries.document_id')
                ->where('documents.internship_id', $internshipId)->whereIn('documents.document_requirement_id', $requirementIds)
                ->min('document_entries.entry_date');
            if ($allEntries) {
                $candidates[] = Carbon::parse($allEntries)->startOfDay();
            }
            foreach ($firsts as $packet => $date) {
                $candidates[] = $date->copy()->subDays(7 * ($packet - 1));
            }

            $number = fn (Carbon $anchor, Carbon $date) => (int) floor($anchor->diffInDays($date, false) / 7) + 1;
            $pin = collect($candidates)->first(function (Carbon $anchor) use ($firsts, $number) {
                foreach ($firsts as $packet => $date) {
                    if ($number($anchor, $date) !== $packet) {
                        return false;
                    }
                }

                return true;
            }) ?? end($candidates);

            DB::table('internships')->where('id', $internshipId)->update(['week_one_date' => $pin->toDateString()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('internships', 'week_one_date')) {
            Schema::table('internships', function (Blueprint $table) {
                $table->dropColumn('week_one_date');
            });
        }
    }
};
