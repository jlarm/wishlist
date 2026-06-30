<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    priority: string;
}>();

// Each priority hangs as a differently coloured bauble.
const ballColor = computed(() => {
    switch (props.priority) {
        case 'most_wanted':
            return 'var(--gold)';
        case 'high':
            return 'var(--cranberry)';
        case 'low':
            return 'var(--muted-foreground)';
        default:
            return 'var(--holly)';
    }
});

const label = computed(() => {
    switch (props.priority) {
        case 'most_wanted':
            return 'Most wanted';
        case 'high':
            return 'High priority';
        case 'low':
            return 'Low priority';
        default:
            return 'Medium priority';
    }
});
</script>

<template>
    <div
        class="pointer-events-none absolute -top-7 right-5 z-10"
        :title="label"
    >
        <svg
            width="22"
            height="34"
            viewBox="0 0 22 34"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            :aria-label="`${label} ornament`"
        >
            <!-- string -->
            <line
                x1="11"
                y1="0"
                x2="11"
                y2="10"
                stroke="var(--gold)"
                stroke-width="1.5"
                stroke-linecap="round"
            />
            <!-- hanging loop -->
            <circle
                cx="11"
                cy="11"
                r="2.4"
                fill="none"
                stroke="var(--gold)"
                stroke-width="1.6"
            />
            <!-- metal cap -->
            <rect
                x="8.6"
                y="12.6"
                width="4.8"
                height="3.4"
                rx="1"
                fill="var(--gold)"
            />
            <!-- bauble -->
            <circle
                cx="11"
                cy="24.5"
                r="7.8"
                :fill="ballColor"
                stroke="var(--card)"
                stroke-width="2"
            />
            <!-- shine -->
            <ellipse
                cx="8.2"
                cy="21.5"
                rx="2"
                ry="1.3"
                fill="#fff"
                opacity="0.5"
            />
        </svg>
        <Star
            v-if="priority === 'most_wanted'"
            class="absolute size-3 fill-white text-white"
            style="left: 5px; top: 19px"
        />
    </div>
</template>
