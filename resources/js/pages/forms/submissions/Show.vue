<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/utils';
import { index as formsIndex } from '@/routes/forms';
import { review as submissionReview, index as submissionsIndex, show as submissionShow } from '@/routes/forms/submissions';

type FieldShape = {
    key: string;
    label: string;
    type: string;
    required?: boolean;
    options?: string[];
};

type DecisionRow = {
    id: number;
    decided_at: string;
    player_action: string;
    player_action_label: string;
    player: { id: number; first_name: string; last_name: string } | null;
    guardian_action: string;
    guardian_action_label: string;
    guardian: { id: number; first_name: string; last_name: string; email: string } | null;
    notes: string | null;
    decided_by: { name: string } | null;
};

type SubmissionPayload = {
    id: number;
    submitted_at: string;
    schema_version: number;
    is_outdated: boolean;
    status: 'pending' | 'processed' | 'skipped';
    status_label: string;
    schema_snapshot: { fields: FieldShape[] };
    data: Record<string, unknown>;
    submitted_by: { name: string; email: string } | null;
    decisions: DecisionRow[];
};

type FormPayload = {
    id: number;
    title: string;
    schema_version: number;
};

const props = defineProps<{
    form: FormPayload;
    submission: SubmissionPayload;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Forms', href: formsIndex() },
        { title: props.form.title, href: submissionsIndex(props.form.id) },
        { title: 'Submissions', href: submissionsIndex(props.form.id) },
        {
            title: `#${props.submission.id}`,
            href: submissionShow([props.form.id, props.submission.id]),
        },
    ],
});

function displayValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (Array.isArray(value)) return value.join(', ');
    return String(value);
}
</script>

<template>
    <Head :title="`Submission #${submission.id}`" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="`Submission #${submission.id}`"
                :description="`${form.title} · submitted ${formatDateTime(submission.submitted_at)}`"
            />
            <div class="flex items-center gap-2">
                <Button
                    v-if="submission.status === 'pending'"
                    as-child
                    variant="default"
                    data-test="submission-review-button"
                >
                    <Link :href="submissionReview([form.id, submission.id])">Review</Link>
                </Button>
                <Button as-child variant="ghost">
                    <Link :href="submissionsIndex(form.id)">Back to submissions</Link>
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            <Badge :variant="submission.status === 'pending' ? 'default' : 'outline'" data-test="submission-status-badge">
                {{ submission.status_label }}
            </Badge>
            <Badge :variant="submission.is_outdated ? 'outline' : 'secondary'">
                v{{ submission.schema_version }}
            </Badge>
            <span v-if="submission.is_outdated">
                The form schema has changed since this submission. Fields below
                render against the schema the submitter actually saw.
            </span>
            <span v-if="submission.submitted_by">
                · submitted by {{ submission.submitted_by.name }}
                ({{ submission.submitted_by.email }})
            </span>
            <span v-else>· submitted anonymously</span>
        </div>

        <dl class="divide-y rounded-lg border">
            <div
                v-for="field in submission.schema_snapshot.fields"
                :key="field.key"
                class="grid gap-1 p-4 sm:grid-cols-3"
                :data-test="`submission-field-${field.key}`"
            >
                <dt class="text-sm font-medium text-muted-foreground">
                    {{ field.label }}
                    <span class="block text-xs text-muted-foreground/70">
                        {{ field.type }} · {{ field.key }}
                    </span>
                </dt>
                <dd class="text-sm sm:col-span-2">
                    {{ displayValue(submission.data[field.key]) }}
                </dd>
            </div>
        </dl>

        <div v-if="submission.decisions.length > 0" class="space-y-3">
            <h3 class="text-sm font-semibold">Processing history</h3>
            <ul class="divide-y rounded-lg border" data-test="submission-decisions">
                <li
                    v-for="decision in submission.decisions"
                    :key="decision.id"
                    class="space-y-2 p-4 text-sm"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ formatDateTime(decision.decided_at) }}
                        <span v-if="decision.decided_by"> · by {{ decision.decided_by.name }}</span>
                    </p>
                    <p>
                        <span class="font-medium">Player:</span>
                        {{ decision.player_action_label }}
                        <span v-if="decision.player">
                            — {{ decision.player.first_name }} {{ decision.player.last_name }}
                        </span>
                    </p>
                    <p>
                        <span class="font-medium">Guardian:</span>
                        {{ decision.guardian_action_label }}
                        <span v-if="decision.guardian">
                            — {{ decision.guardian.first_name }} {{ decision.guardian.last_name }}
                            ({{ decision.guardian.email }})
                        </span>
                    </p>
                    <p v-if="decision.notes" class="text-muted-foreground">
                        {{ decision.notes }}
                    </p>
                </li>
            </ul>
        </div>
    </div>
</template>
