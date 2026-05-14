<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitations;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\NotIn;

final class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
    }

    /**
     * @return array<string, array<int, Closure|Enum|NotIn|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentTenant::class)->id();

        return [
            'email' => [
                'required',
                'string',
                'email',
                'lowercase',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($orgId): void {
                    assert(is_string($value));

                    $alreadyMember = DB::table('organization_user')
                        ->where('organization_id', $orgId)
                        ->whereIn('user_id', DB::table('users')->select('id')->where('email', $value))
                        ->exists();

                    if ($alreadyMember) {
                        $fail(__('This person is already a member of the organization.'));

                        return;
                    }

                    $pending = Invitation::query()
                        ->where('organization_id', $orgId)
                        ->where('email', $value)
                        ->pending()
                        ->exists();

                    if ($pending) {
                        $fail(__('A pending invitation already exists for this email.'));
                    }
                },
            ],
            'role' => [
                'required',
                Rule::enum(OrganizationRole::class),
                Rule::notIn([OrganizationRole::Owner->value]),
            ],
        ];
    }
}
