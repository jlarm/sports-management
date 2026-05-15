<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, CalendarDays, ClipboardList, Mail, MapPin, ScrollText, ShieldAlert, UserPlus, Users } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as backgroundChecksIndex } from '@/routes/background-checks';
import { index as divisionsIndex } from '@/routes/divisions';
import { index as formsIndex } from '@/routes/forms';
import { review as submissionReview } from '@/routes/forms/submissions';
import { index as invitationsIndex } from '@/routes/invitations';
import { index as locationsIndex } from '@/routes/locations';
import { index as playersIndex } from '@/routes/players';
import { index as seasonsIndex } from '@/routes/seasons';
import { index as teamsIndex } from '@/routes/teams';

type ActiveSeason = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    days_remaining: number | null;
};

type Counts = {
    teams: number;
    players: number;
    divisions: number;
    locations: number;
};

type PendingSubmissions = {
    total: number;
    recent: Array<{
        id: number;
        form_id: number;
        form_title: string;
        submitted_at: string;
    }>;
};

type BlockedCoaches = {
    total: number;
    coaches: Array<{
        id: number;
        name: string;
        email: string;
        status: string | null;
    }>;
};

type PendingInvitations = {
    total: number;
    recent: Array<{
        id: number;
        email: string;
        role: string;
        expires_at: string;
    }>;
};

type AuditEntry = {
    id: number;
    action: string;
    actor_name: string | null;
    created_at: string;
};

