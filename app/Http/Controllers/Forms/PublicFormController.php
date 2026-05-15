<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forms;

use App\Enums\ConsentType;
use App\Enums\FieldType;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\SubmissionConfirmationToSubmitter;
use App\Notifications\SubmissionReceivedAdminAlert;
use App\Services\Audit\AuditLogger;
use App\Services\Consents\ConsentTextRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PublicFormController extends Controller
{
    public function __construct(private readonly ConsentTextRegistry $consentText) {}

    public function show(int $form): Response
    {
        $resolved = $this->loadPublishedForm($form);

        return Inertia::render('forms/Respond', [
            'form' => [
                'id' => $resolved->id,
                'title' => $resolved->title,
                'description' => $resolved->description,
                'schema' => $resolved->schema,
                'schema_version' => $resolved->schema_version,
                'consents' => $this->consentsPayload($resolved),
            ],
        ]);
    }

    public function submit(Request $request, int $form, AuditLogger $audit): RedirectResponse
    {
        $resolved = $this->loadPublishedForm($form);

        $request->validate($this->rulesFor($resolved));

        $payload = $request->input('data');
        /** @var array<string, mixed> $data */
        $data = is_array($payload) ? $payload : [];

        $submission = DB::transaction(function () use ($resolved, $data, $request, $audit): Submission {
            $submission = Submission::create([
                'organization_id' => $resolved->organization_id,
                'form_id' => $resolved->id,
                'submitted_by_user_id' => $request->user()?->id,
                'schema_snapshot' => $resolved->schema,
                'schema_version' => $resolved->schema_version,
                'data' => $data,
                'submitted_at' => now(),
            ]);

            foreach ($resolved->requiredConsentTypes() as $type) {
                $entry = $this->consentText->entry($type);

                $consent = Consent::query()->create([
                    'organization_id' => $resolved->organization_id,
                    'submission_id' => $submission->id,
                    'consent_type' => $type,
                    'consent_text_snapshot' => $entry['text'],
                    'consent_text_version' => $entry['version'],
                    'accepted_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                ]);

                $audit->log(
                    organizationId: $resolved->organization_id,
                    action: 'consent.granted',
                    subject: $consent,
                    payload: ['consent_type' => $type->value, 'submission_id' => $submission->id],
                );
            }

            return $submission;
        });

        $this->dispatchNotifications($resolved, $submission, $data, $request->user());

        return to_route('public-forms.thanks', ['form' => $resolved->id]);
    }

    public function thanks(int $form): Response
    {
        $resolved = $this->loadPublishedForm($form);

        return Inertia::render('forms/Thanks', [
            'form' => [
                'id' => $resolved->id,
                'title' => $resolved->title,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatchNotifications(Form $form, Submission $submission, array $data, ?User $authedUser): void
    {
        $organization = Organization::query()->findOrFail($form->organization_id);

        $admins = User::query()
            ->whereHas('organizations', function (Builder $q) use ($organization): void {
                $q->where('organization_id', $organization->id)
                    ->whereIn('role', [
                        OrganizationRole::Owner->value,
                        OrganizationRole::Admin->value,
                    ]);
            })
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new SubmissionReceivedAdminAlert($submission, $form));
        }

        $parentEmail = $data['parent_email'] ?? null;
        $submitterEmail = is_string($parentEmail) && $parentEmail !== ''
            ? $parentEmail
            : $authedUser?->email;

        if (is_string($submitterEmail) && $submitterEmail !== '') {
            Notification::route('mail', $submitterEmail)
                ->notify(new SubmissionConfirmationToSubmitter($submission, $form, $organization));
        }
    }

    private function loadPublishedForm(int $id): Form
    {
        $form = Form::query()->withoutGlobalScopes()->find($id);

        abort_unless($form !== null && $form->isPublished(), 404);

        return $form;
    }

    /**
     * @return array<int, array{type: string, label: string, text: string, version: int}>
     */
    private function consentsPayload(Form $form): array
    {
        return array_map(function (ConsentType $type): array {
            $entry = $this->consentText->entry($type);

            return [
                'type' => $type->value,
                'label' => $type->label(),
                'text' => $entry['text'],
                'version' => $entry['version'],
            ];
        }, $form->requiredConsentTypes());
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    private function rulesFor(Form $form): array
    {
        $rules = [
            'data' => ['required', 'array'],
        ];

        foreach ($form->schema['fields'] as $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key)) {
                continue;
            }
            if ($key === '') {
                continue;
            }

            $fieldRules = [
                ($field['required'] ?? false) === true ? 'required' : 'nullable',
            ];
            $rawType = $field['type'] ?? '';
            $type = is_string($rawType) ? FieldType::tryFrom($rawType) : null;

            match ($type) {
                FieldType::Text, FieldType::Textarea => $fieldRules[] = 'string',
                FieldType::Number => $fieldRules[] = 'numeric',
                FieldType::Date => $fieldRules[] = 'date',
                FieldType::Select => $fieldRules[] = Rule::in(
                    is_array($field['options'] ?? null) ? $field['options'] : []
                ),
                FieldType::Checkbox => $fieldRules[] = 'boolean',
                null => null,
            };

            if (in_array($type, [FieldType::Text, FieldType::Textarea], true)) {
                $fieldRules[] = 'max:5000';
            }

            $rules['data.'.$key] = $fieldRules;
        }

        $requiredConsents = $form->requiredConsentTypes();
        if ($requiredConsents !== []) {
            $rules['consents'] = ['required', 'array'];
            foreach ($requiredConsents as $type) {
                $rules['consents.'.$type->value] = ['accepted'];
            }
        }

        return $rules;
    }
}
