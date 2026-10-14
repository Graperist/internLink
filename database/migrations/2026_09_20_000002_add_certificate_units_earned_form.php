<?php

use App\Models\Campus;
use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $fields = [
            ['field_key' => 'surname', 'label' => 'Last Name', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 1],
            ['field_key' => 'given_name', 'label' => 'First Name', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 2],
            ['field_key' => 'middle_name', 'label' => 'Middle Name', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 3, 'is_required' => false],
            ['field_key' => 'course', 'label' => 'Course', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 4],
            ['field_key' => 'major', 'label' => 'Major', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 5, 'is_required' => false],
            ['field_key' => 'first_year_courses', 'label' => 'First Year Courses Earned', 'field_type' => 'textarea', 'group_label' => 'Courses Earned', 'sort_order' => 10],
            ['field_key' => 'second_year_courses', 'label' => 'Second Year Courses Earned', 'field_type' => 'textarea', 'group_label' => 'Courses Earned', 'sort_order' => 11],
            ['field_key' => 'third_year_courses', 'label' => 'Third Year Courses Earned', 'field_type' => 'textarea', 'group_label' => 'Courses Earned', 'sort_order' => 12],
            ['field_key' => 'fourth_year_courses', 'label' => 'Fourth Year Courses Earned', 'field_type' => 'textarea', 'group_label' => 'Courses Earned', 'sort_order' => 13],
            ['field_key' => 'failed_courses', 'label' => 'Courses Failed to Take as of the Present', 'field_type' => 'textarea', 'group_label' => 'Courses Failed to Take as of the Present', 'sort_order' => 20, 'is_required' => false],
        ];

        Campus::query()->get()->each(function ($campus) use ($fields) {
            $requirement = DocumentRequirement::firstOrCreate(
                ['campus_id' => $campus->id, 'code' => 'CERTIFICATE_OF_UNITS_EARNED'],
                [
                    'name' => 'Certificate of Units Earned for Practicum',
                    'stage' => 'pre_deployment',
                    'is_required' => true,
                    'is_repeating' => false,
                    'entry_cadence' => null,
                ]
            );

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
        DocumentRequirement::where('code', 'CERTIFICATE_OF_UNITS_EARNED')
            ->with('fieldDefinitions')
            ->get()
            ->each(function ($requirement) {
                $requirement->fieldDefinitions()->delete();
                $requirement->delete();
            });
    }
};
