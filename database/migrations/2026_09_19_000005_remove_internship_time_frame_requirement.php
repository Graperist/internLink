<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Internship Time Frame" duplicated the Daily Journal requirement -
     * both ended up asking for the same daily "Number of Hours," so this
     * removes it entirely rather than leaving two places that capture
     * the same thing. documents.document_requirement_id is
     * restrictOnDelete (not cascade), so any documents already logged
     * against this requirement have to be removed explicitly first -
     * everything below that (reviews, field values, entries, entry
     * values) cascades automatically once the document itself is gone.
     * document_field_definitions cascades directly from the requirement
     * row, so no separate step is needed for those.
     */
    public function up(): void
    {
        $requirementId = DB::table('document_requirements')
            ->where('code', 'INTERNSHIP_TIME_FRAME')
            ->value('id');

        if (! $requirementId) {
            return; // never existed on this database - nothing to clean up
        }

        DB::table('documents')->where('document_requirement_id', $requirementId)->delete();
        DB::table('document_requirements')->where('id', $requirementId)->delete();
    }

    public function down(): void
    {
        // Deliberately irreversible - the whole point of this migration is
        // that this requirement and the data logged against it shouldn't
        // exist. Re-seed manually if this ever needs to come back.
    }
};
