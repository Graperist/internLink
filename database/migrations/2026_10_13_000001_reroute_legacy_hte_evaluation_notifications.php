<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Before the evaluation forms were routed by recipient, every HTE submission
 * (Forms 13, 21 and 23b) sent the instructor a "...available in Document
 * Review" notice with no link of its own, so it opened Document Review - where
 * HTE forms never appear.
 *
 *   Form 13  -> belongs in the instructor's Evaluations sidebar: keep the notice,
 *               point it there, and let it clear once opened.
 *   Form 21  -> goes to the student (My HTE Info)         \  not the instructor's: these
 *   Form 23b -> goes to the Coordinator (Documents)       /  old notices are removed.
 *
 * Only instructors' notices are touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $instructorIds = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.code', 'instructor')
            ->select('users.id');

        $legacy = fn () => DB::table('notifications')
            ->whereIn('user_id', $instructorIds)
            ->where('type', 'document_status')
            ->where('message', 'The form is available in Document Review.');

        // Form 13 -> Evaluations sidebar.
        $legacy()->where('title', 'like', 'HTE submitted Form 13%')->update([
            'url' => '/instructor/evaluations',
            'message' => 'The Internship Evaluation Form is available in Evaluations.',
            'resolve_key' => null,   // an FYI: clears when opened, instead of lingering forever
        ]);

        // Forms 21 and 23b are not the instructor's to receive.
        $legacy()->where(function ($q) {
            $q->where('title', 'like', 'HTE submitted Form 21%')
                ->orWhere('title', 'like', 'HTE submitted Form 23b%');
        })->delete();
    }

    public function down(): void
    {
        // Data clean-up only; nothing to restore.
    }
};
