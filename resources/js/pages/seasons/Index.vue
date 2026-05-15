<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import SeasonsController from '@/actions/App/Http/Controllers/Settings/SeasonsController';
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
import { formatDate } from '@/lib/utils';
import { index as seasonsIndex } from '@/routes/seasons';
import { show as rolloverShow } from '@/routes/seasons/rollover';

type Season = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    is_registration_open: boolean;
};

defineProps<{ seasons: Season[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Seasons',
                href: seasonsIndex(),
            },
        ],
    },
});

const dialogOpen = ref(false);
const editing = ref<Season | null>(null);

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(season: Season) {
    editing.value = season;
    dialogOpen.value = true;
}

function dialogTitle() {
    return editing.value ? `Edit ${editing.value.name}` : 'New season';
}
</script>

<template>
    <Head title="Seasons" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Seasons"
                description="Time containers for teams, rosters, and games. Only one season can be active at a time."
            />
            <Button type="button" @click="openCreate" data-test="create-season">
                New season
            </Button>
        </div>

        <div
            v-if="seasons.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="seasons-empty"
        >
            No seasons yet. Create one to start rostering teams.
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="season in seasons"
                :key="season.id"
                class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                :data-test="`season-row-${season.id}`"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ season.name }}</span>
                        <Badge v-if="season.is_active" variant="default"
                            >Active</Badge
                        >
                        <Badge
                            v-if="season.is_registration_open"
                            variant="secondary"
                        >
                            Registration open
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(season.start_date) }} →
                        {{ formatDate(season.end_date) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Form
                        v-if="!season.is_active"
                        v-bind="SeasonsController.activate.form(season.id)"
                        class="inline"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="secondary"
                            :disabled="processing"
                        >
                            Activate
                        </Button>
                    </Form>
                    <Button as-child variant="ghost" :data-test="`season-rollover-${season.id}`">
                        <Link :href="rolloverShow(season.id)">Roll over</Link>
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="openEdit(season)"
                    >
                        Edit
                    </Button>
                    <Form
                        v-bind="SeasonsController.destroy.form(season.id)"
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
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle() }}</DialogTitle>
                    <DialogDescription>
                        Seasons keep teams, rosters, and games scoped to a
                        single competition window.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="
                        editing
                            ? SeasonsController.update.form(editing.id)
                            : SeasonsController.store.form()
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
                            placeholder="Spring 2026"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="start_date">Start date</Label>
                            <Input
                                id="start_date"
                                type="date"
                                name="start_date"
                                :default-value="editing?.start_date ?? ''"
                                required
                            />
                            <InputError :message="errors.start_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="end_date">End date</Label>
                            <Input
                                id="end_date"
                                type="date"
                                name="end_date"
                                :default-value="editing?.end_date ?? ''"
                                required
                            />
                            <InputError :message="errors.end_date" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_registration_open"
                            type="checkbox"
                            name="is_registration_open"
                            value="1"
                            :checked="editing?.is_registration_open ?? false"
                            class="h-4 w-4 rounded border-input"
                        />
                        <Label
                            for="is_registration_open"
                            class="cursor-pointer"
                        >
                            Registration open
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" as-child>
                            <Link
                                :href="seasonsIndex()"
                                @click.prevent="dialogOpen = false"
                            >
                                Cancel
                            </Link>
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ editing ? 'Save changes' : 'Create season' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
