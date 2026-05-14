<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forms;

use App\Enums\FieldType;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PublicFormController extends Controller
{
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
            ],
        ]);
    }

    public function submit(Request $request, int $form): RedirectResponse
    {
        $resolved = $this->loadPublishedForm($form);

        $request->validate($this->rulesFor($resolved));

        $payload = $request->input('data');
        $data = is_array($payload) ? $payload : [];

        Submission::create([
            'organization_id' => $resolved->organization_id,
            'form_id' => $resolved->id,
            'submitted_by_user_id' => $request->user()?->id,
            'schema_snapshot' => $resolved->schema,
            'schema_version' => $resolved->schema_version,
            'data' => $data,
            'submitted_at' => now(),
        ]);

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

    private function loadPublishedForm(int $id): Form
    {
        $form = Form::query()->withoutGlobalScopes()->find($id);

        abort_unless($form !== null && $form->isPublished(), 404);

        return $form;
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

        return $rules;
    }
}
