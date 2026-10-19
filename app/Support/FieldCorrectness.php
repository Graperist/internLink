<?php

namespace App\Support;

use App\Models\DocumentRequirement;

/**
 * "Is what was entered correct?" - the checks a fill-up form runs when it is
 * submitted, on top of required/optional. A form that fails is not saved:
 * the student sees what is wrong under each field and corrects it.
 *
 * Checks come from the field's validation_rule (age, phone_ph, email,
 * numeric, enum:...), its type (date, number) and what the field is (a name,
 * a phone number, inclusive years, hours, height/weight...). The same checks
 * that can be written as a pattern are also run in the browser (client()).
 */
class FieldCorrectness
{
    /** Allowed in a person's name: letters (incl. ñ, accents), spaces, . , ' - */
    public const NAME_PATTERN = "^[\\p{L}][\\p{L}\\s.,'’\\-]*$";

    /** A phone number may only use digits, spaces, dashes, brackets and a leading +. */
    public const PHONE_CHARS = '^\\+?[0-9\\s\\-()]+$';

    private const NAME_KEYS = [
        'surname', 'given_name', 'middle_name', 'full_name', 'student_name', 'parent_guardian_name',
        'recipient_name', 'supervisor_name', 'supervisor_signature_name', 'company_representative',
        'hte_head', 'rater_name', 'nickname',
    ];

    private const HOURS_KEYS = ['required_hours', 'hte_number_of_hours'];

    /** Start/end pairs that must be in order. */
    private const DATE_PAIRS = [
        ['date_started', 'date_ended'],
        ['start_date', 'end_date'],
    ];

    public static function isName(string $key): bool
    {
        return in_array($key, self::NAME_KEYS, true) || (bool) preg_match('/^reference_\d+_name$/', $key);
    }

    public static function isPhone(string $key, ?string $rule): bool
    {
        return $rule === 'phone_ph' || in_array($key, ['contact_number', 'hte_contact_number'], true)
            || (bool) preg_match('/^reference_\d+_contact$/', $key);
    }

    /** Free-text "inclusive date" columns of the resume, e.g. "2016-2022" or "June 2025". */
    public static function isInclusiveYears(string $key, string $type): bool
    {
        return $type === 'text' && (bool) preg_match('/^(education_.+|work_experience_\d+|training_\d+)_date$/', $key);
    }

    /**
     * Extra rules per field key, to append to the required/nullable rules.
     *
     * @return array<string, array>
     */
    public static function rules(DocumentRequirement $requirement): array
    {
        $rules = [];
        $keys = $requirement->fieldDefinitions->pluck('field_key')->all();

        foreach ($requirement->fieldDefinitions as $field) {
            $key = $field->field_key;
            $rule = (string) $field->validation_rule;
            $extra = [];

            if (self::isName($key)) {
                $extra[] = 'regex:/'.self::NAME_PATTERN.'/u';
                $extra[] = 'max:120';
            }

            if (self::isPhone($key, $rule)) {
                $extra[] = function ($attribute, $value, $fail) {
                    if (filled($value) && ! self::validPhone((string) $value)) {
                        $fail('The :attribute must be a valid Philippine number, e.g. 0917 123 4567 (mobile) or (075) 123 4567 (landline).');
                    }
                };
            }

            if ($rule === 'email' || $field->field_type === 'email') {
                $extra[] = 'email:rfc';
                $extra[] = 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/';
                $extra[] = 'max:255';
            }

            if ($rule === 'age' || $key === 'age') {
                $extra[] = 'integer';
                $extra[] = 'between:15,60';
            }

            if (in_array($key, self::HOURS_KEYS, true)) {
                $extra[] = 'integer';
                $extra[] = 'between:1,3000';
            } elseif ($rule === 'numeric') {
                $extra[] = 'numeric';
                $extra[] = 'min:0';
            } elseif (str_starts_with($rule, 'numeric|')) {
                foreach (explode('|', $rule) as $piece) {
                    $extra[] = $piece;
                }
            }

            if (str_starts_with($rule, 'enum:')) {
                $options = array_values(array_filter(array_map('trim', explode(',', substr($rule, 5))), 'strlen'));
                $extra[] = function ($attribute, $value, $fail) use ($options) {
                    if (filled($value) && ! in_array(mb_strtolower(trim((string) $value)), array_map('mb_strtolower', $options), true)) {
                        $fail('Choose one of: '.implode(', ', $options).'.');
                    }
                };
            }

            if ($field->field_type === 'date') {
                $extra[] = 'date';
                $extra[] = 'after_or_equal:1990-01-01';
                $extra[] = 'before_or_equal:'.now()->addYears(3)->toDateString();
            }

            if (self::isInclusiveYears($key, (string) $field->field_type)) {
                $extra[] = function ($attribute, $value, $fail) {
                    if (filled($value) && ($message = self::checkInclusiveYears((string) $value))) {
                        $fail($message);
                    }
                };
            }

            if (in_array($key, ['height', 'weight'], true)) {
                $extra[] = 'regex:/\d/';
                $extra[] = 'max:30';
            }

            if ($extra) {
                $rules[$key] = $extra;
            }
        }

        foreach (self::DATE_PAIRS as [$start, $end]) {
            if (in_array($start, $keys, true) && in_array($end, $keys, true)) {
                $rules[$end][] = 'after_or_equal:'.$start;
            }
        }

        return $rules;
    }

