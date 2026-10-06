<script setup lang="ts">
import WishlistItemActions from '@/components/WishlistItemActions.vue';
import type { WishlistItem } from '@/types';

defineProps<{
    items: WishlistItem[];
    showOwner?: boolean;
}>();

function formatCurrency(value: string | null): string | null {
    if (value === null) {
        return null;
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));
}

function priorityDotClass(priority: string): string {
    switch (priority) {
        case 'most_wanted':
            return 'bg-gold';
        case 'high':
            return 'bg-cranberry';
        case 'low':
            return 'bg-muted-foreground/40';
        default:
            return 'bg-holly';
    }
}

function claimLabel(item: WishlistItem): string {
    switch (item.purchase?.status) {
        case 'delivered':
            return 'Delivered';
        case 'purchased':
            return 'Bought';
        default:
            return 'Reserved';
    }
}

// Tooltip attribution for reserved/bought. A delivered item just reads
// "Delivered" — the claimer's name adds nothing there.
function claimTooltip(item: WishlistItem): string {
    const name = item.purchase?.purchased_by_name;

    if (!name || item.purchase?.status === 'delivered') {
        return claimLabel(item);
    }

    return `${claimLabel(item)} by ${name}`;
}
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border-2 border-border bg-card">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead
                class="border-b border-border text-xs tracking-wide text-muted-foreground uppercase"
            >
                <tr>
                    <th class="px-4 py-3 font-medium">Item</th>
                    <th class="px-4 py-3 font-medium">Priority</th>
                    <th class="px-4 py-3 font-medium">Price</th>
                    <th class="px-4 py-3 font-medium">Tags</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr
                    v-for="item in items"
                    :key="item.id"
                    class="align-middle transition hover:bg-muted/40"
                >
                    <!-- Item: thumbnail + title -->
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted"
                            >
                                <img
                                    :src="item.image_url ?? '/tree.webp'"
                                    :alt="item.title"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ item.title }}
                                </p>
                                <p
                                    v-if="
                                        item.availability === 'out_of_stock' ||
                                        item.availability === 'unavailable'
                                    "
                                    class="truncate text-xs font-semibold text-cranberry"
                                >
                                    {{ item.availability_label }}
                                </p>
                                <p
                                    v-if="showOwner && item.owner_name"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    For {{ item.owner_name }}
                                </p>
                                <p
                                    v-else-if="item.size || item.color"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        [item.size, item.color]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <!-- Priority -->
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5">
                            <span
                                class="size-1.5 rounded-full"
                                :class="priorityDotClass(item.priority)"
                            />
                            {{ item.priority_label }}
                        </span>
                    </td>

                    <!-- Price -->
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span v-if="formatCurrency(item.price)">
                            {{ formatCurrency(item.price) }}
                        </span>
                        <span v-else class="text-muted-foreground">—</span>
                    </td>

                    <!-- Tags -->
                    <td class="px-4 py-3">
                        <div
                            v-if="item.tags.length"
                            class="flex flex-wrap gap-1"
                        >
                            <span
                                v-for="tag in item.tags"
                                :key="tag"
                                class="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                            >
                                {{ tag }}
                            </span>
                        </div>
                        <span v-else class="text-muted-foreground">—</span>
                    </td>

                    <!-- Status -->
                    <td class="px-4 py-3">
                        <span
                            v-if="!item.is_owner && item.is_purchased"
                            class="inline-flex items-center rounded-full border border-cranberry/30 bg-cranberry/10 px-2 py-0.5 text-xs font-semibold text-cranberry"
                            :title="claimTooltip(item)"
                        >
                            {{ claimLabel(item) }}
                        </span>
                        <span
                            v-else-if="
                                item.is_owner &&
                                item.visibility_status === 'hidden'
                            "
                            class="text-xs text-muted-foreground"
                        >
                            Hidden
                        </span>
                        <!-- Only admins get claim data, so only they see "Available" -->
                        <span
                            v-else-if="item.is_purchased === false"
                            class="text-xs text-muted-foreground"
                        >
                            Available
                        </span>
                        <span v-else class="text-muted-foreground">—</span>
                    </td>

                    <!-- Actions -->
                    <td class="px-4 py-3">
                        <WishlistItemActions :item="item" class="justify-end" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
