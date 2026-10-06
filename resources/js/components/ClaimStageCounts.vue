<script setup lang="ts">
import { computed } from 'vue';

export type ClaimCounts = {
    reserved: number;
    purchased: number;
    delivered: number;
};

const props = defineProps<{
    counts: ClaimCounts;
}>();

const stages: { key: keyof ClaimCounts; label: string; dot: string }[] = [
    { key: 'reserved', label: 'reserved', dot: 'bg-gold' },
    { key: 'purchased', label: 'bought', dot: 'bg-holly' },
    { key: 'delivered', label: 'delivered', dot: 'bg-cranberry' },
];

// Only the stages that actually have gifts in them.
const activeStages = computed(() =>
    stages.filter((stage) => props.counts[stage.key] > 0),
);
</script>

<template>
    <div v-if="activeStages.length" class="flex gap-3 whitespace-nowrap">
        <span
            v-for="stage in activeStages"
            :key="stage.key"
            class="inline-flex items-center gap-1.5"
        >
            <span class="size-2 rounded-full" :class="stage.dot" />
            {{ counts[stage.key] }} {{ stage.label }}
        </span>
    </div>
    <span v-else class="text-muted-foreground">—</span>
</template>
