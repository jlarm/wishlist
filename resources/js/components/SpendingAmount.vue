<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    amount: string;
}>();

const formattedAmount = computed(() =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(props.amount)),
);
</script>

<template>
    <!-- Zero is dimmed to a dash so real amounts stand out -->
    <span v-if="Number(amount) === 0" class="text-muted-foreground">—</span>
    <span v-else>{{ formattedAmount }}</span>
</template>
