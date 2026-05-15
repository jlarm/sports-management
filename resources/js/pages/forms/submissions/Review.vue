<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/utils';
import { index as formsIndex } from '@/routes/forms';
import {
    index as submissionsIndex,
    process as submissionProcess,
    show as submissionShow,
} from '@/routes/forms/submissions';

type FieldShape = {
    key: string;
    label: string;
    type: string;
    required?: boolean;
    options?: string[];
};

type ExtractedPlayer = {
    first_name: string | null;
    last_name: string | null;
    dob: string | null;
    jersey_size: string | null;
    medical_notes: string | null;
};

type ExtractedGuardian = {
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
};

type PlayerCandidate = {
    id: number;
    first_name: string;
    last_name: string;
    dob: string;
    jersey_size: string | null;
};

type GuardianCandidate = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
};

type MatchPayload = {
    can_match_player: boolean;
    can_match_guardian: boolean;
    player: { extracted: ExtractedPlayer; candidates: PlayerCandidate[] };
    guardian: { extracted: ExtractedGuardian; candidates: GuardianCandidate[] };
};

type ConsentRow = {
    id: number;
    type: string;
    type_label: string;
    version: number;
    accepted_at: string;
};

type SubmissionPayload = {
    id: number;
    submitted_at: string;
    status: 'pending' | 'processed' | 'skipped';
    status_label: string;
    data: Record<string, unknown>;
    schema_snapshot: { fields: FieldShape[] };
    consents: ConsentRow[];
};

type FormPayload = { id: number; title: string };

const props = defineProps<{
    form: FormPayload;
    submission: SubmissionPayload;
    match: MatchPayload;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Forms', href: formsIndex() },
        { title: props.form.title, href: submissionsIndex(props.form.id) },
        { title: 'Submissions', href: submissionsIndex(props.form.id) },
        {
            title: `Review #${props.submission.id}`,
            href: submissionShow([props.form.id, props.submission.id]),
        },
    ],
});

type PlayerAction = 'created' | 'merged' | 'force_created' | 'skipped';
type GuardianAction = 'created' | 'merged' | 'skipped';

const defaultPlayerAction: PlayerAction =
    props.match.player.candidates.length === 1
        ? 'merged'
        : props.match.player.candidates.length === 0 && props.match.can_match_player
          ? 'created'
          : props.match.player.candidates.length > 1
            ? 'merged'
            : 'skipped';

const defaultGuardianAction: GuardianAction =
    props.match.guardian.candidates.length === 1
        ? 'merged'
        : props.match.guardian.candidates.length === 0 && props.match.can_match_guardian
          ? 'created'
          : props.match.guardian.candidates.length > 1
            ? 'merged'
            : 'skipped';

const decision = useForm({
    player_action: defaultPlayerAction as PlayerAction,
    player_id: props.match.player.candidates[0]?.id ?? null,
    player: {
        first_name: props.match.player.extracted.first_name ?? '',
        last_name: props.match.player.extracted.last_name ?? '',
        dob: props.match.player.extracted.dob ?? '',
        jersey_size: props.match.player.extracted.jersey_size ?? '',
        medical_notes: props.match.player.extracted.medical_notes ?? '',
    },
    guardian_action: defaultGuardianAction as GuardianAction,
    guardian_id: props.match.guardian.candidates[0]?.id ?? null,
    guardian: {
        first_name: props.match.guardian.extracted.first_name ?? '',
        last_name: props.match.guardian.extracted.last_name ?? '',
        email: props.match.guardian.extracted.email ?? '',
        phone: props.match.guardian.extracted.phone ?? '',
    },
    notes: '',
});

function submit() {
    decision.post(submissionProcess([props.form.id, props.submission.id]).url);
}

function displayValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (Array.isArray(value)) return value.join(', ');
    return String(value);
}
</script>

