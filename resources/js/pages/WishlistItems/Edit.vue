<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/WishlistItemController';
import WishlistItemForm from '@/components/WishlistItemForm.vue';
import type { SelectOption } from '@/types';

type EditableItem = {
    id: number;
    title: string;
    description: string | null;
    url: string | null;
    image_url: string | null;
    price: string | null;
    size: string | null;
    color: string | null;
    priority: string;
    notes: string | null;
    visibility_status: string;
};

const props = defineProps<{
    item: EditableItem;
    priorities: SelectOption[];
    visibilities: SelectOption[];
}>();

const initial = {
    title: props.item.title,
    description: props.item.description ?? '',
    url: props.item.url ?? '',
    image_url: props.item.image_url ?? '',
    price: props.item.price ?? '',
    size: props.item.size ?? '',
    color: props.item.color ?? '',
    priority: props.item.priority,
    notes: props.item.notes ?? '',
    visibility_status: props.item.visibility_status,
};
</script>

<template>
    <Head title="Edit wishlist item" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div
            class="rounded-3xl border-2 border-border bg-card px-6 py-6 backdrop-blur"
        >
            <p
                class="text-xs font-semibold tracking-[0.2em] text-gold uppercase"
            >
                Tidy up
            </p>
            <h1 class="mt-1 font-display text-3xl font-semibold">
                Edit your wish
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Change your mind? Update the details below.
            </p>
        </div>

        <div class="rounded-3xl border-2 border-border bg-card p-6">
            <WishlistItemForm
                :priorities="priorities"
                :visibilities="visibilities"
                :submit-url="update(item.id).url"
                method="put"
                submit-label="Save changes"
                :initial="initial"
            />
        </div>
    </div>
</template>
