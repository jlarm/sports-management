<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

use App\Enums\MatchAction;
use App\Models\Form;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

final class ProcessSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $form = $this->route('form');

        return $form instanceof Form
            && ($this->user()?->can('processSubmissions', $form) ?? false);
    }

    /**
     * @return array<string, array<int, Enum|Exists|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();

        return [
            'player_action' => ['required', Rule::enum(MatchAction::class)],
            'player_id' => [
                'nullable',
                'integer',
                Rule::exists('players', 'id')->where('organization_id', $orgId),
            ],
            'player.first_name' => ['nullable', 'string', 'max:80'],
            'player.last_name' => ['nullable', 'string', 'max:80'],
            'player.dob' => ['nullable', 'date'],
            'player.jersey_size' => ['nullable', 'string', 'max:20'],
            'player.medical_notes' => ['nullable', 'string', 'max:5000'],
            'guardian_action' => ['required', Rule::enum(MatchAction::class)],
            'guardian_id' => [
                'nullable',
                'integer',
                Rule::exists('guardians', 'id')->where('organization_id', $orgId),
            ],
            'guardian.first_name' => ['nullable', 'string', 'max:80'],
            'guardian.last_name' => ['nullable', 'string', 'max:80'],
            'guardian.email' => ['nullable', 'email', 'max:191'],
            'guardian.phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function playerAction(): MatchAction
    {
        return MatchAction::from($this->string('player_action')->toString());
    }

    public function guardianAction(): MatchAction
    {
        return MatchAction::from($this->string('guardian_action')->toString());
    }
}
