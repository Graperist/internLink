<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Form 19 used to be reviewed in four-week packets; now each week is
     * reviewed as it is finished, so each week is its own document and
     * packet_number is the week number (Week 1 = the first DTR day or an
     * earlier entry, 7 days each).
     *
     *  - A packet still with the student (draft / returned) is split into one
     *    document per week.
     *  - A packet already with the instructor or signed is kept whole (its
     *    review, printout and signed copy stay valid) and numbered by its
     *    first week.
     */
    public function up(): void
    {
        $requirementIds = DB::table('document_requirements')->where('code', 'WEEKLY_REPORT')->pluck('id');
        if ($requirementIds->isEmpty()) {
            return;
        }

        $groups = DB::table('documents')->whereIn('document_requirement_id', $requirementIds)
            ->select('internship_id', 'document_requirement_id')->distinct()->get();

        foreach ($groups as $group) {
            DB::transaction(function () use ($group) {
                $docs = DB::table('documents')->where('internship_id', $group->internship_id)
                    ->where('document_requirement_id', $group->document_requirement_id)->orderBy('packet_number')->orderBy('id')->get();
                $entries = DB::table('document_entries')->whereIn('document_id', $docs->pluck('id'))->get()->groupBy('document_id');

                $dates = $entries->flatten(1)->pluck('entry_date');
                $firstDtr = DB::table('dtrs')->join('deployments', 'deployments.id', '=', 'dtrs.deployment_id')
                    ->where('deployments.internship_id', $group->internship_id)->min('dtrs.log_date');
                if ($firstDtr) {
                    $dates->push($firstDtr);
                }
                if ($dates->isEmpty()) {
                    return;
                }
                $anchor = Carbon::parse($dates->min())->startOfDay();
                $week = fn ($date) => (int) floor($anchor->diffInDays(Carbon::parse($date)->startOfDay(), false) / 7) + 1;

                // Out of the way of the unique (internship, requirement, packet_number) index first.
                foreach ($docs as $doc) {
                    DB::table('documents')->where('id', $doc->id)->update(['packet_number' => 100000 + $doc->id]);
                }

                // Gives a document its week number. If that week already has a
                // document (week boundaries can shift), an editable one takes
                // the entries; otherwise the next free number is used.
                $taken = function (int $number) use ($group) {
                    return DB::table('documents')->where('internship_id', $group->internship_id)
                        ->where('document_requirement_id', $group->document_requirement_id)
                        ->where('packet_number', $number)->first();
                };
                $place = function ($doc, int $number, $entryIds) use ($taken) {
                    $editable = in_array($doc->status, ['draft', 'returned'], true);
                    while ($existing = $taken($number)) {
                        if ($editable && in_array($existing->status, ['draft', 'returned'], true)) {
                            DB::table('document_entries')->whereIn('id', $entryIds)->update(['document_id' => $existing->id]);

                            return null;   // merged
                        }
                        $number++;
                    }

                    return $number;
                };

                foreach ($docs as $doc) {
                    $docEntries = $entries->get($doc->id, collect());
                    $weeks = $docEntries->groupBy(fn ($e) => max(1, $week($e->entry_date)))->sortKeys();
                    $editable = in_array($doc->status, ['draft', 'returned'], true);

                    if ($weeks->isEmpty()) {
                        if ($editable) {
                            DB::table('documents')->where('id', $doc->id)->delete();   // an empty draft week
                        } elseif ($number = $place($doc, max(1, ((int) $doc->packet_number - 1) * 4 + 1), [])) {
                            DB::table('documents')->where('id', $doc->id)->update(['packet_number' => $number]);
                        }
                        continue;
                    }

                    if (! $editable || $weeks->count() === 1) {
                        $number = $place($doc, $weeks->keys()->first(), $docEntries->pluck('id'));
                        $number === null
                            ? DB::table('documents')->where('id', $doc->id)->delete()
                            : DB::table('documents')->where('id', $doc->id)->update(['packet_number' => $number]);
                        continue;
                    }

                    // Split a draft / returned packet: one document per week.
                    $keptOriginal = false;
                    foreach ($weeks as $weekNumber => $weekEntries) {
                        $number = $place($doc, $weekNumber, $weekEntries->pluck('id'));
                        if ($number === null) {
                            continue;   // merged into that week's document
                        }
                        if (! $keptOriginal) {
                            DB::table('documents')->where('id', $doc->id)->update(['packet_number' => $number]);
                            DB::table('document_entries')->whereIn('id', $weekEntries->pluck('id'))->update(['document_id' => $doc->id]);
                            $keptOriginal = true;
                            continue;
                        }
                        $copy = (array) $doc;
                        unset($copy['id']);
                        $copy['packet_number'] = $number;
                        $newId = DB::table('documents')->insertGetId($copy);
                        DB::table('document_entries')->whereIn('id', $weekEntries->pluck('id'))->update(['document_id' => $newId]);
                    }
                    if (! $keptOriginal && ! DB::table('document_entries')->where('document_id', $doc->id)->exists()) {
                        DB::table('documents')->where('id', $doc->id)->delete();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Not reversed: the per-week documents stay valid documents.
    }
};
