<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="currentColor"
        :class="className"
        v-bind="$attrs"
    >
        <!-- box + lid with the ribbon cut out -->
        <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M3 11h18v10H3z M2 7.5h20V11H2z M10.5 7.5h3V21h-3z"
        />
        <!-- bow -->
        <path d="M12 8 5.2 3.8 8.4 8z" />
        <path d="M12 8 18.8 3.8 15.6 8z" />
    </svg>
</template>
