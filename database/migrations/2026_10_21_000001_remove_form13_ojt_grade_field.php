<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The HTE no longer enters an OJT Grade on Form 13 and the system no
     * longer computes one: the Subject Instructor computes the final grade
     * outside the system (it also includes seminar attendance etc.).
     * Grades already entered stay in document_field_values untouched.
     */
    public function up(): void
    {
        $requirementIds = DB::table('document_requirements')->where('code', 'EVALUATION_FORM')->pluck('id');

        DB::table('document_field_definitions')
            ->whereIn('document_requirement_id', $requirementIds)
            ->where('field_key', 'ojt_grade')
            ->delete();
    }

    public function down(): void
    {
        $now = now();
        foreach (DB::table('document_requirements')->where('code', 'EVALUATION_FORM')->pluck('id') as $id) {
            DB::table('document_field_definitions')->updateOrInsert(
                ['document_requirement_id' => $id, 'field_key' => 'ojt_grade'],
                ['label' => 'OJT Grade', 'field_type' => 'number', 'group_label' => 'Final Rating', 'validation_rule' => 'numeric|min:0|max:100',
                    'is_required' => true, 'sort_order' => 62, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }
};
