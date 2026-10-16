<?php

namespace App\Support;

/**
 * Choices for Form 1's Academic Credentials/Qualifications table.
 */
class Form1Academics
{
    public const ROWS = 5;

    public const SEMESTERS = ['1st Semester', '2nd Semester', 'Summer'];

    /**
     * School years a student can pick, newest first, keyed by the stored
     * value: ['2026-2027' => 2026, '2025-2026' => 2025, ...].
     */
    public static function schoolYearOptions(?int $now = null): array
    {
        $current = $now ?? (int) now()->year;
        $options = [];
        for ($year = $current; $year >= $current - 7; $year--) {
            $options[$year.'-'.($year + 1)] = $year;
        }

        return $options;
    }

    /** Validation rules for the table, merged into the generic form rules. */
    public static function rules(): array
    {
        $rules = ['passed_units' => ['nullable', 'integer', 'min:0', 'max:999']];
        for ($i = 1; $i <= self::ROWS; $i++) {
            $rules["academic_{$i}_sem"] = ['nullable', 'in:'.implode(',', self::SEMESTERS), "required_with:academic_{$i}_subject"];
            $rules["academic_{$i}_sy"] = [
                'nullable', 'regex:/^(\d{4})-(\d{4})$/', "required_with:academic_{$i}_subject",
                function ($attribute, $value, $fail) {
                    [$start, $end] = array_map('intval', explode('-', (string) $value) + [1 => 0]);
                    if ($end !== $start + 1) {
                        $fail('The school year must be two consecutive years, e.g. 2026-2027.');
                    }
                },
            ];
            $rules["academic_{$i}_subject"] = ['nullable', 'string', 'max:255'];
            foreach (['passing', 'failing', 'grade'] as $col) {
                $rules["academic_{$i}_{$col}"] = ['nullable', 'string', 'max:20'];
            }
        }

        return $rules;
    }

    public static function messages(): array
    {
        $messages = [];
        for ($i = 1; $i <= self::ROWS; $i++) {
            $messages["academic_{$i}_sem.required_with"] = "Row {$i}: choose the semester for this subject.";
            $messages["academic_{$i}_sy.required_with"] = "Row {$i}: choose the school year for this subject.";
            $messages["academic_{$i}_sem.in"] = "Row {$i}: choose 1st Semester, 2nd Semester or Summer.";
            $messages["academic_{$i}_sy.regex"] = "Row {$i}: the school year must look like 2026-2027.";
        }

        return $messages;
    }
}
