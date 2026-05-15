<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Enums\ConsentType;
use App\Models\Form;
use App\Rules\FormSchemaIsValid;
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
     * @return array<string, array<int, Enum|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'schema' => ['required', 'array', new FormSchemaIsValid],
            'required_consents' => ['nullable', 'array'],
            'required_consents.*' => [Rule::enum(ConsentType::class)],
        ];
    }
}
