<?php

declare(strict_types=1);

namespace App\Http\Requests\Divisions;

use App\Models\Division;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class UpdateDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $division = $this->route('division');

        return $division instanceof Division
            && ($this->user()?->can('update', $division) ?? false);
    }

    /**
     * @return array<string, array<int, ValidationRule|Unique|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();
        $division = $this->route('division');
        $divisionId = $division instanceof Division ? $division->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('divisions', 'name')
                    ->where('organization_id', $orgId)
                    ->ignore($divisionId),
            ],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
