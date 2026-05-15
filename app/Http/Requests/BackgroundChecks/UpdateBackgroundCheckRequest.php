<?php

declare(strict_types=1);

namespace App\Http\Requests\BackgroundChecks;

use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateBackgroundCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        $check = $this->route('background_check');

        return $check instanceof BackgroundCheck
            && ($this->user()?->can('update', $check) ?? false);
    }

    /**
     * @return array<string, array<int, Enum|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:80'],
            'status' => ['required', Rule::enum(BackgroundCheckStatus::class)],
            'cleared_through' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
