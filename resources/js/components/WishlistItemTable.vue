<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ClaimedItemController from '@/actions/App/Http/Controllers/Admin/ClaimedItemController';
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
import WishlistItemActions from '@/components/WishlistItemActions.vue';
import type { WishlistItem } from '@/types';

defineProps<{
    items: WishlistItem[];
    showOwner?: boolean;
    // Spell out who claimed each item under its status, not just on hover.
    showClaimer?: boolean;
    // Admin view: switch a claim's status right from its row.
    editableStatus?: boolean;
}>();

const statusSelectClass: Record<string, string> = {
    reserved: 'border-gold/40 bg-gold/10 text-gold',
    purchased: 'border-holly/40 bg-holly/10 text-holly',
    delivered: 'border-cranberry/30 bg-cranberry/10 text-cranberry',
};

type ClaimStatus = 'reserved' | 'purchased' | 'delivered';

// A status change waiting on the price prompt.
const pendingChange = ref<{ item: WishlistItem; status: ClaimStatus } | null>(
    null,
);
const pricePaid = ref<string | number>('');

function saveStatus(
    item: WishlistItem,
    status: ClaimStatus,
    price: string | null = null,
) {
    router.patch(
        ClaimedItemController.update(item.id).url,
        { status, price_paid: price },
        { preserveScroll: true, preserveState: true },
    );
}

// Moving to bought (or straight from reserved to delivered) means it was just
// bought, so ask what it cost first. Other changes save immediately.
function setStatus(item: WishlistItem, event: Event) {
    const select = event.target as HTMLSelectElement;
    const status = select.value as ClaimStatus;
    const wasReserved = item.purchase?.status === 'reserved';

    if (status === 'purchased' || (status === 'delivered' && wasReserved)) {
        // Show the current status until the prompt is answered.
        select.value = item.purchase?.status ?? 'reserved';
        pricePaid.value = '';
        pendingChange.value = { item, status };

        return;
    }

    saveStatus(item, status);
}

function confirmPendingChange() {
    if (!pendingChange.value) {
        return;
    }

    saveStatus(
        pendingChange.value.item,
        pendingChange.value.status,
        pricePaid.value === '' ? null : String(pricePaid.value),
    );
    pendingChange.value = null;
}

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
                        <!-- Capped so long titles truncate instead of widening the table -->
                        <div
                            class="flex max-w-xs items-center gap-3 lg:max-w-sm"
                        >
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
                                <p
                                    class="truncate font-medium"
                                    :title="item.title"
                                >
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
                        <select
                            v-if="editableStatus && item.purchase"
                            :value="item.purchase.status"
                            class="h-7 rounded-full border px-2 text-xs font-semibold outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            :class="statusSelectClass[item.purchase.status]"
                            aria-label="Change status"
                            @change="setStatus(item, $event)"
                        >
                            <option value="reserved">Reserved</option>
                            <option value="purchased">Bought</option>
                            <option value="delivered">Delivered</option>
                        </select>
                        <span
                            v-else-if="!item.is_owner && item.is_purchased"
                            class="inline-flex items-center rounded-full border border-cranberry/30 bg-cranberry/10 px-2 py-0.5 text-xs font-semibold text-cranberry"
                            :title="claimTooltip(item)"
                        >
                            {{ claimLabel(item) }}
                        </span>
                        <p
                            v-if="
                                showClaimer &&
                                item.is_purchased &&
                                item.purchase?.purchased_by_name
                            "
                            class="mt-1 text-xs whitespace-nowrap text-muted-foreground"
                        >
                            by {{ item.purchase.purchased_by_name }}
                        </p>
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
                        <WishlistItemActions
                            :item="item"
                            :hide-claim-controls="editableStatus"
                            class="justify-end"
                        />
                    </td>
                </tr>
            </tbody>
        </table>

        <Dialog
            :open="pendingChange !== null"
            @update:open="(open) => !open && (pendingChange = null)"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mark as bought?</DialogTitle>
                    <DialogDescription>
                        Record what “{{ pendingChange?.item.title }}” cost.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="status-price-paid">
                        Price paid (optional)
                    </Label>
                    <Input
                        id="status-price-paid"
                        v-model="pricePaid"
                        type="number"
                        step="0.01"
                        min="0"
                        inputmode="decimal"
                        :placeholder="pendingChange?.item.price ?? '0.00'"
                    />
                    <p class="text-xs text-muted-foreground">
                        Leave blank to keep any price already recorded, or use
                        the listed price.
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="pendingChange = null">
                        Cancel
                    </Button>
                    <Button @click="confirmPendingChange">
                        {{
                            pendingChange?.status === 'delivered'
                                ? 'Mark as delivered'
                                : 'Mark as bought'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
