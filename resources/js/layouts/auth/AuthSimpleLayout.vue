<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { TreePine } from '@lucide/vue';
import Snowfall from '@/components/Snowfall.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="candlelight relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10"
    >
        <Snowfall />

        <div class="relative z-10 w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-3 font-medium"
                    >
                        <span
                            class="flex size-12 items-center justify-center rounded-full bg-holly text-[hsl(40_44%_98%)] shadow-sm ring-2 ring-gold/40"
                        >
                            <TreePine class="size-6" />
                        </span>
                        <span
                            class="font-display text-2xl font-semibold tracking-tight text-[hsl(40_30%_94%)]"
                        >
                            Wishlist
                        </span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1
                            class="font-display text-xl font-semibold text-[hsl(40_30%_94%)]"
                        >
                            {{ title }}
                        </h1>
                        <p class="text-center text-sm text-white/65">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <div
                    class="rounded-3xl border-2 border-border bg-card p-6 shadow-sm backdrop-blur"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
