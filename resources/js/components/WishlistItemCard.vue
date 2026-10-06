<script setup lang="ts">
import { Check, Tag, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import PriceHistoryChart from '@/components/PriceHistoryChart.vue';
import WishlistItemActions from '@/components/WishlistItemActions.vue';
import type { WishlistItem } from '@/types';

const props = defineProps<{
    item: WishlistItem;
    showOwner?: boolean;
}>();

const imageFailed = ref(false);

// Fall back to the tree illustration when an item has no image or the image
// fails to load.
const displayImage = computed(() =>
    props.item.image_url && !imageFailed.value
        ? props.item.image_url
        : '/tree.webp',
);

const ornamentClass = computed(() => {
    switch (props.item.priority) {
        case 'most_wanted':
            return 'bg-gold';
        case 'high':
            return 'bg-cranberry';
        case 'low':
            return 'bg-muted-foreground/40';
        default:
            return 'bg-holly';
    }
});

const formattedPrice = computed(() => {
    if (props.item.price === null) {
        return null;
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(props.item.price));
});

const priceHistory = computed(() => props.item.price_history ?? []);

const hasPriceHistory = computed(() => priceHistory.value.length >= 2);

/**
 * The change from the first recorded price to the latest, so we can flag drops
 * (good news) and rises. Null when there isn't enough history to compare.
 */
const priceTrend = computed(() => {
    if (!hasPriceHistory.value) {
        return null;
    }

    const first = Number(priceHistory.value[0].price);
    const latest = Number(
        priceHistory.value[priceHistory.value.length - 1].price,
    );
    const delta = latest - first;

    if (delta === 0) {
        return null;
    }

    return {
        dropped: delta < 0,
        amount: new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: 'USD',
        }).format(Math.abs(delta)),
    };
});

/**
 * What the viewer paid, compared with the price when the item was added. Only
 * the giver gets price_paid, so this never shows for anyone else.
 */
const pricePaid = computed(() => {
    const paid = props.item.purchase?.price_paid;

    if (!paid) {
        return null;
    }

    const original = props.item.purchase?.original_price;
    const delta = original ? Number(paid) - Number(original) : 0;

    return {
        amount: formatCurrency(paid),
        cheaper: delta < 0,
        difference: delta === 0 ? null : formatCurrency(Math.abs(delta)),
    };
});

function formatCurrency(value: string | number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));
}

const purchasedDate = computed(() => {
    const at = props.item.purchase?.purchased_at;

    return at ? new Date(at).toLocaleDateString() : null;
});

const claimStatusLabel = computed(() => {
    switch (props.item.purchase?.status) {
        case 'delivered':
            return 'Delivered';
        case 'purchased':
            return 'Bought';
        default:
            return 'Reserved';
    }
});

// Attribute the claimer for reserved/bought ("by name"). A delivered item just
// reads "Delivered" — the name/"(you)" is dropped as it adds nothing there.
const claimByline = computed(() => {
    const name = props.item.purchase?.purchased_by_name;

    if (!name || props.item.purchase?.status === 'delivered') {
        return '';
    }

    return ` by ${name}`;
});
</script>

