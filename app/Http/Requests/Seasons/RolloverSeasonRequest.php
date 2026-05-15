<?php

declare(strict_types=1);

namespace App\Http\Requests\Seasons;

use App\Models\Season;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

final class RolloverSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $season = $this->route('season');

        return $season instanceof Season
            && ($this->user()?->can('rollover', $season) ?? false);
    }

    /**
     * @return array<string, array<int, Exists|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('seasons', 'name')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'clone_teams' => ['required', 'boolean'],
            'clone_roster_division_ids' => ['nullable', 'array'],
            'clone_roster_division_ids.*' => [
                'integer',
                Rule::exists('divisions', 'id')->where('organization_id', $orgId),
            ],
        ];
    }
}
