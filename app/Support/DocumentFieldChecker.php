<?php

namespace App\Support;

use App\Models\DocumentFieldValue;
use App\Models\Hte;
use Illuminate\Support\Str;

class DocumentFieldChecker
{
    /**
     * Field keys checked against a real reference list rather than just
     * "is it filled in" - currently just the intended HTE name against
     * the campus's registered partner list.
     */
    private const REFERENCE_CHECKS = [
        'hte_name' => 'hte',
    ];

    /**
     * Field keys checked for suspicious matches across different
     * students' own submissions of the same form - not a hard rule
     * violation, just something worth a second look before approving.
     */
    private const DUPLICATE_CHECKS = [
        'parent_guardian_name',
    ];

    /**
     * Returns a warning message for this field value, or null if nothing
     * looks off. Always advisory - the instructor still makes the actual
     * decision; this never blocks a student from submitting.
     */
    public static function check(DocumentFieldValue $fieldValue, int $campusId): ?string
    {
        if (blank($fieldValue->value)) {
            return null;
        }

        if ($formatWarning = self::checkFormat($fieldValue)) {
            return $formatWarning;
        }

        if (array_key_exists($fieldValue->field_key, self::REFERENCE_CHECKS)) {
            return self::checkAgainstReference($fieldValue, $campusId);
        }

        if (in_array($fieldValue->field_key, self::DUPLICATE_CHECKS, true)) {
            return self::checkForDuplicate($fieldValue, $campusId);
        }

        return null;
    }

    /**
     * Format/correctness check, driven by the field definition's own
     * validation_rule rather than a hardcoded field-key list - this is
     * what lets any field on any form declare what "correct" looks like
     * for it, without touching this class again. A field with no rule
     * (validation_rule is null) is never format-checked, same as before
     * this existed.
     */
    public static function checkFormat(DocumentFieldValue $fieldValue): ?string
    {
        $definition = $fieldValue->document?->requirement?->fieldDefinitions
            ->firstWhere('field_key', $fieldValue->field_key);

        $rule = $definition?->validation_rule;

        if (blank($rule) || blank($fieldValue->value)) {
            return null;
        }

        $value = trim($fieldValue->value);

        if ($rule === 'numeric') {
            return is_numeric($value)
                ? null
                : "\"{$value}\" doesn't look like a number \u2014 double-check this entry.";
        }

        if ($rule === 'age') {
            if (! is_numeric($value)) {
                return "\"{$value}\" doesn't look like a valid age.";
            }
            $age = (int) $value;

            return ($age >= 15 && $age <= 60)
                ? null
                : "{$age} is outside the typical age range for an intern \u2014 please verify this is correct.";
        }

        if ($rule === 'phone_ph') {
            $digitsOnly = preg_replace('/[^0-9]/', '', $value);
            $validLength = in_array(strlen($digitsOnly), [10, 11, 12], true);

            return $validLength
                ? null
                : "\"{$value}\" doesn't look like a valid Philippine phone number.";
        }

        if ($rule === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : "\"{$value}\" doesn't look like a valid email address.";
        }

        if (str_starts_with($rule, 'enum:')) {
            $options = explode(',', substr($rule, 5));
            $matches = collect($options)->contains(fn ($opt) => Str::lower(trim($opt)) === Str::lower($value));

            return $matches
                ? null
                : "\"{$value}\" doesn't match any of the expected values (".implode(', ', $options).').';
        }

        return null;
    }

    /**
     * Reference check only, exposed publicly so the student's own
     * submission flow can show this specific warning without also
     * running (or risking exposing) the cross-student duplicate check,
     * which is instructor-only.
     */
    public static function checkReferenceOnly(DocumentFieldValue $fieldValue, int $campusId): ?string
    {
        if (blank($fieldValue->value) || ! array_key_exists($fieldValue->field_key, self::REFERENCE_CHECKS)) {
            return null;
        }

        return self::checkAgainstReference($fieldValue, $campusId);
    }

    private static function checkAgainstReference(DocumentFieldValue $fieldValue, int $campusId): ?string
    {
        $normalized = Str::lower(trim($fieldValue->value));

        $exists = Hte::where('campus_id', $campusId)
            ->get()
            ->contains(fn ($hte) => Str::lower(trim($hte->name)) === $normalized);

        return $exists
            ? null
            : "This HTE name doesn't match any partner agency currently on file. If this is a new agency found independently, that's expected \u{2014} it will need to be added to the partner list before deployment.";
    }

    private static function checkForDuplicate(DocumentFieldValue $fieldValue, int $campusId): ?string
    {
        $normalized = Str::lower(trim($fieldValue->value));

        $duplicate = DocumentFieldValue::where('field_key', $fieldValue->field_key)
            ->where('id', '!=', $fieldValue->id)
            ->whereHas('document.internship', fn ($q) => $q->where('campus_id', $campusId))
            ->with('document.internship.student.user')
            ->get()
            ->first(fn ($candidate) => Str::lower(trim($candidate->value)) === $normalized);

        if (! $duplicate) {
            return null;
        }

        $otherStudentName = $duplicate->document?->internship?->student?->user?->name ?? 'another student';

        return "Matches {$otherStudentName}'s entry - please verify this isn't a copy-paste error.";
    }
}
