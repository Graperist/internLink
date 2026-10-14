<?php

use App\Models\DocumentRequirement;
use App\Models\DocumentFieldDefinition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $fields = [
            ['field_key' => 'endorsement_date', 'label' => 'Date', 'field_type' => 'text', 'group_label' => 'Endorsement Details', 'sort_order' => 1],
            ['field_key' => 'company_name', 'label' => 'Company / Host Training Establishment', 'field_type' => 'text', 'group_label' => 'Endorsement Details', 'sort_order' => 2],
            ['field_key' => 'course', 'label' => 'Course', 'field_type' => 'text', 'group_label' => 'Endorsement Details', 'sort_order' => 3],
            ['field_key' => 'major', 'label' => 'Major', 'field_type' => 'text', 'group_label' => 'Endorsement Details', 'sort_order' => 4, 'is_required' => false],
        ];

        DocumentRequirement::where('code', 'ENDORSEMENT_LETTER')->each(function ($requirement) use ($fields) {
            foreach ($fields as $field) {
                DocumentFieldDefinition::firstOrCreate(
                    [
                        'document_requirement_id' => $requirement->id,
                        'field_key' => $field['field_key'],
                    ],
                    [
                        'label' => $field['label'],
                        'group_label' => $field['group_label'],
                        'field_type' => $field['field_type'],
                        'is_required' => $field['is_required'] ?? true,
                        'sort_order' => $field['sort_order'],
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        DocumentRequirement::where('code', 'ENDORSEMENT_LETTER')
            ->with('fieldDefinitions')
            ->get()
            ->each(function ($requirement) {
                $requirement->fieldDefinitions()
                    ->whereIn('field_key', [
                        'endorsement_date',
                        'company_name',
                        'course',
                        'major',
                    ])
                    ->delete();
            });
    }
};
