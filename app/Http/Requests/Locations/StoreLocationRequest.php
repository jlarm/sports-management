<?php

declare(strict_types=1);

namespace App\Http\Requests\Locations;

use App\Models\Location;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Location::class) ?? false;
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
                Rule::unique('locations', 'name')->where('organization_id', $orgId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'maps_link' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