<template>
    <div
        class="group relative flex flex-col rounded-2xl border-2 border-border bg-card p-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
    >
        <!-- Image + title: a thumbnail beside the title on phones, a full-width banner from sm up -->
        <div
            class="flex items-start gap-3 sm:flex-col sm:items-stretch sm:gap-0"
        >
            <div
                class="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted sm:aspect-video sm:size-auto sm:w-full"
            >
                <img
                    :src="displayImage"
                    :alt="item.title"
                    class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                    @error="imageFailed = true"
                />

                <span
                    v-if="item.visibility_status === 'hidden'"
                    class="absolute top-1 right-1 rounded-full bg-background/85 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground backdrop-blur sm:top-2 sm:right-2 sm:px-2 sm:text-xs"
                >
                    Hidden
                </span>

                <!-- Nightly link check found a problem -->
                <span
                    v-if="
                        item.availability === 'out_of_stock' ||
                        item.availability === 'unavailable'
                    "
                    class="absolute bottom-1 left-1 inline-flex items-center gap-1 rounded-full bg-background/90 px-1.5 py-0.5 text-xs font-semibold text-cranberry backdrop-blur sm:bottom-2 sm:left-2 sm:px-2"
                    :title="item.availability_label ?? undefined"
                >
                    <TriangleAlert class="size-3" />
                    <span class="sr-only sm:not-sr-only">
                        {{ item.availability_label }}
                    </span>
                </span>
            </div>

            <div
                class="flex min-w-0 flex-1 items-start justify-between gap-2 sm:px-1 sm:pt-3"
            >
                <div class="min-w-0">
                    <h3
                        class="line-clamp-2 font-display text-lg leading-tight font-semibold sm:truncate"
                    >
                        {{ item.title }}
                    </h3>
                    <p
                        v-if="showOwner && item.owner_name"
                        class="text-xs text-muted-foreground"
                    >
                        For {{ item.owner_name }}
                    </p>
                </div>
                <span
                    v-if="formattedPrice"
                    class="shrink-0 rounded-full bg-gold/15 px-2.5 py-1 text-sm font-semibold whitespace-nowrap text-gold"
                >
                    {{ formattedPrice }}
                </span>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 px-1 pt-3">
            <!-- Priority + size + color, as little kraft tags -->
            <div class="flex flex-wrap gap-1.5 text-xs">
                <span
                    class="inline-flex items-center gap-1 rounded-full border border-border bg-secondary/60 px-2 py-0.5 font-medium"
                >
                    <span
                        class="size-1.5 rounded-full"
                        :class="ornamentClass"
                    />
                    {{ item.priority_label }}
                </span>
                <span
                    class="rounded-full bg-muted px-2 py-0.5"
                    :class="item.size ? '' : 'text-muted-foreground italic'"
                >
                    Size: {{ item.size ?? 'Any' }}
                </span>
                <span
                    class="rounded-full bg-muted px-2 py-0.5"
                    :class="item.color ? '' : 'text-muted-foreground italic'"
                >
                    Color: {{ item.color ?? 'Any' }}
                </span>
            </div>

            <!-- Free-form tags -->
            <div v-if="item.tags.length" class="flex flex-wrap gap-1.5">
                <span
                    v-for="tag in item.tags"
                    :key="tag"
                    class="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                >
                    <Tag class="size-3 text-muted-foreground" />
                    {{ tag }}
                </span>
            </div>

            <!-- Nightly price history -->
            <div
                v-if="hasPriceHistory"
                class="rounded-xl bg-muted/50 px-3 py-2"
            >
                <div
                    class="flex items-center justify-between text-xs font-medium text-muted-foreground"
                >
                    <span>Price history</span>
                    <span
                        v-if="priceTrend"
                        class="font-semibold"
                        :class="
                            priceTrend.dropped ? 'text-holly' : 'text-cranberry'
                        "
                    >
                        {{ priceTrend.dropped ? '▼' : '▲' }}
                        {{ priceTrend.amount }}
                    </span>
                </div>
                <PriceHistoryChart :history="priceHistory" class="mt-1" />
            </div>

            <p v-if="item.description" class="text-sm text-muted-foreground">
                {{ item.description }}
            </p>

            <p v-if="item.notes" class="text-sm">
                <span class="font-medium">Notes:</span> {{ item.notes }}
            </p>

            <!-- Claimed ribbon — only ever rendered for non-owners -->
            <div
                v-if="!item.is_owner && item.is_purchased"
                class="rounded-lg border border-cranberry/30 bg-cranberry/10 px-3 py-2"
            >
                <div
                    class="flex items-start gap-1.5 text-sm font-semibold text-cranberry"
                >
                    <Check class="mt-0.5 size-4 shrink-0" />
                    <p>
                        {{ claimStatusLabel }}{{ claimByline
                        }}<span
                            v-if="
                                item.purchase?.purchased_by_me &&
                                item.purchase?.status !== 'delivered'
                            "
                            class="font-normal"
                            >&nbsp;(you)</span
                        >
                    </p>
                </div>
                <p
                    v-if="item.purchase?.status === 'reserved'"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    Not bought yet — still a soft hold.
                </p>
                <p
                    v-if="pricePaid"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    You paid {{ pricePaid.amount
                    }}<span
                        v-if="pricePaid.difference"
                        class="font-semibold"
                        :class="
                            pricePaid.cheaper ? 'text-holly' : 'text-cranberry'
                        "
                    >
                        · {{ pricePaid.difference }}
                        {{ pricePaid.cheaper ? 'less' : 'more' }} than when
                        added</span
                    >
                </p>
                <p
                    v-if="purchasedDate"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    Marked on {{ purchasedDate }}
                </p>
                <p
                    v-if="item.purchase?.note"
                    class="mt-0.5 text-xs text-muted-foreground italic"
                >
                    “{{ item.purchase.note }}”
                </p>
            </div>

            <WishlistItemActions :item="item" block class="mt-auto pt-2" />
        </div>
    </div>
</template>
