<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WishlistItemTable from '@/components/WishlistItemTable.vue';
import { index as claimedItemsIndex } from '@/routes/admin/claimed-items';
import type { WishlistItem } from '@/types';

const props = defineProps<{
    items: WishlistItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Claimed items', href: claimedItemsIndex() }],
    },
});

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px]';

const ownerFilter = ref<'all' | number>('all');
const statusFilter = ref<'all' | 'reserved' | 'purchased' | 'delivered'>('all');

// Everyone who has at least one claimed item, for the "For" filter.
const owners = computed(() => {
    const byId = new Map<number, string>();

    props.items.forEach((item) =>
        byId.set(item.user_id, item.owner_name ?? 'Someone'),
    );

    return [...byId].map(([id, name]) => ({ id, name }));
});

const filteredItems = computed(() =>
    props.items.filter(
        (item) =>
            (ownerFilter.value === 'all' ||
                item.user_id === ownerFilter.value) &&
            (statusFilter.value === 'all' ||
                item.purchase?.status === statusFilter.value),
    ),
);
</script>

<template>
    <Head title="Claimed items" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold">Claimed items</h1>
            <p class="text-sm text-muted-foreground">
                Everything that's been reserved, bought or delivered, across
                everyone's lists. Items on your own list are left out so your
                surprises stay intact.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="ownerFilter"
                :class="selectClass"
                aria-label="Filter by person"
            >
                <option value="all">Everyone</option>
                <option
                    v-for="owner in owners"
                    :key="owner.id"
                    :value="owner.id"
                >
                    For {{ owner.name }}
                </option>
            </select>
            <select
                v-model="statusFilter"
                :class="selectClass"
                aria-label="Filter by status"
            >
                <option value="all">All statuses</option>
                <option value="reserved">Reserved</option>
                <option value="purchased">Bought</option>
                <option value="delivered">Delivered</option>
            </select>
            <span class="text-sm text-muted-foreground">
                {{ filteredItems.length }}
                {{ filteredItems.length === 1 ? 'item' : 'items' }}
            </span>
        </div>

        <WishlistItemTable
            v-if="filteredItems.length"
            :items="filteredItems"
            show-owner
            show-claimer
            editable-status
        />
        <p
            v-else
            class="rounded-xl border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground"
        >
            {{
                items.length
                    ? 'Nothing matches these filters.'
                    : 'Nothing has been claimed yet.'
            }}
        </p>
    </div>
</template>
