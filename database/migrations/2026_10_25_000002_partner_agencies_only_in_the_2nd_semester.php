<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The internship is only in the 2nd Semester. Partner agency listings saved
 * under 1st Semester or Midyear move to that academic year's 2nd Semester;
 * if the agency is already listed there for the program, the two are merged
 * (the larger number of slots is kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        $others = DB::table('hte_listings')->where('semester', '!=', '2nd Semester')->orderBy('id')->get();

        foreach ($others as $row) {
            $kept = DB::table('hte_listings')
                ->where('program_id', $row->program_id)->where('hte_id', $row->hte_id)
                ->where('academic_year', $row->academic_year)->where('semester', '2nd Semester')
                ->first();

            if ($kept) {
                if (($row->slots ?? 0) > ($kept->slots ?? 0)) {
                    DB::table('hte_listings')->where('id', $kept->id)->update(['slots' => $row->slots, 'updated_at' => now()]);
                }
                DB::table('hte_listings')->where('id', $row->id)->delete();
            } else {
                DB::table('hte_listings')->where('id', $row->id)->update(['semester' => '2nd Semester', 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // The original semesters are not kept.
    }
};
