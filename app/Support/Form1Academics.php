<?php

namespace App\Support;

/**
 * Form 1's Academic Credentials/Qualifications table. It starts with the
 * five rows of the official form; the student can add rows (up to
 * MAX_ROWS) when they have more subjects. Each row is stored as field
 * values academic_{n}_{column}.
 */
class Form1Academics
{
    /** Rows shown (and printed) even when empty, as on the official form. */
    public const ROWS = 5;

    public const MAX_ROWS = 30;

    /** Columns the student fills in. SIGNATURE is signed on the paper copy. */
    public const COLUMNS = ['sem', 'sy', 'subject', 'passing', 'failing', 'grade', 'date'];

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

    /**
     * How many rows to show: at least ROWS, or up to the last row that has
     * anything in it. $value reads a field ('academic_7_subject' => ...).
     */
    public static function rowCount(callable $value): int
    {
        $count = self::ROWS;
        for ($i = self::MAX_ROWS; $i > self::ROWS; $i--) {
            foreach (self::COLUMNS as $column) {
                if (filled($value("academic_{$i}_{$column}"))) {
                    return $i;
                }
            }
        }

        return $count;
    }

    /** Validation rules for the table, merged into the generic form rules. */
    public static function rules(): array
    {
        $rules = ['passed_units' => ['nullable', 'integer', 'min:0', 'max:999']];
        for ($i = 1; $i <= self::MAX_ROWS; $i++) {
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
            $rules["academic_{$i}_date"] = ['nullable', 'date'];
        }

        return $rules;
    }

    public static function messages(): array
    {
        $messages = [];
        for ($i = 1; $i <= self::MAX_ROWS; $i++) {
            $messages["academic_{$i}_sem.required_with"] = "Row {$i}: choose the semester for this subject.";
            $messages["academic_{$i}_sy.required_with"] = "Row {$i}: choose the school year for this subject.";
            $messages["academic_{$i}_sem.in"] = "Row {$i}: choose 1st Sem, 2nd Sem or Summer.";
            $messages["academic_{$i}_sy.regex"] = "Row {$i}: the school year must look like 2026-2027.";
            $messages["academic_{$i}_date.date"] = "Row {$i}: enter a valid date.";
        }

        return $messages;
    }

    /**
     * Drops empty rows and numbers the rest 1, 2, 3... so the printed table
     * has no gaps. Returns the validated data with the academic keys
     * replaced; rows 1-5 are always present (blank when unused).
     */
    public static function compact(array $validated): array
    {
        $rows = [];
        for ($i = 1; $i <= self::MAX_ROWS; $i++) {
            $row = [];
            foreach (self::COLUMNS as $column) {
                $row[$column] = $validated["academic_{$i}_{$column}"] ?? null;
            }
            if (array_filter($row, 'filled')) {
                $rows[] = $row;
            }
        }

        $validated = array_filter($validated, fn ($key) => ! preg_match('/^academic_\d+_/', $key), ARRAY_FILTER_USE_KEY);
        $total = max(self::ROWS, count($rows));
        for ($n = 1; $n <= $total; $n++) {
            foreach (self::COLUMNS as $column) {
                $validated["academic_{$n}_{$column}"] = $rows[$n - 1][$column] ?? null;
            }
        }

        return $validated;
    }
}
