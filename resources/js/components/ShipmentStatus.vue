<script setup lang="ts">
import { ExternalLink, Truck } from '@lucide/vue';
import { computed } from 'vue';
import type { PurchaseInfo, TrackingStatus } from '@/types';

const props = defineProps<{
    purchase: PurchaseInfo;
    // Just the status line, for tight spots like a table cell.
    compact?: boolean;
}>();

const statusClass: Record<TrackingStatus, string> = {
    pending: 'text-muted-foreground',
    in_transit: 'text-holly',
    out_for_delivery: 'text-gold',
    delivered: 'text-holly',
    exception: 'text-cranberry',
};

// Only the giver gets the number; everyone else sees status alone.
const hasTracking = computed(
    () =>
        props.purchase.tracking_status !== null ||
        props.purchase.tracking_number !== null,
);

const estimatedDelivery = computed(() => {
    const at = props.purchase.tracking_estimated_delivery_at;

    if (!at || props.purchase.tracking_status === 'delivered') {
        return null;
    }

    // A bare date, so read it as local rather than UTC midnight.
    return new Date(`${at}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
});
</script>

<template>
    <div v-if="hasTracking" class="flex flex-col gap-0.5 text-xs">
        <p class="flex flex-wrap items-center gap-1.5">
            <Truck class="size-3.5 shrink-0 text-muted-foreground" />
            <span
                v-if="
                    purchase.tracking_status_label && purchase.tracking_status
                "
                class="font-semibold"
                :class="statusClass[purchase.tracking_status]"
            >
                {{ purchase.tracking_status_label }}
            </span>
            <span v-else class="text-muted-foreground">Tracking added</span>
            <span v-if="estimatedDelivery" class="text-muted-foreground">
                · arrives {{ estimatedDelivery }}
            </span>
        </p>
        <p
            v-if="!compact && purchase.tracking_detail"
            class="text-muted-foreground"
        >
            {{ purchase.tracking_detail }}
        </p>
        <p
            v-if="!compact && purchase.tracking_number"
            class="text-muted-foreground"
        >
            {{ purchase.carrier_label ?? 'Tracking' }}
            <a
                v-if="purchase.tracking_url"
                :href="purchase.tracking_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-0.5 font-mono underline-offset-2 hover:underline"
            >
                {{ purchase.tracking_number }}
                <ExternalLink class="size-3" />
            </a>
            <span v-else class="font-mono">{{ purchase.tracking_number }}</span>
        </p>
    </div>
</template>
