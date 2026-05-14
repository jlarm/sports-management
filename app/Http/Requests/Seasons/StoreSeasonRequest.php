<?php

declare(strict_types=1);

namespace App\Http\Requests\Seasons;

use App\Models\Season;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class StoreSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Season::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|Unique|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('seasons', 'name')->where('organization_id', $orgId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_registration_open' => ['sometimes', 'boolean'],
        ];
    }
}
