<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Models\Team;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

final class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && ($this->user()?->can('update', $team) ?? false);
    }

    /**
     * @return array<string, array<int, Exists|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();
        $team = $this->route('team');
        $teamId = $team instanceof Team ? $team->id : null;
        $seasonId = $this->integer('season_id');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('teams', 'slug')
                    ->where('organization_id', $orgId)
                    ->where('season_id', $seasonId)
                    ->ignore($teamId),
            ],
            'season_id' => [
                'required',
                'integer',
                Rule::exists('seasons', 'id')->where('organization_id', $orgId),
            ],
            'division_id' => [
                'required',
                'integer',
                Rule::exists('divisions', 'id')->where('organization_id', $orgId),
            ],
        ];
    }
}
