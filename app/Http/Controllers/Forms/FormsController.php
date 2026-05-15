<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forms;

use App\Enums\ConsentType;
use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreFormRequest;
use App\Http\Requests\Forms\UpdateFormRequest;
use App\Http\Resources\FormResource;
use App\Models\Form;
use App\Services\Audit\AuditLogger;
use App\Services\Consents\ConsentTextRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FormsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Form::class);

        $forms = Form::query()
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('forms/Index', [
            'forms' => FormResource::collection($forms)->toArray($request),
        ]);
    }

    public function edit(Request $request, Form $form, ConsentTextRegistry $consents): Response
    {
        $this->authorize('update', $form);

        $fieldTypeOptions = array_map(
            fn (FieldType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'requires_options' => $type->requiresOptions(),
            ],
            FieldType::cases(),
        );

        $consentOptions = array_map(
            fn (ConsentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'text' => $consents->entry($type)['text'],
            ],
            ConsentType::cases(),
        );

        return Inertia::render('forms/Edit', [
            'form' => new FormResource($form)->toArray($request),
            'fieldTypeOptions' => $fieldTypeOptions,
            'consentOptions' => $consentOptions,
        ]);
    }

    public function store(StoreFormRequest $request): RedirectResponse
    {
        $form = Form::create([
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'status' => FormStatus::Draft->value,
            'schema' => $request->validated()['schema'],
            'schema_version' => 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form created.')]);

        return to_route('forms.edit', $form);
    }

    public function update(UpdateFormRequest $request, Form $form): RedirectResponse
    {
        if ($form->isClosed()) {
            abort(403, 'A closed form cannot be edited.');
        }

        $newSchema = $request->validated()['schema'];
        $schemaChanged = $form->schema !== $newSchema;

        $validated = $request->validated();
        /** @var array<int, string>|null $requiredConsents */
        $requiredConsents = $validated['required_consents'] ?? null;

        $form->update([
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'schema' => $newSchema,
            'schema_version' => $schemaChanged && $form->isPublished()
                ? $form->schema_version + 1
                : $form->schema_version,
            'required_consents' => $requiredConsents === null || $requiredConsents === [] ? null : array_values($requiredConsents),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form updated.')]);

        return to_route('forms.edit', $form);
    }

    public function destroy(Form $form): RedirectResponse
    {
        $this->authorize('delete', $form);

        $form->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form archived.')]);

        return to_route('forms.index');
    }

    public function publish(Form $form, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('publish', $form);

        if ($form->isDraft()) {
            $form->forceFill(['status' => FormStatus::Published->value])->save();
            $audit->log(
                organizationId: $form->organization_id,
                action: 'form.published',
                subject: $form,
            );
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Form published.')]);
        }

        return to_route('forms.index');
    }

    public function close(Form $form, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('close', $form);

        if ($form->isPublished()) {
            $form->forceFill(['status' => FormStatus::Closed->value])->save();
            $audit->log(
                organizationId: $form->organization_id,
                action: 'form.closed',
                subject: $form,
            );
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Form closed.')]);
        }

        return to_route('forms.index');
    }
}