<template>
    <Head :title="`Review submission #${submission.id}`" />

    <form class="flex flex-col space-y-6 px-4 py-6 md:px-6" @submit.prevent="submit">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="`Review submission #${submission.id}`"
                :description="`${form.title} · submitted ${formatDateTime(submission.submitted_at)}`"
            />
            <Button as-child variant="ghost">
                <Link :href="submissionsIndex(form.id)">Back</Link>
            </Button>
        </div>

        <section
            v-if="submission.consents.length > 0"
            class="space-y-2 rounded-lg border p-3"
            data-test="review-consents"
        >
            <h3 class="text-sm font-semibold">Consents captured</h3>
            <ul class="space-y-1 text-sm">
                <li v-for="consent in submission.consents" :key="consent.id">
                    {{ consent.type_label }} · v{{ consent.version }}
                </li>
            </ul>
        </section>

        <section class="space-y-3" data-test="review-submission-data">
            <h3 class="text-sm font-semibold">Submitted data</h3>
            <dl class="divide-y rounded-lg border">
                <div
                    v-for="field in submission.schema_snapshot.fields"
                    :key="field.key"
                    class="grid gap-1 p-3 sm:grid-cols-3"
                >
                    <dt class="text-xs font-medium text-muted-foreground">{{ field.label }}</dt>
                    <dd class="text-sm sm:col-span-2">{{ displayValue(submission.data[field.key]) }}</dd>
                </div>
            </dl>
        </section>

        <section class="space-y-3" data-test="review-player">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Player</h3>
                <Badge v-if="match.player.candidates.length === 0" variant="outline">No matches</Badge>
                <Badge v-else-if="match.player.candidates.length === 1" variant="secondary">1 match</Badge>
                <Badge v-else variant="default">{{ match.player.candidates.length }} matches</Badge>
            </div>

            <div class="space-y-2 rounded-lg border p-4">
                <label class="flex items-start gap-3 text-sm">
                    <input
                        v-model="decision.player_action"
                        type="radio"
                        value="merged"
                        :disabled="match.player.candidates.length === 0"
                        data-test="player-action-merged"
                    />
                    <span class="flex-1">
                        <span class="font-medium">Merge with existing</span>
                        <span class="block text-xs text-muted-foreground">Keep the existing player record. Updates jersey size / medical notes.</span>
                    </span>
                </label>
                <ul v-if="decision.player_action === 'merged'" class="space-y-1 pl-7">
                    <li v-for="candidate in match.player.candidates" :key="candidate.id">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="decision.player_id" type="radio" :value="candidate.id" />
                            <span>
                                {{ candidate.first_name }} {{ candidate.last_name }} —
                                {{ candidate.dob }}
                                <span v-if="candidate.jersey_size" class="text-xs text-muted-foreground">({{ candidate.jersey_size }})</span>
                            </span>
                        </label>
                    </li>
                </ul>
                <InputError :message="decision.errors.player_id" />

                <label class="flex items-start gap-3 text-sm">
                    <input v-model="decision.player_action" type="radio" value="created" data-test="player-action-created" />
                    <span class="flex-1">
                        <span class="font-medium">Create new player</span>
                        <span class="block text-xs text-muted-foreground">No suitable match — make a new player from the submission.</span>
                    </span>
                </label>

                <label v-if="match.player.candidates.length > 0" class="flex items-start gap-3 text-sm">
                    <input v-model="decision.player_action" type="radio" value="force_created" data-test="player-action-force-created" />
                    <span class="flex-1">
                        <span class="font-medium">Force create (override match)</span>
                        <span class="block text-xs text-muted-foreground">Create a new player anyway. The audit log will record the override.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 text-sm">
                    <input v-model="decision.player_action" type="radio" value="skipped" data-test="player-action-skipped" />
                    <span class="font-medium">Skip — don't create or link a player</span>
                </label>

                <div
                    v-if="decision.player_action === 'created' || decision.player_action === 'force_created'"
                    class="grid gap-3 pt-2 sm:grid-cols-2"
                    data-test="review-player-fields"
                >
                    <div class="space-y-1">
                        <Label>First name</Label>
                        <Input v-model="decision.player.first_name" />
                        <InputError :message="decision.errors['player.first_name']" />
                    </div>
                    <div class="space-y-1">
                        <Label>Last name</Label>
                        <Input v-model="decision.player.last_name" />
                        <InputError :message="decision.errors['player.last_name']" />
                    </div>
                    <div class="space-y-1">
                        <Label>Date of birth</Label>
                        <Input v-model="decision.player.dob" type="date" />
                        <InputError :message="decision.errors['player.dob']" />
                    </div>
                    <div class="space-y-1">
                        <Label>Jersey size</Label>
                        <Input v-model="decision.player.jersey_size" />
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label>Medical notes</Label>
                        <Input v-model="decision.player.medical_notes" />
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-3" data-test="review-guardian">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Guardian</h3>
                <Badge v-if="match.guardian.candidates.length === 0" variant="outline">No matches</Badge>
                <Badge v-else-if="match.guardian.candidates.length === 1" variant="secondary">1 match</Badge>
                <Badge v-else variant="default">{{ match.guardian.candidates.length }} matches</Badge>
            </div>

            <div class="space-y-2 rounded-lg border p-4">
                <label class="flex items-start gap-3 text-sm">
                    <input
                        v-model="decision.guardian_action"
                        type="radio"
                        value="merged"
                        :disabled="match.guardian.candidates.length === 0"
                        data-test="guardian-action-merged"
                    />
                    <span class="font-medium">Merge with existing</span>
                </label>
                <ul v-if="decision.guardian_action === 'merged'" class="space-y-1 pl-7">
                    <li v-for="candidate in match.guardian.candidates" :key="candidate.id">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="decision.guardian_id" type="radio" :value="candidate.id" />
                            <span>{{ candidate.first_name }} {{ candidate.last_name }} — {{ candidate.email }}</span>
                        </label>
                    </li>
                </ul>
                <InputError :message="decision.errors.guardian_id" />

                <label class="flex items-start gap-3 text-sm">
                    <input v-model="decision.guardian_action" type="radio" value="created" data-test="guardian-action-created" />
                    <span class="font-medium">Create new guardian</span>
                </label>

                <label class="flex items-start gap-3 text-sm">
                    <input v-model="decision.guardian_action" type="radio" value="skipped" data-test="guardian-action-skipped" />
                    <span class="font-medium">Skip — don't create or link a guardian</span>
                </label>

                <div
                    v-if="decision.guardian_action === 'created'"
                    class="grid gap-3 pt-2 sm:grid-cols-2"
                    data-test="review-guardian-fields"
                >
                    <div class="space-y-1">
                        <Label>First name</Label>
                        <Input v-model="decision.guardian.first_name" />
                        <InputError :message="decision.errors['guardian.first_name']" />
                    </div>
                    <div class="space-y-1">
                        <Label>Last name</Label>
                        <Input v-model="decision.guardian.last_name" />
                        <InputError :message="decision.errors['guardian.last_name']" />
                    </div>
                    <div class="space-y-1">
                        <Label>Email</Label>
                        <Input v-model="decision.guardian.email" type="email" />
                        <InputError :message="decision.errors['guardian.email']" />
                    </div>
                    <div class="space-y-1">
                        <Label>Phone</Label>
                        <Input v-model="decision.guardian.phone" />
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-1">
            <Label>Notes (optional)</Label>
            <Input v-model="decision.notes" />
        </section>

        <div class="flex items-center justify-end gap-3">
            <Button as-child variant="ghost">
                <Link :href="submissionShow([form.id, submission.id])">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="decision.processing" data-test="review-submit">
                Save decision
            </Button>
        </div>
    </form>
</template>
