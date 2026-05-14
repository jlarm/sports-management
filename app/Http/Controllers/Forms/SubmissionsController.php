<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Submission;
use Inertia\Inertia;
use Inertia\Response;

final class SubmissionsController extends Controller
{
    public function index(Form $form): Response
    {
        $this->authorize('viewSubmissions', $form);

        $submissions = Submission::query()
            ->where('form_id', $form->id)
            ->with('submittedBy:id,name,email')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Submission $submission): array => [
                'id' => $submission->id,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'schema_version' => $submission->schema_version,
                'submitted_by' => $submission->submittedBy !== null
                    ? [
                        'name' => $submission->submittedBy->name,
                        'email' => $submission->submittedBy->email,
                    ]
                    : null,
            ])
            ->all();

        return Inertia::render('forms/submissions/Index', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'schema_version' => $form->schema_version,
            ],
            'submissions' => $submissions,
        ]);
    }

    public function show(Form $form, Submission $submission): Response
    {
        $this->authorize('viewSubmissions', $form);
        $this->authorize('view', $submission);

        abort_unless($submission->form_id === $form->id, 404);

        $submission->loadMissing('submittedBy');

        return Inertia::render('forms/submissions/Show', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'schema_version' => $form->schema_version,
            ],
            'submission' => [
                'id' => $submission->id,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'schema_version' => $submission->schema_version,
                'is_outdated' => $submission->schema_version !== $form->schema_version,
                'schema_snapshot' => $submission->schema_snapshot,
                'data' => $submission->data,
                'submitted_by' => $submission->submittedBy !== null
                    ? [
                        'name' => $submission->submittedBy->name,
                        'email' => $submission->submittedBy->email,
                    ]
                    : null,
            ],
        ]);
    }
}
