<?php

declare(strict_types=1);

namespace App\Http\Requests\BackgroundChecks;

use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

final class StoreBackgroundCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BackgroundCheck::class) ?? false;
    }

    /**
     * @return array<string, array<int, Enum|Exists|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', $orgId),
                Rule::unique('coach_background_checks', 'user_id')->where('organization_id', $orgId),
            ],
            'provider' => ['required', 'string', 'max:80'],
            'status' => ['required', Rule::enum(BackgroundCheckStatus::class)],
            'cleared_through' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
