<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InvitationsController from '@/actions/App/Http/Controllers/Settings/InvitationsController';
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
import { formatDateTime } from '@/lib/utils';
import { index as invitationsIndex } from '@/routes/invitations';

type Invitation = {
    id: number;
    email: string;
    role: 'owner' | 'admin' | 'coach' | 'guardian';
    status: 'pending' | 'accepted' | 'declined' | 'revoked' | 'expired';
    expires_at: string;
    invited_by: string | null;
};

defineProps<{
    invitations: Invitation[];
    organizationName: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Invitations',
                href: invitationsIndex(),
            },
        ],
    },
});

const dialogOpen = ref(false);

const statusVariant: Record<
    Invitation['status'],
    'default' | 'secondary' | 'outline'
> = {
    pending: 'default',
    accepted: 'secondary',
    declined: 'outline',
    revoked: 'outline',
    expired: 'outline',
};

function statusLabel(status: Invitation['status']) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>

<template>
    <Head title="Invitations" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Invitations"
                :description="`Invite admins, coaches, and guardians to ${organizationName}.`"
            />
            <Button
                type="button"
                @click="dialogOpen = true"
                data-test="create-invitation"
            >
                Invite person
            </Button>
        </div>

        <div
            v-if="invitations.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="invitations-empty"
        >
            No invitations yet. Send one to start building your team.
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="invitation in invitations"
                :key="invitation.id"
                class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                :data-test="`invitation-row-${invitation.id}`"
            >
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ invitation.email }}</span>
                        <Badge :variant="statusVariant[invitation.status]">
                            {{ statusLabel(invitation.status) }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ invitation.role }} · expires
                        {{ formatDateTime(invitation.expires_at) }}
                        <span v-if="invitation.invited_by">
                            · invited by {{ invitation.invited_by }}</span
                        >
                    </p>
                </div>
                <Form
                    v-if="invitation.status === 'pending'"
                    v-bind="InvitationsController.destroy.form(invitation.id)"
                    class="inline"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="ghost"
                        class="text-destructive"
                        :disabled="processing"
                    >
                        Revoke
                    </Button>
                </Form>
            </li>
        </ul>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite person</DialogTitle>
                    <DialogDescription>
                        We'll email them a link to accept the invitation. It
                        expires in 7 days.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="InvitationsController.store.form()"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="dialogOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autocomplete="off"
                            placeholder="coach@example.com"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role">Role</Label>
                        <select
                            id="role"
                            name="role"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                            required
                        >
                            <option value="admin">Admin</option>
                            <option value="coach" selected>Coach</option>
                            <option value="guardian">Guardian</option>
                        </select>
                        <InputError :message="errors.role" />
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
                            Send invitation
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
