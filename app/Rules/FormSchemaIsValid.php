<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\FieldType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class FormSchemaIsValid implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be a schema object.');

            return;
        }

        $fields = $value['fields'] ?? null;

        if (! is_array($fields)) {
            $fail('The :attribute must include a fields array.');

            return;
        }

        $seenKeys = [];

        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                $fail("Field at index {$index} is not an object.");

                continue;
            }

            $key = $field['key'] ?? null;
            $label = $field['label'] ?? null;
            $type = $field['type'] ?? null;
            $options = $field['options'] ?? null;

            if (! is_string($key) || $key === '' || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
                $fail("Field at index {$index} must have a snake_case key.");

                continue;
            }

            if (in_array($key, $seenKeys, true)) {
                $fail("Duplicate field key '{$key}'. Each field must have a unique key.");

                continue;
            }
            $seenKeys[] = $key;

            if (! is_string($label) || mb_trim($label) === '') {
                $fail("Field '{$key}' must have a non-empty label.");

                continue;
            }

            if (! is_string($type) || FieldType::tryFrom($type) === null) {
                $fail("Field '{$key}' has an unsupported type.");

                continue;
            }

            $fieldType = FieldType::from($type);

            if ($fieldType->requiresOptions()) {
                if (! is_array($options) || $options === []) {
                    $fail("Field '{$key}' of type 'select' must include at least one option.");

                    continue;
                }

                foreach ($options as $option) {
                    if (! is_string($option) || mb_trim($option) === '') {
                        $fail("Field '{$key}' has an empty option entry.");

                        break;
                    }
                }
            }
        }
    }
}
