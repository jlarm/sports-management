<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/utils';
import { index as seasonsIndex } from '@/routes/seasons';
import { store as rolloverStore } from '@/routes/seasons/rollover';

type DivisionGroup = {
    division_id: number | null;
    division_name: string;
    teams: { id: number; name: string; slug: string }[];
};

type Division = { id: number; name: string };

type SourceSeason = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

const props = defineProps<{
    source_season: SourceSeason;
    teams_by_division: DivisionGroup[];
    divisions: Division[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Seasons', href: seasonsIndex() },
        {
            title: `Roll over ${props.source_season.name}`,
            href: seasonsIndex(),
        },
    ],
});

const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
    clone_teams: true,
    clone_roster_division_ids: [] as number[],
});

function submit() {
    form.post(rolloverStore(props.source_season.id).url);
}

function hasTeamsInDivision(divisionId: number): boolean {
    return props.teams_by_division.some((g) => g.division_id === divisionId && g.teams.length > 0);
}
</script>

<template>
    <Head :title="`Roll over ${source_season.name}`" />

    <form class="flex flex-col space-y-6 px-4 py-6 md:px-6" @submit.prevent="submit">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="`Roll over from ${source_season.name}`"
                description="Create the next season and optionally copy teams and rosters forward."
            />
            <Button as-child variant="ghost">
                <Link :href="seasonsIndex()">Cancel</Link>
            </Button>
        </div>

        <section class="space-y-3 rounded-lg border p-4">
            <h3 class="text-sm font-semibold">Source season</h3>
            <p class="text-sm text-muted-foreground">
                {{ source_season.name }} · {{ formatDate(source_season.start_date) }} →
                {{ formatDate(source_season.end_date) }}
                <Badge v-if="source_season.is_active" class="ml-2">Active</Badge>
            </p>
        </section>

        <section class="space-y-4 rounded-lg border p-4">
            <h3 class="text-sm font-semibold">New season</h3>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="space-y-1 sm:col-span-3">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" required placeholder="Fall 2026" data-test="rollover-name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="space-y-1">
                    <Label for="start_date">Start date</Label>
                    <Input id="start_date" type="date" v-model="form.start_date" required data-test="rollover-start" />
                    <InputError :message="form.errors.start_date" />
                </div>
                <div class="space-y-1">
                    <Label for="end_date">End date</Label>
                    <Input id="end_date" type="date" v-model="form.end_date" required data-test="rollover-end" />
                    <InputError :message="form.errors.end_date" />
                </div>
            </div>
        </section>

        <section class="space-y-3 rounded-lg border p-4">
            <h3 class="text-sm font-semibold">What to copy forward</h3>

            <label class="flex items-start gap-3 text-sm">
                <input
                    v-model="form.clone_teams"
                    type="checkbox"
                    class="mt-1 h-4 w-4 rounded border-input"
                    data-test="rollover-clone-teams"
                />
                <span>
                    <span class="font-medium">Clone teams</span>
                    <span class="block text-xs text-muted-foreground">
                        Copy each team (name, slug, division) into the new season. The new teams
                        start with empty rosters unless you opt-in below.
                    </span>
                </span>
            </label>

            <div v-if="form.clone_teams" class="space-y-2 pl-7" data-test="rollover-division-list">
                <p class="text-xs font-medium text-muted-foreground">
                    Carry rosters forward for these divisions:
                </p>
                <div v-if="divisions.length === 0" class="text-xs text-muted-foreground">
                    No divisions yet.
                </div>
                <label
                    v-for="division in divisions"
                    :key="division.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="form.clone_roster_division_ids"
                        type="checkbox"
                        :value="division.id"
                        :disabled="!hasTeamsInDivision(division.id)"
                        class="h-4 w-4 rounded border-input"
                        :data-test="`rollover-roster-${division.id}`"
                    />
                    <span :class="{ 'text-muted-foreground': !hasTeamsInDivision(division.id) }">
                        {{ division.name }}
                        <span v-if="!hasTeamsInDivision(division.id)" class="text-xs"> (no teams)</span>
                    </span>
                </label>
            </div>
        </section>

        <section v-if="teams_by_division.length > 0" class="space-y-3 rounded-lg border p-4">
            <h3 class="text-sm font-semibold">Teams in the source season</h3>
            <div v-for="group in teams_by_division" :key="group.division_id ?? 0" class="text-sm">
                <p class="font-medium">{{ group.division_name }}</p>
                <ul class="ml-4 list-disc text-muted-foreground">
                    <li v-for="team in group.teams" :key="team.id">{{ team.name }}</li>
                </ul>
            </div>
        </section>

        <div class="flex items-center justify-end gap-3">
            <Button as-child variant="ghost">
                <Link :href="seasonsIndex()">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing" data-test="rollover-submit">
                Roll over season
            </Button>
        </div>
    </form>
</template>
