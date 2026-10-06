<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ClaimStageCounts from '@/components/ClaimStageCounts.vue';
import type { ClaimCounts } from '@/components/ClaimStageCounts.vue';
import SpendingAmount from '@/components/SpendingAmount.vue';
import { index as spendingIndex } from '@/routes/admin/spending';
import { show as wishlistShow } from '@/routes/wishlists';

type SpendingSummary = {
    requested_total: string;
    items_count: number;
    spent_total: string;
    spent_vs_original: string | null;
    reserved_total: string;
    awaiting_delivery_count: number;
    delivered_count: number;
};

type SpendingUser = {
    id: number;
    name: string;
    email: string;
    is_me: boolean;
    is_disabled: boolean;
    items_count: number;
    unpriced_count: number;
    requested_total: string;
    unclaimed_total: string | null;
    receiving: ClaimCounts | null;
    received_count: number;
    spent_total: string;
    spent_vs_original: string | null;
    reserved_total: string;
    giving: ClaimCounts;
};

const props = defineProps<{
    summary: SpendingSummary;
    users: SpendingUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Spending', href: spendingIndex() }],
    },
});

const currency = new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
});

function formatPrice(price: string): string {
    return currency.format(Number(price));
}

/**
 * "$12.00 under" / "$3.50 over" what the bought gifts cost when they were
 * added, or null when there's nothing to compare.
 */
function describeDifference(
    difference: string | null,
): { text: string; cheaper: boolean } | null {
    const amount = Number(difference ?? 0);

    if (amount === 0) {
        return null;
    }

    return {
        text: `${currency.format(Math.abs(amount))} ${amount < 0 ? 'under' : 'over'} original price`,
        cheaper: amount < 0,
    };
}

const rows = computed(() =>
    props.users.map((user) => ({
        ...user,
        spentDifference: describeDifference(user.spent_vs_original),
    })),
);

const summarySpentDifference = computed(() =>
    describeDifference(props.summary.spent_vs_original),
);

const summaryTiles = computed(() => [
    {
        label: 'Requested',
        value: formatPrice(props.summary.requested_total),
        detail: `${props.summary.items_count} items on lists`,
    },
    {
        label: 'Spent',
        value: formatPrice(props.summary.spent_total),
        detail: summarySpentDifference.value?.text ?? 'Bought or delivered',
    },
    {
        label: 'Reserved',
        value: formatPrice(props.summary.reserved_total),
        detail: 'Claimed, not bought yet',
    },
    {
        label: 'Awaiting delivery',
        value: String(props.summary.awaiting_delivery_count),
        detail: `${props.summary.delivered_count} delivered`,
    },
]);
</script>

<template>
    <Head title="Spending" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold">Spending</h1>
            <p class="text-sm text-muted-foreground">
                What everyone has asked for, what's been spent, and how their
                gifts are coming along. Totals use each item's current listed
                price. Claims on your own items are left out so your surprises
                stay intact.
            </p>
        </div>

        <!-- Totals -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div
                v-for="tile in summaryTiles"
                :key="tile.label"
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    {{ tile.label }}
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ tile.value }}
                </p>
                <p class="text-xs text-muted-foreground">{{ tile.detail }}</p>
            </div>
        </div>

        <!-- One row per member -->
        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Member</th>
                        <th class="px-4 py-3 text-right font-medium">Items</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Requested
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            Still unclaimed
                        </th>
                        <th class="px-4 py-3 font-medium">Gifts for them</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Received
                        </th>
                        <th class="px-4 py-3 text-right font-medium">Spent</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Reserved
                        </th>
                        <th class="px-4 py-3 font-medium">
                            Gifts they're giving
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in rows"
                        :key="user.id"
                        class="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/40 dark:border-sidebar-border"
                        @click="router.visit(wishlistShow(user.id))"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium whitespace-nowrap">
                                <Link
                                    :href="wishlistShow(user.id)"
                                    class="hover:underline"
                                    @click.stop
                                    >{{ user.name }}</Link
                                >
                                <span
                                    v-if="user.is_me"
                                    class="text-muted-foreground"
                                    >(you)</span
                                >
                                <span
                                    v-if="user.is_disabled"
                                    class="text-xs text-muted-foreground"
                                    >· disabled</span
                                >
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ user.email }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span
                                :class="{
                                    'text-muted-foreground': !user.items_count,
                                }"
                                >{{ user.items_count || '—' }}</span
                            >
                            <div
                                v-if="user.unpriced_count"
                                class="text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ user.unpriced_count }} unpriced
                            </div>
                        </td>
                        <td
                            class="px-4 py-3 text-right font-medium tabular-nums"
                        >
                            <SpendingAmount :amount="user.requested_total" />
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span
                                v-if="user.unclaimed_total === null"
                                class="text-muted-foreground"
                                >Hidden</span
                            >
                            <SpendingAmount
                                v-else
                                :amount="user.unclaimed_total"
                            />
                        </td>
                        <td class="px-4 py-3">
                            <span
                                v-if="user.receiving === null"
                                class="text-muted-foreground"
                                >Hidden</span
                            >
                            <ClaimStageCounts v-else :counts="user.receiving" />
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span class="text-muted-foreground">{{
                                user.received_count || '—'
                            }}</span>
                        </td>
                        <td
                            class="px-4 py-3 text-right font-medium tabular-nums"
                        >
                            <SpendingAmount :amount="user.spent_total" />
                            <div
                                v-if="user.spentDifference"
                                class="text-xs font-normal whitespace-nowrap"
                                :class="
                                    user.spentDifference.cheaper
                                        ? 'text-holly'
                                        : 'text-cranberry'
                                "
                            >
                                {{ user.spentDifference.text }}
                            </div>
                        </td>
                        <td
                            class="px-4 py-3 text-right text-muted-foreground tabular-nums"
                        >
                            <SpendingAmount :amount="user.reserved_total" />
                        </td>
                        <td class="px-4 py-3">
                            <ClaimStageCounts :counts="user.giving" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
