<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams\Coaches;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

final class StoreTeamCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && ($this->user()?->can('manageCoaches', $team) ?? false);
    }

    /**
     * @return array<string, array<int, Enum|Exists|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();
        $team = $this->route('team');
        $teamId = $team instanceof Team ? $team->id : 0;
        $userId = $this->integer('user_id');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', $orgId),
            ],
            'role' => [
                'required',
                Rule::enum(TeamRole::class),
                Rule::unique('team_user', 'role')
                    ->where('team_id', $teamId)
                    ->where('user_id', $userId),
            ],
        ];
    }
}
