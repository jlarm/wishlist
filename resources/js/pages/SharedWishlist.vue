<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Gift, TreePine } from '@lucide/vue';
import Snowfall from '@/components/Snowfall.vue';
import { Toaster } from '@/components/ui/sonner';
import WishlistItemCard from '@/components/WishlistItemCard.vue';
import type { WishlistItem } from '@/types';

defineProps<{
    owner: { name: string };
    items: WishlistItem[];
}>();
</script>

<template>
    <Head :title="`${owner.name}'s wishlist`" />

    <div class="candlelight relative flex min-h-svh flex-col">
        <Snowfall />

        <header
            class="border-b border-white/10 bg-[hsl(158_44%_8%)]/85 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center gap-2.5 px-4"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-full bg-holly text-white shadow-sm ring-2 ring-gold/50"
                >
                    <TreePine class="size-5" />
                </span>
                <span
                    class="font-display text-xl leading-none font-semibold tracking-tight text-white"
                >
                    Wishlist
                </span>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <div
                class="relative overflow-hidden rounded-3xl border-2 border-border bg-card px-6 py-7 backdrop-blur"
            >
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-gold uppercase"
                >
                    A shared wishlist
                </p>
                <h1
                    class="mt-1 font-display text-3xl font-semibold sm:text-4xl"
                >
                    {{ owner.name }}'s wishes
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ items.length }}
                    {{ items.length === 1 ? 'wish' : 'wishes' }} on the list
                </p>
            </div>

            <div
                v-if="items.length"
                class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <WishlistItemCard
                    v-for="item in items"
                    :key="item.id"
                    :item="item"
                />
            </div>

            <div
                v-else
                class="mt-6 flex flex-col items-center justify-center gap-3 rounded-3xl border-2 border-dashed border-border bg-card p-12 text-center"
            >
                <div class="rounded-full bg-holly/10 p-4 text-holly">
                    <Gift class="size-8" />
                </div>
                <p class="font-display text-lg font-semibold">No wishes yet</p>
                <p class="text-sm text-muted-foreground">
                    {{ owner.name }} hasn't added anything to their list.
                </p>
            </div>
        </main>

        <Toaster />
    </div>
</template>
