<?php

use App\Models\DocumentRequirement;
use App\Models\DocumentFieldDefinition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DocumentRequirement::where('code', 'PARENT_CONSENT')
            ->get()
            ->each(function (DocumentRequirement $requirement): void {
                // The current system previously allowed a mismatched set of
                // field definitions to be attached to this requirement. Reset
                // only this requirement's definitions so existing document
                // records and field values remain untouched.
                DocumentFieldDefinition::where('document_requirement_id', $requirement->id)->delete();

                $fields = [
                    ['field_key' => 'full_name', 'label' => 'Student Name', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 1],
                    ['field_key' => 'address', 'label' => 'Student Address', 'field_type' => 'textarea', 'group_label' => 'Student Information', 'sort_order' => 2],
                    ['field_key' => 'course', 'label' => 'Course / Program', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 3],
                    ['field_key' => 'required_hours', 'label' => 'Required Internship Hours', 'field_type' => 'text', 'validation_rule' => 'numeric', 'group_label' => 'Student Information', 'sort_order' => 4],
                    ['field_key' => 'company_name', 'label' => 'List of Partner Agencies', 'field_type' => 'text', 'group_label' => 'Partner Agency', 'sort_order' => 5],
                ];

                foreach ($fields as $field) {
                    DocumentFieldDefinition::create(array_merge(
                        [
                            'document_requirement_id' => $requirement->id,
                            'is_required' => true,
                            'validation_rule' => null,
                        ],
                        $field
                    ));
                }
            });
    }

    public function down(): void
    {
        DocumentRequirement::where('code', 'PARENT_CONSENT')
            ->get()
            ->each(function (DocumentRequirement $requirement): void {
                DocumentFieldDefinition::where('document_requirement_id', $requirement->id)->delete();
            });
    }
};
