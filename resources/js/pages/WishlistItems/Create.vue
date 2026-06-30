<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/WishlistItemController';
import WishlistItemForm from '@/components/WishlistItemForm.vue';
import type { SelectOption } from '@/types';

defineProps<{
    priorities: SelectOption[];
    visibilities: SelectOption[];
}>();
</script>

<template>
    <Head title="Add a wish" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div
            class="rounded-3xl border-2 border-border bg-card px-6 py-6 backdrop-blur"
        >
            <p
                class="text-xs font-semibold tracking-[0.2em] text-gold uppercase"
            >
                A new wish
            </p>
            <h1 class="mt-1 font-display text-3xl font-semibold">
                Add to your list
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                The more detail you give, the easier you make the gift-givers'
                job.
            </p>
        </div>

        <div class="rounded-3xl border-2 border-border bg-card p-6">
            <WishlistItemForm
                :priorities="priorities"
                :visibilities="visibilities"
                :submit-url="store().url"
                method="post"
                submit-label="Add to my list"
            />
        </div>
    </div>
</template>
