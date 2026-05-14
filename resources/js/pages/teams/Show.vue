<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Star } from 'lucide-vue-next';
import { ref } from 'vue';
import CoachesController from '@/actions/App/Http/Controllers/Teams/CoachesController';
import RosterController from '@/actions/App/Http/Controllers/Teams/RosterController';
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
import { show as rosterShow } from '@/routes/teams/roster';

type TeamSummary = {
    id: number;
    name: string;
    slug: string;
    season_id: number;
    division_id: number;
    season_name: string | null;
    division_name: string | null;
};

type RosterPlayer = {
    id: number;
    first_name: string;
    last_name: string;
    dob: string;
    bats: 'R' | 'L' | 'S' | null;
    throws: 'R' | 'L' | null;
};

type RosterEntry = {
    id: number;
    jersey_number: number | null;
    primary_position: string | null;
    is_captain: boolean;
    player: RosterPlayer | null;
};

type AvailablePlayer = {
    id: number;
    first_name: string;
    last_name: string;
    dob: string;
};

type CoachUser = {
    id: number;
    name: string;
    email: string;
};

type CoachEntry = {
    id: number;
    user_id: number;
    role: 'head_coach' | 'assistant_coach' | 'team_admin';
    role_label: string;
    user: CoachUser | null;
};

type RoleOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    team: TeamSummary;
    rosterEntries: RosterEntry[];
    availablePlayers: AvailablePlayer[];
    coaches: CoachEntry[];
    availableMembers: CoachUser[];
    teamRoleOptions: RoleOption[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Teams', href: teamsIndex() },
        { title: props.team.name, href: rosterShow(props.team.id) },
    ],
});

const addOpen = ref(false);
const editing = ref<RosterEntry | null>(null);
const editOpen = ref(false);

const coachOpen = ref(false);
const editingCoach = ref<CoachEntry | null>(null);
const coachEditOpen = ref(false);

function openEdit(entry: RosterEntry) {
    editing.value = entry;
    editOpen.value = true;
}

function openCoachEdit(coach: CoachEntry) {
    editingCoach.value = coach;
    coachEditOpen.value = true;
}
</script>

