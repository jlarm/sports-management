<?php

declare(strict_types=1);

namespace App\Http\Requests\Players;

use App\Enums\BattingHand;
use App\Enums\ThrowingHand;
use App\Models\Player;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;

final class UpdatePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $player = $this->route('player');

        return $player instanceof Player
            && ($this->user()?->can('update', $player) ?? false);
    }

    /**
     * @return array<string, array<int, Enum|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();
        $player = $this->route('player');
        $playerId = $player instanceof Player ? $player->id : null;

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'dob' => ['required', 'date', 'before:today'],
            'graduation_year' => ['nullable', 'integer', 'between:2020,2050'],
            'gender' => ['nullable', 'string', 'max:30'],
            'bats' => ['nullable', Rule::enum(BattingHand::class)],
            'throws' => ['nullable', Rule::enum(ThrowingHand::class)],
            'school' => ['nullable', 'string', 'max:120'],
            'jersey_size' => ['nullable', 'string', 'max:20'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'external_id' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('players', 'external_id')
                    ->where('organization_id', $orgId)
                    ->ignore($playerId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
