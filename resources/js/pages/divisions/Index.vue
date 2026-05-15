<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Archive, GripVertical, Pencil, RotateCcw } from 'lucide-vue-next';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index as divisionsIndex } from '@/routes/divisions';

type Division = {
    id: number;
    name: string;
    display_order: number;
};

const props = defineProps<{
    divisions: Division[];
    archived: boolean;
    archived_count: number;
}>();

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

const archiveTarget = ref<Division | null>(null);
const archiveDialogOpen = ref(false);

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

function confirmArchive(division: Division) {
    archiveTarget.value = division;
    archiveDialogOpen.value = true;
}

function switchView(archived: boolean) {
    if (archived === props.archived) return;
    router.get(
        divisionsIndex().url,
        archived ? { archived: 1 } : {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="archived ? 'Archived divisions' : 'Divisions'" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Divisions"
                description="Age groups and skill levels that teams roll up into (e.g., 10U, 12U, Varsity)."
            />
            <Button
                v-if="!archived"
                type="button"
                @click="openCreate"
                data-test="create-division"
            >
                New division
            </Button>
        </div>

        <div class="inline-flex rounded-md border bg-muted p-0.5 text-sm w-fit" role="tablist">
            <button
                type="button"
                role="tab"
                :aria-selected="!archived"
                class="rounded-sm px-3 py-1.5 font-medium transition-colors"
                :class="!archived ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                data-test="divisions-tab-active"
                @click="switchView(false)"
            >
                Active
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="archived"
                class="rounded-sm px-3 py-1.5 font-medium transition-colors"
                :class="archived ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                data-test="divisions-tab-archived"
                @click="switchView(true)"
            >
                Archived
                <span
                    v-if="archived_count > 0"
                    class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted-foreground/15 px-1.5 text-xs"
                >
                    {{ archived_count }}
                </span>
            </button>
        </div>

        <div
            v-if="orderedDivisions.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="divisions-empty"
        >
            <template v-if="archived">
                No archived divisions.
            </template>
            <template v-else>
                No divisions yet. Create one before you build teams.
            </template>
        </div>

        <TooltipProvider v-else :delay-duration="150">
            <draggable
                v-model="orderedDivisions"
                item-key="id"
                handle=".drag-handle"
                :animation="150"
                ghost-class="opacity-50"
                :disabled="archived"
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
                                v-if="!archived"
                                type="button"
                                class="drag-handle inline-flex h-7 w-7 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-muted active:cursor-grabbing"
                                :aria-label="`Drag ${division.name}`"
                            >
                                <GripVertical class="size-4" />
                            </button>
                            <span class="font-medium">{{ division.name }}</span>
                        </div>

                        <div
                            v-if="archived"
                            class="inline-flex divide-x rounded-md border bg-background shadow-xs"
                        >
                            <Form
                                v-bind="DivisionsController.restore.form(division.id)"
                                class="inline-flex"
                                v-slot="{ processing }"
                            >
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="rounded-none"
                                            :disabled="processing"
                                            :data-test="`restore-division-${division.id}`"
                                            :aria-label="`Restore ${division.name}`"
                                        >
                                            <RotateCcw class="size-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Restore</TooltipContent>
                                </Tooltip>
                            </Form>
                        </div>

                        <div
                            v-else
                            class="inline-flex divide-x rounded-md border bg-background shadow-xs"
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="rounded-none"
                                        :aria-label="`Edit ${division.name}`"
                                        @click="openEdit(division)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Edit</TooltipContent>
                            </Tooltip>

                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="rounded-none text-destructive hover:text-destructive"
                                        :data-test="`archive-division-${division.id}`"
                                        :aria-label="`Archive ${division.name}`"
                                        @click="confirmArchive(division)"
                                    >
                                        <Archive class="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Archive</TooltipContent>
                            </Tooltip>
                        </div>
                    </li>
                </template>
            </draggable>
        </TooltipProvider>

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

        <Dialog v-model:open="archiveDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Archive {{ archiveTarget?.name }}?
                    </DialogTitle>
                    <DialogDescription>
                        This division will be hidden from team setup. You can
                        restore it from the Archived tab.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-if="archiveTarget"
                    v-bind="DivisionsController.destroy.form(archiveTarget.id)"
                    v-slot="{ processing }"
                    @success="archiveDialogOpen = false"
                >
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="archiveDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-archive-division"
                        >
                            Archive
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
