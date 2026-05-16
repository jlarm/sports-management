<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Enums\ConsentType;
use App\Models\Form;
use App\Rules\FormSchemaIsValid;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $form = $this->route('form');

        return $form instanceof Form
            && ($this->user()?->can('update', $form) ?? false);
    }

    /**
     * @return array<string, array<int, Closure|Enum|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'schema' => ['required', 'array', new FormSchemaIsValid],
            'required_consents' => ['nullable', 'array'],
            'required_consents.*' => [Rule::enum(ConsentType::class)],
            'custom_consents' => ['nullable', 'array', $this->customConsentsRule()],
            'custom_consents.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'max:64'],
            'custom_consents.*.label' => ['required', 'string', 'max:160'],
            'custom_consents.*.text' => ['required', 'string', 'max:2000'],
        ];
    }

    private function customConsentsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $seen = [];
            $reserved = array_map(fn (ConsentType $t): string => $t->value, ConsentType::presets());

            foreach ($value as $index => $entry) {
                $key = is_array($entry) ? ($entry['key'] ?? null) : null;

                if (! is_string($key) || $key === '') {
                    continue;
                }

                if (in_array($key, $reserved, true)) {
                    $fail("Custom consent key '{$key}' collides with a preset consent type.");

                    continue;
                }

                if (in_array($key, $seen, true)) {
                    $fail("Duplicate custom consent key '{$key}'.");

                    continue;
                }

                $seen[] = $key;
                unset($index);
            }
        };
    }
}
