<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams\Coaches;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamUser;
use App\Services\BackgroundChecks\BackgroundCheckGate;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

final class UpdateTeamCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && ($this->user()?->can('manageCoaches', $team) ?? false);
    }

    /**
     * @return array<string, array<int, Enum|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $teamId = $team instanceof Team ? $team->id : 0;

        $coach = $this->route('coach');
        $coachId = $coach instanceof TeamUser ? $coach->id : null;
        $userId = $coach instanceof TeamUser ? $coach->user_id : 0;

        return [
            'role' => [
                'required',
                Rule::enum(TeamRole::class),
                Rule::unique('team_user', 'role')
                    ->where('team_id', $teamId)
                    ->where('user_id', $userId)
                    ->ignore($coachId),
            ],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rawRole = $this->input('role');
                if (! is_string($rawRole)) {
                    return;
                }
                $role = TeamRole::tryFrom($rawRole);
                if ($role === null) {
                    return;
                }

                $gate = app(BackgroundCheckGate::class);
                if (! $gate->roleRequiresCheck($role)) {
                    return;
                }

                $coach = $this->route('coach');
                $userId = $coach instanceof TeamUser ? $coach->user_id : 0;

                $orgId = app(CurrentTenant::class)->id();
                if (! $gate->hasCurrentClearedCheck($orgId, $userId)) {
                    $validator->errors()->add(
                        'role',
                        __('This coach needs a current cleared background check before they can be assigned to that role.'),
                    );
                }
            },
        ];
    }
}
