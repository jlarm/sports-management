<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import BackgroundChecksController from '@/actions/App/Http/Controllers/BackgroundChecks/BackgroundChecksController';
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
import { index as backgroundChecksIndex } from '@/routes/background-checks';

type CheckRow = {
    id: number;
    provider: string;
    status: 'pending' | 'cleared' | 'flagged' | 'expired';
    status_label: string;
    cleared_through: string | null;
    is_current: boolean;
    notes: string | null;
};

type Row = {
    user: { id: number; name: string; email: string };
    check: CheckRow | null;
};

type StatusOption = { value: string; label: string };

defineProps<{
    rows: Row[];
    statusOptions: StatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Background checks', href: backgroundChecksIndex() },
        ],
    },
});

const dialogOpen = ref(false);
const editing = ref<Row | null>(null);

function openCreate(row: Row) {
    editing.value = row;
    dialogOpen.value = true;
}

function openEdit(row: Row) {
    editing.value = row;
    dialogOpen.value = true;
}

function dialogTitle(): string {
    return editing.value?.check
        ? `Edit check for ${editing.value.user.name}`
        : `Record check for ${editing.value?.user.name ?? ''}`;
}
</script>

<template>
    <Head title="Background checks" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <Heading
            variant="small"
            title="Background checks"
            description="Track which coaches have a current cleared check. Head and assistant coaches cannot be assigned to a team without one."
        />

        <ul class="divide-y rounded-lg border" data-test="background-checks-list">
            <li
                v-for="row in rows"
                :key="row.user.id"
                class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                :data-test="`bg-check-row-${row.user.id}`"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ row.user.name }}</span>
                        <Badge
                            v-if="row.check"
                            :variant="row.check.is_current ? 'default' : 'outline'"
                            :data-test="`bg-check-badge-${row.user.id}`"
                        >
                            {{ row.check.status_label }}
                        </Badge>
                        <Badge v-else variant="outline">No check on file</Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ row.user.email }}
                        <span v-if="row.check">
                            · {{ row.check.provider }}
                            <span v-if="row.check.cleared_through">
                                · cleared through {{ formatDate(row.check.cleared_through) }}
                            </span>
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="row.check"
                        type="button"
                        variant="ghost"
                        @click="openEdit(row)"
                        :data-test="`bg-check-edit-${row.user.id}`"
                    >
                        Edit
                    </Button>
                    <Button
                        v-else
                        type="button"
                        @click="openCreate(row)"
                        :data-test="`bg-check-record-${row.user.id}`"
                    >
                        Record check
                    </Button>
                    <Form
                        v-if="row.check"
                        v-bind="BackgroundChecksController.destroy.form(row.check.id)"
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

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle() }}</DialogTitle>
                    <DialogDescription>
                        Record the result of the background check and the date it stays cleared
                        through. Leave the date empty for a check that never expires.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="
                        editing?.check
                            ? BackgroundChecksController.update.form(editing.check.id)
                            : BackgroundChecksController.store.form()
                    "
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="dialogOpen = false"
                >
                    <input
                        v-if="!editing?.check && editing"
                        type="hidden"
                        name="user_id"
                        :value="editing.user.id"
                    />

                    <div class="grid gap-2">
                        <Label for="provider">Provider</Label>
                        <Input
                            id="provider"
                            name="provider"
                            :default-value="editing?.check?.provider ?? ''"
                            required
                            placeholder="NCSI, Sterling, etc."
                        />
                        <InputError :message="errors.provider" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            name="status"
                            :default-value="editing?.check?.status ?? 'pending'"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="cleared_through">Cleared through</Label>
                        <Input
                            id="cleared_through"
                            name="cleared_through"
                            type="date"
                            :default-value="editing?.check?.cleared_through ?? ''"
                        />
                        <InputError :message="errors.cleared_through" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            :default-value="editing?.check?.notes ?? ''"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus:outline-none"
                        ></textarea>
                        <InputError :message="errors.notes" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" @click="dialogOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ editing?.check ? 'Save changes' : 'Record check' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
