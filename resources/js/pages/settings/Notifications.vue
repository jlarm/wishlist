<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { edit, update } from '@/routes/notifications';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Notification settings',
                href: edit(),
            },
        ],
    },
});

const props = defineProps<{
    preferences: {
        notify_price_drops: boolean;
        notify_gift_purchases: boolean;
    };
}>();

const form = useForm({
    notify_price_drops: props.preferences.notify_price_drops,
    notify_gift_purchases: props.preferences.notify_gift_purchases,
});

function submit() {
    form.patch(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notification settings" />

    <h1 class="sr-only">Notification settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Notifications"
            description="Choose which emails we send you"
        />

        <form class="space-y-4" @submit.prevent="submit">
            <label
                class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-card p-4"
            >
                <Checkbox v-model="form.notify_price_drops" class="mt-0.5" />
                <span class="grid gap-1">
                    <span class="text-sm font-medium"> Price-drop alerts </span>
                    <span class="text-sm text-muted-foreground">
                        Email me when a gift I could buy drops in price — an
                        item I've reserved, or anything still up for grabs.
                    </span>
                </span>
            </label>

            <label
                class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-card p-4"
            >
                <Checkbox v-model="form.notify_gift_purchases" class="mt-0.5" />
                <span class="grid gap-1">
                    <span class="text-sm font-medium">
                        Gift claimed alerts
                    </span>
                    <span class="text-sm text-muted-foreground">
                        Email me when someone claims a gift on another person's
                        wishlist, so I don't buy the same thing. The list owner
                        is never told.
                    </span>
                </span>
            </label>

            <div class="flex items-center gap-4">
                <Button :disabled="form.processing">Save</Button>
            </div>
        </form>
    </div>
</template>
