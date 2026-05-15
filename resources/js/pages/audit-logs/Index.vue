<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/utils';
import { index as auditLogsIndex } from '@/routes/audit-logs';

type Entry = {
    id: number;
    action: string;
    created_at: string;
    subject_type: string | null;
    subject_id: number | null;
    payload: Record<string, unknown> | null;
    actor: { id: number; name: string } | null;
};

type Filters = {
    action: string;
    from: string;
    to: string;
};

const props = defineProps<{
    entries: Entry[];
    pagination: { current_page: number; last_page: number; total: number };
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Audit log', href: auditLogsIndex() }],
    },
});

const action = ref(props.filters.action ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

let debounce: ReturnType<typeof setTimeout> | undefined;
function applyFilters() {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            auditLogsIndex().url,
            { action: action.value, from: from.value, to: to.value },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 250);
}

watch([action, from, to], applyFilters);

function clearFilters() {
    action.value = '';
    from.value = '';
    to.value = '';
}
</script>

<template>
    <Head title="Audit log" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <Heading
            variant="small"
            title="Audit log"
            :description="`${pagination.total} recorded events. Filter by action prefix (e.g. player., form.) or date range.`"
        />

        <div class="grid gap-3 sm:grid-cols-4" data-test="audit-log-filters">
            <div class="space-y-1">
                <Label for="filter-action">Action prefix</Label>
                <Input id="filter-action" v-model="action" placeholder="player." />
            </div>
            <div class="space-y-1">
                <Label for="filter-from">From</Label>
                <Input id="filter-from" type="date" v-model="from" />
            </div>
            <div class="space-y-1">
                <Label for="filter-to">To</Label>
                <Input id="filter-to" type="date" v-model="to" />
            </div>
            <div class="flex items-end">
                <Button type="button" variant="ghost" @click="clearFilters">Reset</Button>
            </div>
        </div>

        <div
            v-if="entries.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="audit-log-empty"
        >
            No audit entries match the current filters.
        </div>

        <ul v-else class="divide-y rounded-lg border" data-test="audit-log-list">
            <li
                v-for="entry in entries"
                :key="entry.id"
                class="space-y-1 p-4 text-sm"
                :data-test="`audit-log-row-${entry.id}`"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">{{ entry.action }}</Badge>
                        <span v-if="entry.subject_type" class="text-xs text-muted-foreground">
                            {{ entry.subject_type }} #{{ entry.subject_id }}
                        </span>
                    </div>
                    <span class="text-xs text-muted-foreground">
                        {{ formatDateTime(entry.created_at) }}
                        <span v-if="entry.actor"> · {{ entry.actor.name }}</span>
                        <span v-else> · system</span>
                    </span>
                </div>
                <pre
                    v-if="entry.payload"
                    class="overflow-x-auto rounded bg-muted/50 p-2 text-xs"
                >{{ JSON.stringify(entry.payload, null, 2) }}</pre>
            </li>
        </ul>

        <p class="text-xs text-muted-foreground">
            Page {{ pagination.current_page }} of {{ pagination.last_page }}
        </p>
    </div>
</template>
