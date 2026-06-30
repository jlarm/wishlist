<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Mail, RefreshCw, Send, X } from '@lucide/vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy,
    index as invitationsIndex,
    resend,
    store,
} from '@/routes/admin/invitations';

type Invitation = {
    id: number;
    email: string;
    status: string;
    status_label: string;
    is_expired: boolean;
    invited_by: string | null;
    accepted_by: string | null;
    expires_at: string | null;
    created_at: string | null;
    can: { resend: boolean; revoke: boolean };
};

defineProps<{
    invitations: Invitation[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Invitations', href: invitationsIndex() }],
    },
});

const form = useForm({ email: '' });

function submit() {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
}

function resendInvite(id: number) {
    router.post(resend(id).url, {}, { preserveScroll: true });
}

function revokeInvite(id: number) {
    router.delete(destroy(id).url, { preserveScroll: true });
}

function badgeVariant(status: string) {
    switch (status) {
        case 'accepted':
            return 'default';
        case 'revoked':
        case 'expired':
            return 'destructive';
        default:
            return 'secondary';
    }
}

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleDateString() : '—';
}
</script>

<template>
    <Head title="Invitations" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold">Invitations</h1>
            <p class="text-sm text-muted-foreground">
                Invite people by email. Registration is invite-only.
            </p>
        </div>

        <!-- Invite form -->
        <form
            class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:flex-row sm:items-start dark:border-sidebar-border"
            @submit.prevent="submit"
        >
            <div class="flex-1">
                <div class="relative">
                    <Mail
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="form.email"
                        type="email"
                        required
                        placeholder="person@example.com"
                        class="pl-9"
                    />
                </div>
                <InputError class="mt-1" :message="form.errors.email" />
            </div>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                <Send v-else class="size-4" />
                Send invite
            </Button>
        </form>

        <!-- Invitations list -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Invited by</th>
                        <th class="px-4 py-3 font-medium">Expires</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="invitation in invitations"
                        :key="invitation.id"
                        class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ invitation.email }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="badgeVariant(invitation.status)"
                                class="capitalize"
                            >
                                {{
                                    invitation.is_expired &&
                                    invitation.status === 'pending'
                                        ? 'Expired'
                                        : invitation.status_label
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ invitation.invited_by ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDate(invitation.expires_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <Button
                                    v-if="invitation.can.resend"
                                    variant="ghost"
                                    size="sm"
                                    @click="resendInvite(invitation.id)"
                                >
                                    <RefreshCw class="size-4" />
                                    Resend
                                </Button>
                                <ConfirmDialog
                                    v-if="invitation.can.revoke"
                                    title="Revoke invitation?"
                                    :description="`The invite for ${invitation.email} will no longer be usable.`"
                                    confirm-label="Revoke"
                                    confirm-variant="destructive"
                                    @confirm="revokeInvite(invitation.id)"
                                >
                                    <template #trigger>
                                        <Button variant="ghost" size="sm">
                                            <X class="size-4" />
                                            Revoke
                                        </Button>
                                    </template>
                                </ConfirmDialog>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!invitations.length">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No invitations yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
