<?php

declare(strict_types=1);

namespace App\Http\Requests\Divisions;

use App\Models\Division;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class ReorderDivisionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Division::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|Exists|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'integer',
                Rule::exists('divisions', 'id')->where('organization_id', $orgId),
            ],
        ];
    }
}
