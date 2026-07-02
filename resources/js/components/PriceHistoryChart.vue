<script setup lang="ts">
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import type { PricePoint } from '@/types/wishlist';

ChartJS.register(
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Tooltip,
    Filler,
);

const props = defineProps<{
    history: PricePoint[];
}>();

/**
 * Resolve a theme CSS variable to a concrete colour so the chart matches light
 * and dark mode. Falls back to a sensible gold when the variable is unset (e.g.
 * during SSR where there is no document).
 */
function themeColor(variable: string, fallback: string): string {
    if (typeof document === 'undefined') {
        return fallback;
    }

    const value = getComputedStyle(document.documentElement)
        .getPropertyValue(variable)
        .trim();

    return value || fallback;
}

const currencyFormatter = new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
});

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
});

const chartData = computed<ChartData<'line'>>(() => {
    const line = themeColor('--gold', 'hsl(38 58% 48%)');

    return {
        labels: props.history.map((point) =>
            dateFormatter.format(new Date(point.recorded_at)),
        ),
        datasets: [
            {
                data: props.history.map((point) => Number(point.price)),
                borderColor: line,
                backgroundColor: 'transparent',
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                pointHoverBackgroundColor: line,
                tension: 0.3,
                fill: false,
            },
        ],
    };
});

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    scales: {
        x: { display: false },
        y: { display: false, grace: '5%' },
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            displayColors: false,
            callbacks: {
                label: (context) =>
                    currencyFormatter.format(context.parsed.y ?? 0),
            },
        },
    },
}));

// A single point can't draw a line; the sparkline needs at least two.
const hasEnoughPoints = computed(() => props.history.length >= 2);
</script>

<template>
    <div v-if="hasEnoughPoints" class="h-16 w-full">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
