<?php

declare(strict_types=1);

namespace App\Http\Requests\Locations;

use App\Models\Location;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $location = $this->route('location');

        return $location instanceof Location
            && ($this->user()?->can('update', $location) ?? false);
    }

    /**
     * @return array<string, array<int, ValidationRule|Unique|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();
        $location = $this->route('location');
        $locationId = $location instanceof Location ? $location->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('locations', 'name')
                    ->where('organization_id', $orgId)
                    ->ignore($locationId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'maps_link' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
