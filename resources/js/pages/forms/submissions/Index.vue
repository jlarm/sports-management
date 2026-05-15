<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/utils';
import { index as formsIndex } from '@/routes/forms';
import { review as submissionReview, show as submissionShow, index as submissionsIndex } from '@/routes/forms/submissions';

type SubmissionRow = {
    id: number;
    submitted_at: string;
    schema_version: number;
    status: 'pending' | 'processed' | 'skipped';
    status_label: string;
    submitted_by: { name: string; email: string } | null;
};

type FormPayload = {
    id: number;
    title: string;
    schema_version: number;
};

const props = defineProps<{
    form: FormPayload;
    submissions: SubmissionRow[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Forms', href: formsIndex() },
        { title: props.form.title, href: submissionsIndex(props.form.id) },
        { title: 'Submissions', href: submissionsIndex(props.form.id) },
    ],
});
</script>

<template>
    <Head :title="`${form.title} submissions`" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="`${form.title} — submissions`"
                :description="`Current schema version v${form.schema_version}. Older entries render against the version they were submitted under.`"
            />
            <Button as-child variant="ghost">
                <Link :href="formsIndex()">Back to forms</Link>
            </Button>
        </div>

        <div
            v-if="submissions.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="submissions-empty"
        >
            No submissions yet.
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="submission in submissions"
                :key="submission.id"
                class="flex items-center justify-between gap-4 p-4"
                :data-test="`submission-row-${submission.id}`"
            >
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">
                            #{{ submission.id }}
                        </span>
                        <Badge
                            v-if="submission.schema_version !== form.schema_version"
                            variant="outline"
                        >
                            v{{ submission.schema_version }} (older schema)
                        </Badge>
                        <Badge v-else variant="secondary">
                            v{{ submission.schema_version }}
                        </Badge>
                        <Badge
                            :variant="submission.status === 'pending' ? 'default' : 'outline'"
                            :data-test="`submission-status-${submission.id}`"
                        >
                            {{ submission.status_label }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDateTime(submission.submitted_at) }}
                        <span v-if="submission.submitted_by">
                            · by {{ submission.submitted_by.name }}
                            ({{ submission.submitted_by.email }})
                        </span>
                        <span v-else> · anonymous</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="submission.status === 'pending'"
                        as-child
                        variant="default"
                        :data-test="`submission-review-${submission.id}`"
                    >
                        <Link :href="submissionReview([form.id, submission.id])">Review</Link>
                    </Button>
                    <Button as-child variant="ghost">
                        <Link :href="submissionShow([form.id, submission.id])">View</Link>
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
