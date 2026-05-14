<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TeamsController from '@/actions/App/Http/Controllers/Teams/TeamsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as teamsIndex } from '@/routes/teams';

type Team = {
    id: number;
    name: string;
    slug: string;
    season_id: number;
    division_id: number;
    season_name: string | null;
    division_name: string | null;
};

type SeasonOption = {
    id: number;
    name: string;
    is_active: boolean;
};

type DivisionOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    teams: Team[];
    seasons: SeasonOption[];
    divisions: DivisionOption[];
    selectedSeasonId: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Teams',
                href: teamsIndex(),
            },
        ],
    },
});

const dialogOpen = ref(false);
const editing = ref<Team | null>(null);

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(team: Team) {
    editing.value = team;
    dialogOpen.value = true;
}

function dialogTitle() {
    return editing.value ? `Edit ${editing.value.name}` : 'New team';
}

function changeSeason(event: Event) {
    const target = event.target as HTMLSelectElement;
    const next = Number.parseInt(target.value, 10);

    if (Number.isFinite(next)) {
        router.visit(teamsIndex({ query: { season: next } }), {
            preserveScroll: true,
        });
    }
}

const teamsByDivision = computed(() => {
    const groups = new Map<number | null, { name: string; teams: Team[] }>();

    for (const team of props.teams) {
        if (!groups.has(team.division_id)) {
            groups.set(team.division_id, {
                name: team.division_name ?? 'Unknown division',
                teams: [],
            });
        }

        groups.get(team.division_id)?.teams.push(team);
    }

    return Array.from(groups.values());
});

const defaultSeasonForNew = computed(
    () => props.selectedSeasonId ?? props.seasons[0]?.id ?? '',
);
const defaultDivisionForNew = computed(() => props.divisions[0]?.id ?? '');
</script>

<template>
    <Head title="Teams" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                variant="small"
                title="Teams"
                description="Seasonal squads. Each team rolls up into a division and a season."
            />
            <div class="flex flex-wrap items-center gap-3">
                <Label for="season" class="text-xs text-muted-foreground"
                    >Season</Label
                >
                <select
                    id="season"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none sm:w-56"
                    :value="selectedSeasonId ?? ''"
                    @change="changeSeason"
                >
                    <option v-if="seasons.length === 0" value="">
                        No seasons yet
                    </option>
                    <option
                        v-for="season in seasons"
                        :key="season.id"
                        :value="season.id"
                    >
                        {{ season.name }}{{ season.is_active ? ' (active)' : '' }}
                    </option>
                </select>
                <Button
                    type="button"
                    :disabled="seasons.length === 0 || divisions.length === 0"
                    @click="openCreate"
                    data-test="create-team"
                >
                    New team
                </Button>
            </div>
        </div>

        <div
            v-if="seasons.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="teams-empty-no-seasons"
        >
            Create and activate a season before adding teams.
        </div>

        <div
            v-else-if="divisions.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="teams-empty-no-divisions"
        >
            Add a division (e.g., 10U, Varsity) before adding teams.
        </div>

        <div
            v-else-if="teams.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="teams-empty"
        >
            No teams in this season yet.
        </div>

        <div v-else class="space-y-6">
            <section
                v-for="group in teamsByDivision"
                :key="group.name"
                class="space-y-2"
            >
                <h3 class="text-sm font-semibold text-muted-foreground uppercase">
                    {{ group.name }}
                </h3>
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="team in group.teams"
                        :key="team.id"
                        class="flex items-center justify-between gap-4 p-4"
                        :data-test="`team-row-${team.id}`"
                    >
                        <div class="space-y-1">
                            <span class="font-medium">{{ team.name }}</span>
                            <p class="text-xs text-muted-foreground">
                                {{ team.season_name }}
                                <Badge variant="secondary" class="ml-2">{{
                                    team.division_name
                                }}</Badge>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                @click="openEdit(team)"
                            >
                                Edit
                            </Button>
                            <Form
                                v-bind="TeamsController.destroy.form(team.id)"
                                class="inline"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    class="text-destructive"
                                    :disabled="processing"
                                >
                                    Archive
                                </Button>
                            </Form>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle() }}</DialogTitle>
                    <DialogDescription>
                        Teams belong to a single season and division. Pick where this
                        one lives.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="
                        editing
                            ? TeamsController.update.form(editing.id)
                            : TeamsController.store.form()
                    "
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="dialogOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="editing?.name ?? ''"
                            required
                            autocomplete="off"
                            placeholder="10U Red"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="season_id">Season</Label>
                            <select
                                id="season_id"
                                name="season_id"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                            >
                                <option
                                    v-for="season in seasons"
                                    :key="season.id"
                                    :value="season.id"
                                    :selected="
                                        (editing?.season_id ?? defaultSeasonForNew) ===
                                        season.id
                                    "
                                >
                                    {{ season.name
                                    }}{{ season.is_active ? ' (active)' : '' }}
                                </option>
                            </select>
                            <InputError :message="errors.season_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="division_id">Division</Label>
                            <select
                                id="division_id"
                                name="division_id"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                            >
                                <option
                                    v-for="division in divisions"
                                    :key="division.id"
                                    :value="division.id"
                                    :selected="
                                        (editing?.division_id ??
                                            defaultDivisionForNew) === division.id
                                    "
                                >
                                    {{ division.name }}
                                </option>
                            </select>
                            <InputError :message="errors.division_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input
                            id="slug"
                            name="slug"
                            :default-value="editing?.slug ?? ''"
                            placeholder="Auto-generated from name if left blank"
                        />
                        <InputError :message="errors.slug" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="dialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ editing ? 'Save changes' : 'Create team' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
