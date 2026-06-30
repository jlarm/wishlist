<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Gift, Plus, ShieldCheck, Users } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import WishlistItemCard from '@/components/WishlistItemCard.vue';
import { dashboard } from '@/routes';
import { index as invitationsIndex } from '@/routes/admin/invitations';
import { create as createItem } from '@/routes/wishlist-items';
import {
    index as wishlistsIndex,
    show as wishlistShow,
} from '@/routes/wishlists';
import type { User, WishlistItem, WishlistUserSummary } from '@/types';

defineProps<{
    users: WishlistUserSummary[];
    myItemsCount: number;
    recentItems: WishlistItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage<{ auth: { user: User } }>();
const currentUser = computed(() => page.props.auth.user);
const myWishlist = computed(() => wishlistShow(currentUser.value.id as number));
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">
                    Welcome back, {{ currentUser?.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Here's what's happening across your group's wishlists.
                </p>
            </div>
            <Button as-child>
                <Link :href="createItem()">
                    <Plus class="size-4" />
                    Add wishlist item
                </Link>
            </Button>
        </div>

        <!-- Summary tiles -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                :href="myWishlist"
                class="flex items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-4 transition hover:bg-accent dark:border-sidebar-border"
            >
                <div class="rounded-lg bg-primary/10 p-3 text-primary">
                    <Gift class="size-6" />
                </div>
                <div>
                    <p class="text-2xl font-semibold">{{ myItemsCount }}</p>
                    <p class="text-sm text-muted-foreground">
                        Items on your wishlist
                    </p>
                </div>
            </Link>

            <Link
                :href="wishlistsIndex()"
                class="flex items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-4 transition hover:bg-accent dark:border-sidebar-border"
            >
                <div class="rounded-lg bg-primary/10 p-3 text-primary">
                    <Users class="size-6" />
                </div>
                <div>
                    <p class="text-2xl font-semibold">{{ users.length }}</p>
                    <p class="text-sm text-muted-foreground">
                        People in your group
                    </p>
                </div>
            </Link>

            <Link
                v-if="currentUser?.is_admin"
                :href="invitationsIndex()"
                class="flex items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-4 transition hover:bg-accent dark:border-sidebar-border"
            >
                <div class="rounded-lg bg-primary/10 p-3 text-primary">
                    <ShieldCheck class="size-6" />
                </div>
                <div>
                    <p class="text-lg font-semibold">Manage invites</p>
                    <p class="text-sm text-muted-foreground">
                        Invite & manage members
                    </p>
                </div>
            </Link>
        </div>

        <!-- People -->
        <div>
            <h2 class="mb-3 text-lg font-semibold">Everyone's wishlists</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="user in users"
                    :key="user.id"
                    :href="wishlistShow(user.id)"
                    class="flex items-center justify-between rounded-lg border border-sidebar-border/70 bg-card p-3 transition hover:bg-accent dark:border-sidebar-border"
                >
                    <span class="font-medium">
                        {{ user.name }}
                        <span v-if="user.is_me" class="text-muted-foreground"
                            >(you)</span
                        >
                    </span>
                    <span class="text-sm text-muted-foreground">
                        {{ user.wishlist_items_count }} items
                    </span>
                </Link>
            </div>
        </div>

        <!-- Recent items from others -->
        <div>
            <h2 class="mb-3 text-lg font-semibold">Recently added by others</h2>
            <div
                v-if="recentItems.length"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <WishlistItemCard
                    v-for="item in recentItems"
                    :key="item.id"
                    :item="item"
                    show-owner
                />
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No items from other people yet.
            </p>
        </div>
    </div>
</template>
