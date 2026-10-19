<?php

namespace App\Support;

use App\Models\DocumentRequirement;
use App\Models\Hte;

class DocumentFormRules
{
    public static function rules(DocumentRequirement $requirement, ?int $campusId = null): array
    {
        $rules = [];

        foreach ($requirement->fieldDefinitions as $field) {
            $required = $field->is_required ? 'required' : 'nullable';

            $ruleSet = match ($field->field_type) {
                'rating_scale' => [$required, 'integer', 'between:1,5'],
                'number' => [$required, 'numeric'],
                'date' => [$required, 'date'],
                'email' => [$required, 'email', 'max:255'],
                'checkbox' => [$required, 'boolean'],
                'select' => [$required, 'string', 'max:255'],
                default => [$required, 'string', 'max:'.($requirement->code === 'CERTIFICATE_OF_UNITS_EARNED' ? 10000 : 4000)],
            };

            if ($field->field_type === 'select') {
                foreach (self::enumRule($field->validation_rule) as $rule) {
                    $ruleSet[] = $rule;
                }
            }

            if ($field->field_type === 'number' && $field->validation_rule) {
                // Rules given as a "a|b|c" string must be split when they are
                // placed in an array rule set, otherwise Laravel looks for a
                // single validator literally named "numeric|min" and 500s.
                $numberRule = match ($field->validation_rule) {
                    'age' => 'integer|min:1|max:120',
                    'numeric' => 'numeric',
                    default => $field->validation_rule,
                };
                foreach (explode('|', $numberRule) as $piece) {
                    $ruleSet[] = $piece;
                }
            } elseif (! in_array($field->field_type, ['select', 'rating_scale', 'date', 'number', 'email', 'checkbox'], true) && $field->validation_rule) {
                $ruleSet[] = match ($field->validation_rule) {
                    'age' => 'integer|min:1|max:120',
                    'numeric' => 'numeric',
                    'phone_ph' => 'regex:/^(?:\\+63|0)9\\d{9}$/',
                    'email' => 'email',
                    default => 'string',
                };
            }

            if ($field->validation_rule === 'hte' && $campusId !== null) {
                $allowed = Hte::where('campus_id', $campusId)->pluck('name')->all();
                $ruleSet[] = function ($attribute, $value, $fail) use ($allowed) {
                    if (! in_array($value, $allowed, true)) {
                        $fail('Please select a valid partner HTE registered by the Coordinator.');
                    }
                };
            }

            $rules[$field->field_key] = $ruleSet;
        }

        return $rules;
    }

    public static function enumRule(?string $rule): array
    {
        if (! $rule || ! str_starts_with($rule, 'enum:')) {
            return [];
        }

        $values = array_values(array_filter(array_map('trim', explode(',', substr($rule, 5))), fn ($value) => $value !== ''));
        return $values ? ['in:'.implode(',', $values)] : [];
    }

    public static function coerceCheckboxes(array $validated): array
    {
        return collect($validated)->map(fn ($value) => is_bool($value) ? ($value ? '1' : '0') : $value)->all();
    }
}
