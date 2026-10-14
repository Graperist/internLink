<?php

use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $fields = [
            [
                'field_key' => 'recipient_salutation',
                'label' => 'Recipient Salutation',
                'field_type' => 'text',
                'group_label' => 'Recipient Information',
                'sort_order' => 5,
            ],
            [
                'field_key' => 'student_title',
                'label' => 'Student Title',
                'field_type' => 'text',
                'group_label' => 'Student Information',
                'sort_order' => 1,
            ],
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
                        'is_required' => true,
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
                    ->whereIn('field_key', ['recipient_salutation', 'student_title'])
                    ->delete();
            });
    }
};
