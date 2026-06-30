<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Gift } from '@lucide/vue';
import { show as wishlistShow } from '@/routes/wishlists';
import type { WishlistUserSummary } from '@/types';

defineProps<{
    users: WishlistUserSummary[];
}>();
</script>

<template>
    <Head title="Everyone's wishlists" />

    <div class="flex flex-col gap-6">
        <div
            class="relative overflow-hidden rounded-3xl border-2 border-border bg-card px-6 py-7 backdrop-blur"
        >
            <p
                class="text-xs font-semibold tracking-[0.2em] text-gold uppercase"
            >
                The whole group
            </p>
            <h1 class="mt-1 font-display text-3xl font-semibold sm:text-4xl">
                Whose list shall we peek at?
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Browse everyone's wishes and quietly claim the gifts you'll
                bring.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="user in users"
                :key="user.id"
                :href="wishlistShow(user.id)"
                class="group flex items-center justify-between rounded-2xl border-2 border-border bg-card p-4 transition hover:-translate-y-0.5 hover:border-holly/40 hover:shadow-md"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-11 items-center justify-center rounded-full bg-holly/10 text-holly ring-2 ring-gold/20"
                    >
                        <Gift class="size-5" />
                    </div>
                    <div>
                        <p class="font-display font-semibold">
                            {{ user.name }}
                            <span
                                v-if="user.is_me"
                                class="font-normal text-muted-foreground"
                            >
                                (you)
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ user.wishlist_items_count }}
                            {{
                                user.wishlist_items_count === 1
                                    ? 'wish'
                                    : 'wishes'
                            }}
                        </p>
                    </div>
                </div>
                <ChevronRight
                    class="size-5 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-holly"
                />
            </Link>
        </div>
    </div>
</template>
