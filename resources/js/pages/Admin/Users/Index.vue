<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ShieldOff, ShieldCheck, UserCheck, UserX } from '@lucide/vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as usersIndex, update } from '@/routes/admin/users';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    is_disabled: boolean;
    is_me: boolean;
    wishlist_items_count: number;
    created_at: string | null;
};

defineProps<{
    users: AdminUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: usersIndex() }],
    },
});

function setDisabled(id: number, isDisabled: boolean) {
    router.patch(
        update(id).url,
        { is_disabled: isDisabled },
        { preserveScroll: true },
    );
}

function setAdmin(id: number, isAdmin: boolean) {
    router.patch(
        update(id).url,
        { is_admin: isAdmin },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Users" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold">Users</h1>
            <p class="text-sm text-muted-foreground">
                Manage members, roles, and access.
            </p>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Items</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ user.name }}
                            <span
                                v-if="user.is_me"
                                class="text-muted-foreground"
                                >(you)</span
                            >
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ user.email }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="user.is_admin ? 'default' : 'outline'"
                            >
                                {{ user.is_admin ? 'Admin' : 'Member' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    user.is_disabled
                                        ? 'destructive'
                                        : 'secondary'
                                "
                            >
                                {{ user.is_disabled ? 'Disabled' : 'Active' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ user.wishlist_items_count }}
                        </td>
                        <td class="px-4 py-3">
                            <div
                                v-if="!user.is_me"
                                class="flex justify-end gap-1"
                            >
                                <Button
                                    v-if="user.is_admin"
                                    variant="ghost"
                                    size="sm"
                                    @click="setAdmin(user.id, false)"
                                >
                                    <ShieldOff class="size-4" />
                                    Revoke admin
                                </Button>
                                <Button
                                    v-else
                                    variant="ghost"
                                    size="sm"
                                    @click="setAdmin(user.id, true)"
                                >
                                    <ShieldCheck class="size-4" />
                                    Make admin
                                </Button>

                                <Button
                                    v-if="user.is_disabled"
                                    variant="ghost"
                                    size="sm"
                                    @click="setDisabled(user.id, false)"
                                >
                                    <UserCheck class="size-4" />
                                    Enable
                                </Button>
                                <ConfirmDialog
                                    v-else
                                    title="Disable this user?"
                                    :description="`${user.name} will be logged out and unable to sign in.`"
                                    confirm-label="Disable"
                                    confirm-variant="destructive"
                                    @confirm="setDisabled(user.id, true)"
                                >
                                    <template #trigger>
                                        <Button variant="ghost" size="sm">
                                            <UserX class="size-4" />
                                            Disable
                                        </Button>
                                    </template>
                                </ConfirmDialog>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
