<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import DivisionsController from '@/actions/App/Http/Controllers/Settings/DivisionsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { index as divisionsIndex } from '@/routes/divisions';

type Division = {
    id: number;
    name: string;
    display_order: number;
};

defineProps<{ divisions: Division[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Divisions',
                href: divisionsIndex(),
            },
        ],
    },
});

const dialogOpen = ref(false);
const editing = ref<Division | null>(null);

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(division: Division) {
    editing.value = division;
    dialogOpen.value = true;
}

function dialogTitle() {
    return editing.value ? `Edit ${editing.value.name}` : 'New division';
}
</script>

<template>
    <Head title="Divisions" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Divisions"
                description="Age groups and skill levels that teams roll up into (e.g., 10U, 12U, Varsity)."
            />
            <Button type="button" @click="openCreate" data-test="create-division">
                New division
            </Button>
        </div>

        <div
            v-if="divisions.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="divisions-empty"
        >
            No divisions yet. Create one before you build teams.
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="division in divisions"
                :key="division.id"
                class="flex items-center justify-between gap-4 p-4"
                :data-test="`division-row-${division.id}`"
            >
                <div class="space-y-1">
                    <span class="font-medium">{{ division.name }}</span>
                    <p class="text-xs text-muted-foreground">
                        Display order: {{ division.display_order }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="ghost" @click="openEdit(division)">
                        Edit
                    </Button>
                    <Form
                        v-bind="DivisionsController.destroy.form(division.id)"
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
                        Divisions group teams by age or skill level. They persist across seasons.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="
                        editing
                            ? DivisionsController.update.form(editing.id)
                            : DivisionsController.store.form()
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
                            placeholder="10U"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="display_order">Display order</Label>
                        <Input
                            id="display_order"
                            type="number"
                            min="0"
                            name="display_order"
                            :default-value="editing?.display_order ?? 0"
                        />
                        <InputError :message="errors.display_order" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" as-child>
                            <Link :href="divisionsIndex()" @click.prevent="dialogOpen = false">
                                Cancel
                            </Link>
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ editing ? 'Save changes' : 'Create division' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
