<?php

use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DocumentRequirement::where('code', 'ENDORSEMENT_LETTER')->each(function ($requirement) {
            $fields = [
                [
                    'field_key' => 'endorsement_date',
                    'label' => 'Date',
                    'field_type' => 'date',
                    'group_label' => 'Letter Details',
                    'sort_order' => 1,
                ],
                [
                    'field_key' => 'company_name',
                    'label' => 'Host Training Establishment / Company',
                    'field_type' => 'text',
                    'group_label' => 'Letter Details',
                    'sort_order' => 2,
                ],
                [
                    'field_key' => 'recipient_name',
                    'label' => 'Recipient Name',
                    'field_type' => 'text',
                    'group_label' => 'Recipient',
                    'sort_order' => 10,
                ],
                [
                    'field_key' => 'recipient_position',
                    'label' => 'Recipient Position / Designation',
                    'field_type' => 'text',
                    'group_label' => 'Recipient',
                    'sort_order' => 11,
                ],
                [
                    'field_key' => 'recipient_office',
                    'label' => 'Office / Department',
                    'field_type' => 'text',
                    'group_label' => 'Recipient',
                    'sort_order' => 12,
                ],
                [
                    'field_key' => 'recipient_address',
                    'label' => 'Office / Company Address',
                    'field_type' => 'textarea',
                    'group_label' => 'Recipient',
                    'sort_order' => 13,
                ],
                [
                    'field_key' => 'course',
                    'label' => 'Course',
                    'field_type' => 'text',
                    'group_label' => 'Student Information',
                    'sort_order' => 20,
                ],
                [
                    'field_key' => 'major',
                    'label' => 'Major',
                    'field_type' => 'text',
                    'group_label' => 'Student Information',
                    'sort_order' => 21,
                    'is_required' => false,
                ],
            ];

            foreach ($fields as $field) {
                DocumentFieldDefinition::updateOrCreate(
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

            // The original migration created these four fields. Keep them in
            // the same logical order after the recipient fields were added.
            $orders = [
                'endorsement_date' => 1,
                'company_name' => 2,
                'recipient_name' => 10,
                'recipient_position' => 11,
                'recipient_office' => 12,
                'recipient_address' => 13,
                'course' => 20,
                'major' => 21,
            ];

            foreach ($orders as $key => $order) {
                DocumentFieldDefinition::where('document_requirement_id', $requirement->id)
                    ->where('field_key', $key)
                    ->update(['sort_order' => $order]);
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
                        'recipient_name',
                        'recipient_position',
                        'recipient_office',
                        'recipient_address',
                    ])
                    ->delete();
            });
    }
};
