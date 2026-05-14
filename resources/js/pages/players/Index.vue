<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PlayersController from '@/actions/App/Http/Controllers/Players/PlayersController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/utils';
import { index as playersIndex } from '@/routes/players';

type Player = {
    id: number;
    first_name: string;
    last_name: string;
    dob: string;
    graduation_year: number | null;
    gender: string | null;
    bats: 'R' | 'L' | 'S' | null;
    throws: 'R' | 'L' | null;
    school: string | null;
    jersey_size: string | null;
    medical_notes: string | null;
    external_id: string | null;
    notes: string | null;
};

defineProps<{ players: Player[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Players',
                href: playersIndex(),
            },
        ],
    },
});

const dialogOpen = ref(false);
const editing = ref<Player | null>(null);

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(player: Player) {
    editing.value = player;
    dialogOpen.value = true;
}

function dialogTitle() {
    return editing.value
        ? `Edit ${editing.value.first_name} ${editing.value.last_name}`
        : 'New player';
}

const today = new Date();
function ageAt(dobIso: string): number | null {
    const dob = new Date(dobIso);

    if (Number.isNaN(dob.getTime())) {
        return null;
    }

    let age = today.getFullYear() - dob.getFullYear();
    const beforeBirthday =
        today.getMonth() < dob.getMonth() ||
        (today.getMonth() === dob.getMonth() &&
            today.getDate() < dob.getDate());

    if (beforeBirthday) {
        age -= 1;
    }

    return age;
}

const battingOptions = ['R', 'L', 'S'] as const;
const throwingOptions = ['R', 'L'] as const;
</script>

<template>
    <Head title="Players" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Players"
                description="The global pool of players for your organization. Persists across seasons."
            />
            <Button type="button" @click="openCreate" data-test="create-player">
                New player
            </Button>
        </div>

        <div
            v-if="players.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="players-empty"
        >
            No players yet. Add one to start building rosters.
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="player in players"
                :key="player.id"
                class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                :data-test="`player-row-${player.id}`"
            >
                <div class="space-y-1">
                    <span class="font-medium"
                        >{{ player.last_name }}, {{ player.first_name }}</span
                    >
                    <p class="text-xs text-muted-foreground">
                        DOB {{ formatDate(player.dob) }}
                        <span v-if="ageAt(player.dob) !== null">
                            · age {{ ageAt(player.dob) }}</span
                        >
                        <span v-if="player.graduation_year">
                            · class of {{ player.graduation_year }}</span
                        >
                        <span v-if="player.bats || player.throws">
                            · bats {{ player.bats ?? '—' }} / throws
                            {{ player.throws ?? '—' }}</span
                        >
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="openEdit(player)"
                    >
                        Edit
                    </Button>
                    <Form
                        v-bind="PlayersController.destroy.form(player.id)"
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

        <Dialog v-model:open="dialogOpen">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle() }}</DialogTitle>
                    <DialogDescription>
                        Player records persist across seasons and feed every
                        roster.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="
                        editing
                            ? PlayersController.update.form(editing.id)
                            : PlayersController.store.form()
                    "
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="dialogOpen = false"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="first_name">First name</Label>
                            <Input
                                id="first_name"
                                name="first_name"
                                :default-value="editing?.first_name ?? ''"
                                required
                                autocomplete="off"
                            />
                            <InputError :message="errors.first_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="last_name">Last name</Label>
                            <Input
                                id="last_name"
                                name="last_name"
                                :default-value="editing?.last_name ?? ''"
                                required
                                autocomplete="off"
                            />
                            <InputError :message="errors.last_name" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="dob">Date of birth</Label>
                            <Input
                                id="dob"
                                type="date"
                                name="dob"
                                :default-value="editing?.dob ?? ''"
                                required
                            />
                            <InputError :message="errors.dob" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="graduation_year">Graduation year</Label>
                            <Input
                                id="graduation_year"
                                type="number"
                                min="2020"
                                max="2050"
                                name="graduation_year"
                                :default-value="editing?.graduation_year ?? ''"
                            />
                            <InputError :message="errors.graduation_year" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="grid gap-2">
                            <Label for="bats">Bats</Label>
                            <select
                                id="bats"
                                name="bats"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                            >
                                <option value="">—</option>
                                <option
                                    v-for="opt in battingOptions"
                                    :key="opt"
                                    :value="opt"
                                    :selected="editing?.bats === opt"
                                >
                                    {{ opt }}
                                </option>
                            </select>
                            <InputError :message="errors.bats" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="throws">Throws</Label>
                            <select
                                id="throws"
                                name="throws"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                            >
                                <option value="">—</option>
                                <option
                                    v-for="opt in throwingOptions"
                                    :key="opt"
                                    :value="opt"
                                    :selected="editing?.throws === opt"
                                >
                                    {{ opt }}
                                </option>
                            </select>
                            <InputError :message="errors.throws" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="jersey_size">Jersey size</Label>
                            <Input
                                id="jersey_size"
                                name="jersey_size"
                                :default-value="editing?.jersey_size ?? ''"
                            />
                            <InputError :message="errors.jersey_size" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="gender">Gender</Label>
                            <Input
                                id="gender"
                                name="gender"
                                :default-value="editing?.gender ?? ''"
                            />
                            <InputError :message="errors.gender" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="school">School</Label>
                            <Input
                                id="school"
                                name="school"
                                :default-value="editing?.school ?? ''"
                            />
                            <InputError :message="errors.school" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="external_id">External ID</Label>
                        <Input
                            id="external_id"
                            name="external_id"
                            :default-value="editing?.external_id ?? ''"
                            placeholder="Imported badge number or registration ID"
                        />
                        <InputError :message="errors.external_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="medical_notes">Medical notes</Label>
                        <textarea
                            id="medical_notes"
                            name="medical_notes"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus:outline-none"
                            :value="editing?.medical_notes ?? ''"
                        ></textarea>
                        <InputError :message="errors.medical_notes" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus:outline-none"
                            :value="editing?.notes ?? ''"
                        ></textarea>
                        <InputError :message="errors.notes" />
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
                            {{ editing ? 'Save changes' : 'Create player' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
