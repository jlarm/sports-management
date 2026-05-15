<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { GripVertical } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
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

const props = defineProps<{ divisions: Division[] }>();

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

const orderedDivisions = ref<Division[]>([...props.divisions]);

watch(
    () => props.divisions,
    (next) => {
        orderedDivisions.value = [...next];
    },
);

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

function persistOrder() {
    router.post(
        DivisionsController.reorder.url(),
        { ids: orderedDivisions.value.map((d) => d.id) },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Divisions" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Divisions"
                description="Age groups and skill levels that teams roll up into (e.g., 10U, 12U, Varsity)."
            />
            <Button
                type="button"
                @click="openCreate"
                data-test="create-division"
            >
                New division
            </Button>
        </div>

        <div
            v-if="orderedDivisions.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="divisions-empty"
        >
            No divisions yet. Create one before you build teams.
        </div>

        <draggable
            v-else
            v-model="orderedDivisions"
            item-key="id"
            handle=".drag-handle"
            :animation="150"
            ghost-class="opacity-50"
            tag="ul"
            class="divide-y rounded-lg border"
            @end="persistOrder"
        >
            <template #item="{ element: division }">
                <li
                    class="flex items-center justify-between gap-4 p-4"
                    :data-test="`division-row-${division.id}`"
                >
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="drag-handle inline-flex h-7 w-7 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-muted active:cursor-grabbing"
                            :aria-label="`Drag ${division.name}`"
                        >
                            <GripVertical class="size-4" />
                        </button>
                        <span class="font-medium">{{ division.name }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="openEdit(division)"
                        >
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
            </template>
        </draggable>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle() }}</DialogTitle>
                    <DialogDescription>
                        Divisions group teams by age or skill level. They
                        persist across seasons.
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

                    <DialogFooter>
                        <Button type="button" variant="ghost" as-child>
                            <Link
                                :href="divisionsIndex()"
                                @click.prevent="dialogOpen = false"
                            >
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
