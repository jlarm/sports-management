<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams\Roster;

use App\Models\Team;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

final class StoreRosterEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && ($this->user()?->can('manageRoster', $team) ?? false);
    }

    /**
     * @return array<string, array<int, Exists|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();
        $team = $this->route('team');
        $teamId = $team instanceof Team ? $team->id : 0;

        return [
            'player_id' => [
                'required',
                'integer',
                Rule::exists('players', 'id')->where('organization_id', $orgId),
                Rule::unique('team_player', 'player_id')->where('team_id', $teamId),
            ],
            'jersey_number' => [
                'nullable',
                'integer',
                'between:0,999',
                Rule::unique('team_player', 'jersey_number')->where('team_id', $teamId),
            ],
            'primary_position' => ['nullable', 'string', 'max:10'],
            'is_captain' => ['sometimes', 'boolean'],
        ];
    }
}