defineProps<{
    can_manage: boolean;
    active_season: ActiveSeason | null;
    counts: Counts;
    pending_submissions: PendingSubmissions | null;
    blocked_coaches: BlockedCoaches | null;
    pending_invitations: PendingInvitations | null;
    recent_audit: AuditEntry[] | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <Heading
            variant="small"
            title="Dashboard"
            description="A snapshot of what needs your attention across the organization."
        />

        <section
            v-if="active_season"
            class="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between"
            data-test="dashboard-active-season"
        >
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <CalendarDays class="size-5" />
                </span>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Active season
                    </p>
                    <h2 class="text-xl font-semibold">{{ active_season.name }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ formatDate(active_season.start_date) }} – {{ formatDate(active_season.end_date) }}
                        <span v-if="active_season.days_remaining !== null">
                            · {{ active_season.days_remaining }} day<span v-if="active_season.days_remaining !== 1">s</span> remaining
                        </span>
                    </p>
                </div>
            </div>
            <Button as-child variant="outline">
                <Link :href="seasonsIndex()">Manage seasons</Link>
            </Button>
        </section>

        <section
            v-else
            class="flex items-center justify-between rounded-xl border border-dashed p-5 text-sm text-muted-foreground"
            data-test="dashboard-no-season"
        >
            <span>No active season. Create one to start setting up teams.</span>
            <Button as-child size="sm">
                <Link :href="seasonsIndex()">Set up season</Link>
            </Button>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="dashboard-counts">
            <Link
                :href="teamsIndex()"
                class="rounded-xl border bg-card p-4 shadow-xs transition-colors hover:bg-accent"
            >
                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    <Users class="size-4" /> Teams
                </div>
                <div class="mt-2 text-3xl font-semibold">{{ counts.teams }}</div>
            </Link>
            <Link
                :href="playersIndex()"
                class="rounded-xl border bg-card p-4 shadow-xs transition-colors hover:bg-accent"
            >
                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    <UserPlus class="size-4" /> Players
                </div>
                <div class="mt-2 text-3xl font-semibold">{{ counts.players }}</div>
            </Link>
            <Link
                :href="divisionsIndex()"
                class="rounded-xl border bg-card p-4 shadow-xs transition-colors hover:bg-accent"
            >
                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    <ClipboardList class="size-4" /> Divisions
                </div>
                <div class="mt-2 text-3xl font-semibold">{{ counts.divisions }}</div>
            </Link>
            <Link
                :href="locationsIndex()"
                class="rounded-xl border bg-card p-4 shadow-xs transition-colors hover:bg-accent"
            >
                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    <MapPin class="size-4" /> Locations
                </div>
                <div class="mt-2 text-3xl font-semibold">{{ counts.locations }}</div>
            </Link>
        </div>

        <div v-if="can_manage" class="grid gap-4 lg:grid-cols-2">
            <section
                v-if="pending_submissions"
                class="flex flex-col rounded-xl border bg-card p-5 shadow-xs"
                data-test="dashboard-pending-submissions"
            >
                <header class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <ClipboardList class="size-4 text-muted-foreground" />
                        <h3 class="font-semibold">Submissions awaiting review</h3>
                    </div>
                    <Badge v-if="pending_submissions.total > 0" variant="secondary">
                        {{ pending_submissions.total }}
                    </Badge>
                </header>
                <ul
                    v-if="pending_submissions.recent.length > 0"
                    class="mt-4 divide-y border-t"
                >
                    <li
                        v-for="submission in pending_submissions.recent"
                        :key="submission.id"
                        class="flex items-center justify-between gap-4 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ submission.form_title }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                #{{ submission.id }} · {{ formatDateTime(submission.submitted_at) }}
                            </p>
                        </div>
                        <Button as-child size="sm" variant="ghost">
                            <Link :href="submissionReview([submission.form_id, submission.id])">
                                Review
                            </Link>
                        </Button>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-4 rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    Nothing pending — you're all caught up.
                </p>
                <footer v-if="pending_submissions.total > 0" class="mt-4">
                    <Link
                        :href="formsIndex()"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        View all forms
                    </Link>
                </footer>
            </section>

            <section
                v-if="blocked_coaches"
                class="flex flex-col rounded-xl border bg-card p-5 shadow-xs"
                data-test="dashboard-blocked-coaches"
            >
                <header class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <ShieldAlert class="size-4 text-muted-foreground" />
                        <h3 class="font-semibold">Coaches blocked by background check</h3>
                    </div>
                    <Badge v-if="blocked_coaches.total > 0" variant="destructive">
                        {{ blocked_coaches.total }}
                    </Badge>
                </header>
                <ul
                    v-if="blocked_coaches.coaches.length > 0"
                    class="mt-4 divide-y border-t"
                >
                    <li
                        v-for="coach in blocked_coaches.coaches"
                        :key="coach.id"
                        class="flex items-center justify-between gap-4 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ coach.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ coach.email }}
                            </p>
                        </div>
                        <Badge variant="outline" class="capitalize">
                            {{ coach.status ?? 'missing' }}
                        </Badge>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-4 rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    All assigned coaches are cleared.
                </p>
                <footer v-if="blocked_coaches.total > 0" class="mt-4">
                    <Link
                        :href="backgroundChecksIndex()"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        Manage background checks
                    </Link>
                </footer>
            </section>

            <section
                v-if="pending_invitations"
                class="flex flex-col rounded-xl border bg-card p-5 shadow-xs"
                data-test="dashboard-pending-invitations"
            >
                <header class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Mail class="size-4 text-muted-foreground" />
                        <h3 class="font-semibold">Pending invitations</h3>
                    </div>
                    <Badge v-if="pending_invitations.total > 0" variant="secondary">
                        {{ pending_invitations.total }}
                    </Badge>
                </header>
                <ul
                    v-if="pending_invitations.recent.length > 0"
                    class="mt-4 divide-y border-t"
                >
                    <li
                        v-for="invitation in pending_invitations.recent"
                        :key="invitation.id"
                        class="flex items-center justify-between gap-4 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ invitation.email }}</p>
                            <p class="text-xs capitalize text-muted-foreground">
                                {{ invitation.role }}
                            </p>
                        </div>
                        <span class="text-xs text-muted-foreground">
                            expires {{ formatDateTime(invitation.expires_at) }}
                        </span>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-4 rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    No invitations are waiting.
                </p>
                <footer v-if="pending_invitations.total > 0" class="mt-4">
                    <Link
                        :href="invitationsIndex()"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        Manage invitations
                    </Link>
                </footer>
            </section>

            <section
                v-if="recent_audit"
                class="flex flex-col rounded-xl border bg-card p-5 shadow-xs"
                data-test="dashboard-recent-audit"
            >
                <header class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <ScrollText class="size-4 text-muted-foreground" />
                        <h3 class="font-semibold">Recent activity</h3>
                    </div>
                </header>
                <ul
                    v-if="recent_audit.length > 0"
                    class="mt-4 divide-y border-t"
                >
                    <li
                        v-for="entry in recent_audit"
                        :key="entry.id"
                        class="flex items-center justify-between gap-4 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ entry.action }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.actor_name ?? 'System' }}
                            </p>
                        </div>
                        <span class="text-xs text-muted-foreground">
                            {{ formatDateTime(entry.created_at) }}
                        </span>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-4 rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    No activity yet.
                </p>
                <footer v-if="recent_audit.length > 0" class="mt-4">
                    <Link
                        :href="auditLogsIndex()"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        Open audit log
                    </Link>
                </footer>
            </section>
        </div>

        <div
            v-else
            class="flex items-start gap-3 rounded-xl border border-dashed p-5 text-sm text-muted-foreground"
        >
            <AlertTriangle class="size-5 text-muted-foreground" />
            <p>
                Administrative metrics (submissions, background checks, invitations, audit log)
                are visible to organization admins.
            </p>
        </div>
    </div>
</template>
