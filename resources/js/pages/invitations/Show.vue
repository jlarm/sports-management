<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AcceptInvitationController from '@/actions/App/Http/Controllers/Invitations/AcceptInvitationController';
import DeclineInvitationController from '@/actions/App/Http/Controllers/Invitations/DeclineInvitationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDateTime } from '@/lib/utils';

type Invitation = {
    organization_name: string;
    role: string;
    email: string;
    status: 'pending' | 'accepted' | 'declined' | 'revoked' | 'expired';
    invited_by: string | null;
    expires_at: string;
    email_matches: boolean;
};

defineProps<{
    invitation: Invitation;
    token: string;
}>();
</script>

<template>
    <Head :title="`Invitation to ${invitation.organization_name}`" />

    <div class="flex min-h-screen items-center justify-center bg-background px-4 py-12">
        <Card class="w-full max-w-md">
            <CardHeader>
                <CardTitle>Join {{ invitation.organization_name }}</CardTitle>
                <CardDescription>
                    <span v-if="invitation.invited_by">{{ invitation.invited_by }} invited</span>
                    <span v-else>You were invited</span>
                    <strong> {{ invitation.email }} </strong>
                    to join as <Badge variant="secondary">{{ invitation.role }}</Badge>.
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-4 text-sm">
                <div v-if="invitation.status !== 'pending'" class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-destructive">
                    This invitation is no longer valid ({{ invitation.status }}).
                </div>

                <div v-else-if="!invitation.email_matches" class="rounded-md border border-yellow-500/50 bg-yellow-500/10 p-3 text-yellow-700 dark:text-yellow-300">
                    This invitation was sent to <strong>{{ invitation.email }}</strong>. Sign in
                    with that account to accept.
                </div>

                <p v-else class="text-muted-foreground">
                    This invitation expires on {{ formatDateTime(invitation.expires_at) }}.
                </p>
            </CardContent>

            <CardFooter
                v-if="invitation.status === 'pending' && invitation.email_matches"
                class="flex gap-2"
            >
                <Form
                    v-bind="DeclineInvitationController.form(token)"
                    class="inline"
                    v-slot="{ processing }"
                >
                    <Button type="submit" variant="ghost" :disabled="processing">
                        Decline
                    </Button>
                </Form>
                <Form
                    v-bind="AcceptInvitationController.form(token)"
                    class="inline flex-1"
                    v-slot="{ processing }"
                >
                    <Button type="submit" class="w-full" :disabled="processing">
                        Accept invitation
                    </Button>
                </Form>
            </CardFooter>
        </Card>
    </div>
</template>
