<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Heart, Inbox, Undo2 } from '@lucide/vue';
import { computed } from 'vue';
import ReceivedItemController from '@/actions/App/Http/Controllers/ReceivedItemController';
import ThankYouController from '@/actions/App/Http/Controllers/ThankYouController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { show as wishlistShow } from '@/routes/wishlists';
import type { User } from '@/types';

type ReceivedItem = {
    id: number;
    title: string;
    url: string | null;
    image_url: string | null;
    price: string | null;
    received_at: string | null;
    thanked_at: string | null;
    // Only set when a giver had actually bought it — never for a reservation.
};

const props = defineProps<{
    items: ReceivedItem[];
}>();

const page = usePage<{ auth: { user: User } }>();

const thankedCount = computed(
    () => props.items.filter((item) => item.thanked_at).length,
);

function toggleThanked(item: ReceivedItem, thanked: boolean | 'indeterminate') {
    const options = { preserveScroll: true };

    if (thanked === true) {
        router.post(ThankYouController.store(item.id).url, {}, options);
    } else {
        router.delete(ThankYouController.destroy(item.id).url, options);
    }
}

function restore(item: ReceivedItem) {
    router.delete(ReceivedItemController.destroy(item.id).url, {
        preserveScroll: true,
    });
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '';
}
</script>

<template>
    <Head title="Received gifts" />

    <div class="flex flex-col gap-6">
        <div
            class="rounded-3xl border-2 border-border bg-card px-6 py-7 backdrop-blur"
        >
            <Link
                :href="wishlistShow(page.props.auth.user.id as number)"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                Back to my list
            </Link>
            <h1 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">
                Received gifts
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Gifts you've marked as received.
                <template v-if="items.length">
                    {{ thankedCount }} of {{ items.length }} thanked.
                </template>
            </p>
        </div>

        <ul
            v-if="items.length"
            class="divide-y divide-border rounded-3xl border-2 border-border bg-card px-4 sm:px-5"
        >
            <li
                v-for="item in items"
                :key="item.id"
                class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <img
                        :src="item.image_url ?? '/tree.webp'"
                        :alt="item.title"
                        class="size-12 shrink-0 rounded-lg bg-muted object-cover"
                    />
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ item.title }}</p>
                        <p class="text-xs text-muted-foreground">
                            Received {{ formatDate(item.received_at) }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <label
                        class="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <Checkbox
                            :model-value="!!item.thanked_at"
                            @update:model-value="
                                (value) => toggleThanked(item, value)
                            "
                        />
                        <Heart
                            class="size-4"
                            :class="
                                item.thanked_at
                                    ? 'fill-cranberry text-cranberry'
                                    : 'text-muted-foreground'
                            "
                        />
                        Thanked
                    </label>

                    <ConfirmDialog
                        title="Put this back on your list?"
                        :description="`“${item.title}” will show up on your wishlist again.`"
                        confirm-label="Put back"
                        @confirm="restore(item)"
                    >
                        <template #trigger>
                            <Button variant="ghost" size="sm">
                                <Undo2 class="size-4" />
                                Put back
                            </Button>
                        </template>
                    </ConfirmDialog>
                </div>
            </li>
        </ul>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-3 rounded-3xl border-2 border-dashed border-border bg-card p-12 text-center"
        >
            <div class="rounded-full bg-holly/10 p-4 text-holly">
                <Inbox class="size-8" />
            </div>
            <p class="font-display text-lg font-semibold">Nothing yet</p>
            <p class="text-sm text-muted-foreground">
                When you get something from your list, tap
                <strong>Received</strong> on it and it'll land here.
            </p>
        </div>
    </div>
</template>