<template>
    <Head :title="`${team.name} roster`" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                variant="small"
                :title="team.name"
                :description="`${team.season_name ?? ''} · ${team.division_name ?? ''}`"
            />
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="ghost">
                    <Link :href="teamsIndex({ query: { season: team.season_id } })">
                        Back to teams
                    </Link>
                </Button>
                <Button
                    type="button"
                    :disabled="availablePlayers.length === 0"
                    @click="addOpen = true"
                    data-test="add-roster-entry"
                >
                    Add player
                </Button>
            </div>
        </div>

        <div
            v-if="rosterEntries.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="roster-empty"
        >
            No players on this roster yet.
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="entry in rosterEntries"
                :key="entry.id"
                class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                :data-test="`roster-row-${entry.id}`"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full border bg-muted text-sm font-semibold"
                    >
                        {{ entry.jersey_number ?? '—' }}
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">
                                {{ entry.player?.last_name ?? '—' }},
                                {{ entry.player?.first_name ?? '' }}
                            </span>
                            <Badge v-if="entry.is_captain" variant="default">
                                <Star class="size-3" />
                                Captain
                            </Badge>
                            <Badge v-if="entry.primary_position" variant="secondary">
                                {{ entry.primary_position }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="entry.player">
                                DOB {{ entry.player.dob }} · bats
                                {{ entry.player.bats ?? '—' }} / throws
                                {{ entry.player.throws ?? '—' }}
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="ghost" @click="openEdit(entry)">
                        Edit
                    </Button>
                    <Form
                        v-bind="RosterController.destroy.form([team.id, entry.id])"
                        class="inline"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="ghost"
                            class="text-destructive"
                            :disabled="processing"
                        >
                            Remove
                        </Button>
                    </Form>
                </div>
            </li>
        </ul>

        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold">Coaching staff</h2>
                <Button
                    type="button"
                    :disabled="availableMembers.length === 0"
                    @click="coachOpen = true"
                    data-test="add-coach"
                >
                    Add coach
                </Button>
            </div>
            <div
                v-if="coaches.length === 0"
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                data-test="coaches-empty"
            >
                No coaches assigned yet.
            </div>
            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="coach in coaches"
                    :key="coach.id"
                    class="flex items-center justify-between gap-4 p-4"
                    :data-test="`coach-row-${coach.id}`"
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">
                                {{ coach.user?.name ?? '—' }}
                            </span>
                            <Badge variant="secondary">{{ coach.role_label }}</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ coach.user?.email }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="openCoachEdit(coach)"
                        >
                            Change role
                        </Button>
                        <Form
                            v-bind="
                                CoachesController.destroy.form([team.id, coach.id])
                            "
                            class="inline"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="ghost"
                                class="text-destructive"
                                :disabled="processing"
                            >
                                Remove
                            </Button>
                        </Form>
                    </div>
                </li>
            </ul>
        </section>

        <Dialog v-model:open="coachOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add coach</DialogTitle>
                    <DialogDescription>
                        Pick an org member and a team role. Same person can hold
                        multiple roles (e.g., head coach + team admin).
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="CoachesController.store.form(team.id)"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="coachOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="coach_user">Person</Label>
                        <select
                            id="coach_user"
                            name="user_id"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                        >
                            <option
                                v-for="member in availableMembers"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }} ({{ member.email }})
                            </option>
                        </select>
                        <InputError :message="errors.user_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="coach_role">Role</Label>
                        <select
                            id="coach_role"
                            name="role"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                        >
                            <option
                                v-for="option in teamRoleOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.role" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="coachOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">Add</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="coachEditOpen">
            <DialogContent v-if="editingCoach">
                <DialogHeader>
                    <DialogTitle>
                        Change role — {{ editingCoach.user?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Update this person's role on the team.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="
                        CoachesController.update.form([team.id, editingCoach.id])
                    "
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="coachEditOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="edit_coach_role">Role</Label>
                        <select
                            id="edit_coach_role"
                            name="role"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                        >
                            <option
                                v-for="option in teamRoleOptions"
                                :key="option.value"
                                :value="option.value"
                                :selected="editingCoach.role === option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.role" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="coachEditOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            Save changes
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="addOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add player to roster</DialogTitle>
                    <DialogDescription>
                        Pick a player from your org pool. Jersey number is optional and
                        must be unique within this team.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="RosterController.store.form(team.id)"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="addOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="player_id">Player</Label>
                        <select
                            id="player_id"
                            name="player_id"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                        >
                            <option
                                v-for="player in availablePlayers"
                                :key="player.id"
                                :value="player.id"
                            >
                                {{ player.last_name }}, {{ player.first_name }} ({{
                                    player.dob
                                }})
                            </option>
                        </select>
                        <InputError :message="errors.player_id" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="add_jersey">Jersey #</Label>
                            <Input
                                id="add_jersey"
                                name="jersey_number"
                                type="number"
                                min="0"
                                max="999"
                            />
                            <InputError :message="errors.jersey_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="add_position">Position</Label>
                            <Input
                                id="add_position"
                                name="primary_position"
                                placeholder="e.g. P, C, 1B"
                            />
                            <InputError :message="errors.primary_position" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="add_captain"
                            type="checkbox"
                            name="is_captain"
                            value="1"
                            class="h-4 w-4 rounded border-input"
                        />
                        <Label for="add_captain" class="cursor-pointer">Captain</Label>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="addOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">Add</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="editOpen">
            <DialogContent v-if="editing">
                <DialogHeader>
                    <DialogTitle>
                        Edit roster entry —
                        {{ editing.player?.first_name }}
                        {{ editing.player?.last_name }}
                    </DialogTitle>
                    <DialogDescription>
                        Update jersey number, position, or captain flag.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="RosterController.update.form([team.id, editing.id])"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="editOpen = false"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="edit_jersey">Jersey #</Label>
                            <Input
                                id="edit_jersey"
                                name="jersey_number"
                                type="number"
                                min="0"
                                max="999"
                                :default-value="editing.jersey_number ?? ''"
                            />
                            <InputError :message="errors.jersey_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_position">Position</Label>
                            <Input
                                id="edit_position"
                                name="primary_position"
                                :default-value="editing.primary_position ?? ''"
                                placeholder="e.g. P, C, 1B"
                            />
                            <InputError :message="errors.primary_position" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="edit_captain"
                            type="checkbox"
                            name="is_captain"
                            value="1"
                            :checked="editing.is_captain"
                            class="h-4 w-4 rounded border-input"
                        />
                        <Label for="edit_captain" class="cursor-pointer">Captain</Label>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="editOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            Save changes
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
