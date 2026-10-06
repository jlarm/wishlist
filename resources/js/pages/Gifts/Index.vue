<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Clock, Gift, ShoppingBag } from '@lucide/vue';
import { computed } from 'vue';
import WishlistItemActions from '@/components/WishlistItemActions.vue';
import {
    index as wishlistsIndex,
    show as wishlistShow,
} from '@/routes/wishlists';
import type { WishlistItem } from '@/types';

const props = defineProps<{
    items: WishlistItem[];
}>();

const currency = new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
});

function formatPrice(price: string | null): string | null {
    return price === null ? null : currency.format(Number(price));
}

// Claims grouped by the person they're for, in the order the server sent.
const groups = computed(() => {
    const byOwner = new Map<
        number,
        { ownerId: number; ownerName: string; items: WishlistItem[] }
    >();

    props.items.forEach((item) => {
        const group = byOwner.get(item.user_id) ?? {
            ownerId: item.user_id,
            ownerName: item.owner_name ?? 'Someone',
            items: [],
        };
        group.items.push(item);
        byOwner.set(item.user_id, group);
    });

    return [...byOwner.values()];
});

// Prefer what was actually paid, falling back to the listed price.
function sumPrices(items: WishlistItem[]): number {
    return items.reduce(
        (total, item) =>
            total + Number(item.purchase?.price_paid ?? item.price ?? 0),
        0,
    );
}

const reserved = computed(() =>
    props.items.filter((item) => item.purchase?.status === 'reserved'),
);
const bought = computed(() =>
    props.items.filter((item) => item.purchase?.status !== 'reserved'),
);
const needsAttention = computed(() =>
    props.items.filter((item) => item.purchase?.needs_confirmation),
);

const statusStyles: Record<string, string> = {
    reserved: 'border-gold/40 bg-gold/10 text-gold',
    purchased: 'border-holly/40 bg-holly/10 text-holly',
    delivered: 'border-border bg-muted text-muted-foreground',
};

const statusLabels: Record<string, string> = {
    reserved: 'Reserved',
    purchased: 'Bought',
    delivered: 'Delivered',
};
</script>

<template>
    <Head title="My gifts" />

    <div class="flex flex-col gap-6">
        <!-- Hero with totals -->
        <div
            class="rounded-3xl border-2 border-border bg-card px-6 py-7 backdrop-blur"
        >
            <p
                class="text-xs font-semibold tracking-[0.2em] text-gold uppercase"
            >
                Your shopping list
            </p>
            <h1 class="mt-1 font-display text-3xl font-semibold sm:text-4xl">
                My gifts
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Everything you've claimed for other people. Only you can see
                this.
            </p>

            <div
                v-if="items.length"
                class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4"
            >
                <div class="rounded-xl bg-muted/60 px-4 py-3">
                    <p class="text-xs text-muted-foreground">Gifts</p>
                    <p class="font-display text-2xl font-semibold">
                        {{ items.length }}
                    </p>
                </div>
                <div class="rounded-xl bg-muted/60 px-4 py-3">
                    <p class="text-xs text-muted-foreground">Still to buy</p>
                    <p class="font-display text-2xl font-semibold">
                        {{ reserved.length }}
                    </p>
                </div>
                <div class="rounded-xl bg-muted/60 px-4 py-3">
                    <p class="text-xs text-muted-foreground">Spent</p>
                    <p class="font-display text-2xl font-semibold">
                        {{ currency.format(sumPrices(bought)) }}
                    </p>
                </div>
                <div class="rounded-xl bg-muted/60 px-4 py-3">
                    <p class="text-xs text-muted-foreground">Planned</p>
                    <p class="font-display text-2xl font-semibold">
                        {{ currency.format(sumPrices(reserved)) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Stale reservation nudge -->
        <div
            v-if="needsAttention.length"
            class="flex items-start gap-3 rounded-2xl border-2 border-gold/40 bg-gold/10 p-4 text-sm"
        >
            <Clock class="mt-0.5 size-4 shrink-0 text-gold" />
            <p>
                {{ needsAttention.length }}
                {{
                    needsAttention.length === 1
                        ? 'reservation has'
                        : 'reservations have'
                }}
                been sitting a while. Tap <strong>Keep it</strong>, mark it
                bought, or release it — unanswered reservations are released
                automatically.
            </p>
        </div>

        <!-- Grouped by person -->
        <section
            v-for="group in groups"
            :key="group.ownerId"
            class="rounded-3xl border-2 border-border bg-card p-4 sm:p-5"
        >
            <div class="mb-3 flex items-center justify-between gap-2">
                <Link
                    :href="wishlistShow(group.ownerId)"
                    class="group inline-flex items-center gap-1 font-display text-xl font-semibold hover:text-holly"
                >
                    For {{ group.ownerName }}
                    <ChevronRight
                        class="size-4 text-muted-foreground transition group-hover:translate-x-0.5"
                    />
                </Link>
                <span class="text-sm text-muted-foreground">
                    {{ currency.format(sumPrices(group.items)) }}
                </span>
            </div>

            <ul class="divide-y divide-border">
                <li
                    v-for="item in group.items"
                    :key="item.id"
                    class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <img
                            :src="item.image_url ?? '/tree.webp'"
                            :alt="item.title"
                            class="size-12 shrink-0 rounded-lg bg-muted object-cover"
                        />
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ item.title }}
                            </p>
                            <div
                                class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs"
                            >
                                <span
                                    v-if="item.purchase"
                                    class="rounded-full border px-2 py-0.5 font-semibold"
                                    :class="statusStyles[item.purchase.status]"
                                >
                                    {{ statusLabels[item.purchase.status] }}
                                </span>
                                <span
                                    v-if="formatPrice(item.price)"
                                    class="text-muted-foreground"
                                >
                                    {{ formatPrice(item.price) }}
                                </span>
                                <span
                                    v-if="[item.size, item.color].some(Boolean)"
                                    class="text-muted-foreground"
                                >
                                    ·
                                    {{
                                        [item.size, item.color]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </span>
                                <span
                                    v-if="item.is_received"
                                    class="font-semibold text-holly"
                                >
                                    · {{ group.ownerName }} has received it
                                </span>
                                <span
                                    v-else-if="
                                        item.availability === 'out_of_stock' ||
                                        item.availability === 'unavailable'
                                    "
                                    class="font-semibold text-cranberry"
                                >
                                    · {{ item.availability_label }}
                                </span>
                                <span
                                    v-if="item.purchase?.needs_confirmation"
                                    class="font-semibold text-gold"
                                >
                                    · Still getting this?
                                </span>
                            </div>
                        </div>
                    </div>
                    <WishlistItemActions
                        :item="item"
                        class="shrink-0 sm:justify-end"
                    />
                </li>
            </ul>
        </section>

        <!-- Empty state -->
        <div
            v-if="!items.length"
            class="flex flex-col items-center justify-center gap-3 rounded-3xl border-2 border-dashed border-border bg-card p-12 text-center"
        >
            <div class="rounded-full bg-holly/10 p-4 text-holly">
                <ShoppingBag class="size-8" />
            </div>
            <p class="font-display text-lg font-semibold">
                Nothing claimed yet
            </p>
            <p class="text-sm text-muted-foreground">
                Reserve a gift on someone's list and it'll show up here.
            </p>
            <Link
                :href="wishlistsIndex()"
                class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-holly hover:underline"
            >
                <Gift class="size-4" />
                Browse everyone's lists
            </Link>
        </div>
    </div>
</template>
