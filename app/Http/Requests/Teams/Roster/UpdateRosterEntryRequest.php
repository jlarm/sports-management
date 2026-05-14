<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams\Roster;

use App\Models\Team;
use App\Models\TeamPlayer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class UpdateRosterEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && ($this->user()?->can('manageRoster', $team) ?? false);
    }

    /**
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $teamId = $team instanceof Team ? $team->id : 0;

        $entry = $this->route('rosterEntry');
        $entryId = $entry instanceof TeamPlayer ? $entry->id : null;

        return [
            'jersey_number' => [
                'nullable',
                'integer',
                'between:0,999',
                Rule::unique('team_player', 'jersey_number')
                    ->where('team_id', $teamId)
                    ->ignore($entryId),
            ],
            'primary_position' => ['nullable', 'string', 'max:10'],
            'is_captain' => ['sometimes', 'boolean'],
        ];
    }
}