    /** Field labels, so messages say "The Contact Number ..." instead of "contact_number". */
    public static function attributes(DocumentRequirement $requirement): array
    {
        return $requirement->fieldDefinitions
            ->mapWithKeys(fn ($field) => [$field->field_key => $field->label])
            ->all();
    }

    public static function messages(): array
    {
        return [
            'regex' => 'The :attribute is not in the right format.',
            'between' => 'The :attribute must be between :min and :max.',
            'email' => 'The :attribute must be a real email address, e.g. juan.delacruz@gmail.com.',
        ];
    }

    /** Messages for the checks above that depend on the field. */
    public static function fieldMessages(DocumentRequirement $requirement): array
    {
        $messages = [];
        foreach ($requirement->fieldDefinitions as $field) {
            $key = $field->field_key;
            if (self::isName($key)) {
                $messages["$key.regex"] = 'The :attribute may only contain letters, spaces, periods, commas, hyphens and apostrophes.';
            }
            if (in_array($key, ['height', 'weight'], true)) {
                $messages["$key.regex"] = $key === 'height'
                    ? 'Enter your height with a number, e.g. 5\'2" or 158 cm.'
                    : 'Enter your weight with a number, e.g. 50 kg.';
            }
            if ($key === 'age' || $field->validation_rule === 'age') {
                $messages["$key.between"] = 'The :attribute must be between 15 and 60.';
                $messages["$key.integer"] = 'The :attribute must be a whole number.';
            }
            if ($field->field_type === 'date') {
                $messages["$key.after_or_equal"] = 'The :attribute is too far in the past - check the year.';
                $messages["$key.before_or_equal"] = 'The :attribute is too far in the future - check the year.';
            }
        }
        foreach (self::DATE_PAIRS as [$start, $end]) {
            $messages["$end.after_or_equal"] = 'The :attribute cannot be earlier than the start date.';
        }

        return $messages;
    }

    /**
     * The checks the browser can run while the student types:
     * key => ['pattern' => JS regex source, 'message' => text].
     */
    public static function client(DocumentRequirement $requirement): array
    {
        $checks = [];
        foreach ($requirement->fieldDefinitions as $field) {
            $key = $field->field_key;
            $label = $field->label;
            if (self::isName($key)) {
                $checks[$key] = ['pattern' => self::NAME_PATTERN, 'flags' => 'u', 'message' => "{$label}: letters, spaces, periods, commas, hyphens and apostrophes only."];
            } elseif (self::isPhone($key, (string) $field->validation_rule)) {
                $checks[$key] = ['phone' => true, 'message' => "{$label}: enter a valid Philippine number, e.g. 0917 123 4567 or (075) 123 4567."];
            } elseif ($field->validation_rule === 'email' || $field->field_type === 'email') {
                $checks[$key] = ['pattern' => '^[^@\\s]+@[^@\\s]+\\.[A-Za-z]{2,}$', 'message' => "{$label}: enter a real email address, e.g. juan.delacruz@gmail.com."];
            } elseif ($key === 'age' || $field->validation_rule === 'age') {
                $checks[$key] = ['min' => 15, 'max' => 60, 'integer' => true, 'message' => "{$label}: enter a whole number from 15 to 60."];
            } elseif (in_array($key, self::HOURS_KEYS, true)) {
                $checks[$key] = ['min' => 1, 'max' => 3000, 'integer' => true, 'message' => "{$label}: enter the number of hours, e.g. 486."];
            } elseif (in_array($key, ['height', 'weight'], true)) {
                $checks[$key] = ['pattern' => '\\d', 'message' => $key === 'height' ? 'Height: include a number, e.g. 5\'2" or 158 cm.' : 'Weight: include a number, e.g. 50 kg.'];
            } elseif (self::isInclusiveYears($key, (string) $field->field_type)) {
                $checks[$key] = ['years' => true, 'message' => "{$label}: include the year(s), e.g. 2016-2022 or June 2025."];
            }
        }

        return $checks;
    }

    /** 09XXXXXXXXX / +639XXXXXXXXX mobile, or a landline (7-8 digits, with an optional area code). */
    public static function validPhone(string $value): bool
    {
        if (! preg_match('/'.self::PHONE_CHARS.'/', $value)) {
            return false;
        }
        $digits = preg_replace('/\D/', '', $value);

        return (bool) preg_match('/^(09\d{9}|639\d{9}|0[2-8]\d{7,9}|[1-9]\d{6,7})$/', $digits);
    }

    /** null when the inclusive years look right, otherwise what is wrong. */
    public static function checkInclusiveYears(string $value): ?string
    {
        preg_match_all('/\b(19\d{2}|20\d{2})\b/', $value, $m);
        $years = array_map('intval', $m[1]);
        if (! $years) {
            return 'Include the year(s), e.g. 2016-2022 or June 2025.';
        }
        $max = (int) now()->year + 6;
        foreach ($years as $year) {
            if ($year < 1950 || $year > $max) {
                return "{$year} doesn't look like a real year here - check it.";
            }
        }
        if (count($years) >= 2 && $years[0] > $years[1]) {
            return "The first year ({$years[0]}) should not be after the second ({$years[1]}).";
        }

        return null;
    }
}
