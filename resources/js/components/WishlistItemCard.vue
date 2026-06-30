<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Check,
    ExternalLink,
    Gift,
    ImageOff,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import WishlistItemController from '@/actions/App/Http/Controllers/WishlistItemController';
import WishlistItemPurchaseController from '@/actions/App/Http/Controllers/WishlistItemPurchaseController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PriorityOrnament from '@/components/PriorityOrnament.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { WishlistItem } from '@/types';

const props = defineProps<{
    item: WishlistItem;
    showOwner?: boolean;
}>();

const imageFailed = ref(false);
const purchaseNote = ref('');
const processing = ref(false);

const ornamentClass = computed(() => {
    switch (props.item.priority) {
        case 'most_wanted':
            return 'bg-gold';
        case 'high':
            return 'bg-cranberry';
        case 'low':
            return 'bg-muted-foreground/40';
        default:
            return 'bg-holly';
    }
});

const formattedPrice = computed(() => {
    if (props.item.price === null) {
        return null;
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(props.item.price));
});

const purchasedDate = computed(() => {
    const at = props.item.purchase?.purchased_at;

    return at ? new Date(at).toLocaleDateString() : null;
});

function markPurchased() {
    processing.value = true;
    router.post(
        WishlistItemPurchaseController.store(props.item.id).url,
        { note: purchaseNote.value },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                purchaseNote.value = '';
            },
        },
    );
}

function unmarkPurchased() {
    processing.value = true;
    router.delete(WishlistItemPurchaseController.destroy(props.item.id).url, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}

function deleteItem() {
    router.delete(WishlistItemController.destroy(props.item.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div
        class="group relative flex flex-col rounded-2xl border-2 border-border bg-card p-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
    >
        <!-- Hanging bauble showing priority -->
        <PriorityOrnament :priority="item.priority" />

        <!-- Image -->
        <div
            class="relative flex aspect-video items-center justify-center overflow-hidden rounded-xl bg-muted"
        >
            <img
                v-if="item.image_url && !imageFailed"
                :src="item.image_url"
                :alt="item.title"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                @error="imageFailed = true"
            />
            <ImageOff v-else class="size-8 text-muted-foreground" />

            <span
                v-if="item.visibility_status === 'hidden'"
                class="absolute top-2 right-2 rounded-full bg-background/85 px-2 py-0.5 text-xs font-medium text-muted-foreground backdrop-blur"
            >
                Hidden
            </span>
        </div>

        <div class="flex flex-1 flex-col gap-3 px-1 pt-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h3
                        class="truncate font-display text-lg leading-tight font-semibold"
                    >
                        {{ item.title }}
                    </h3>
                    <p
                        v-if="showOwner && item.owner_name"
                        class="text-xs text-muted-foreground"
                    >
                        For {{ item.owner_name }}
                    </p>
                </div>
                <span
                    v-if="formattedPrice"
                    class="shrink-0 rounded-full bg-gold/15 px-2.5 py-1 text-sm font-semibold whitespace-nowrap text-gold"
                >
                    {{ formattedPrice }}
                </span>
            </div>

            <!-- Priority + size + color, as little kraft tags -->
            <div class="flex flex-wrap gap-1.5 text-xs">
                <span
                    class="inline-flex items-center gap-1 rounded-full border border-border bg-secondary/60 px-2 py-0.5 font-medium"
                >
                    <span
                        class="size-1.5 rounded-full"
                        :class="ornamentClass"
                    />
                    {{ item.priority_label }}
                </span>
                <span
                    class="rounded-full bg-muted px-2 py-0.5"
                    :class="item.size ? '' : 'text-muted-foreground italic'"
                >
                    Size: {{ item.size ?? 'Any' }}
                </span>
                <span
                    class="rounded-full bg-muted px-2 py-0.5"
                    :class="item.color ? '' : 'text-muted-foreground italic'"
                >
                    Color: {{ item.color ?? 'Any' }}
                </span>
            </div>

            <p v-if="item.description" class="text-sm text-muted-foreground">
                {{ item.description }}
            </p>

            <p v-if="item.notes" class="text-sm">
                <span class="font-medium">Notes:</span> {{ item.notes }}
            </p>

            <!-- Claimed ribbon — only ever rendered for non-owners -->
            <div
                v-if="!item.is_owner && item.is_purchased"
                class="rounded-lg border border-cranberry/30 bg-cranberry/10 px-3 py-2"
            >
                <p
                    class="flex items-center gap-1.5 text-sm font-semibold text-cranberry"
                >
                    <Check class="size-4" />
                    Claimed<template v-if="item.purchase?.purchased_by_name">
                        by {{ item.purchase.purchased_by_name }}</template
                    >
                    <span
                        v-if="item.purchase?.purchased_by_me"
                        class="font-normal"
                        >(you)</span
                    >
                </p>
                <p
                    v-if="purchasedDate"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    Marked on {{ purchasedDate }}
                </p>
                <p
                    v-if="item.purchase?.note"
                    class="mt-0.5 text-xs text-muted-foreground italic"
                >
                    “{{ item.purchase.note }}”
                </p>
            </div>

            <div class="mt-auto flex items-center gap-2 pt-2">
                <Button v-if="item.url" as-child variant="outline" size="sm">
                    <a
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLink class="size-4" />
                        View
                    </a>
                </Button>

                <!-- Owner controls — never any purchase state -->
                <template v-if="item.is_owner">
                    <Button
                        v-if="item.can.update"
                        as-child
                        variant="outline"
                        size="sm"
                    >
                        <Link :href="WishlistItemController.edit(item.id).url">
                            <Pencil class="size-4" />
                            Edit
                        </Link>
                    </Button>

                    <ConfirmDialog
                        v-if="item.can.delete"
                        title="Delete this item?"
                        :description="`“${item.title}” will be removed from your wishlist.`"
                        confirm-label="Delete"
                        confirm-variant="destructive"
                        @confirm="deleteItem"
                    >
                        <template #trigger>
                            <Button variant="ghost" size="sm">
                                <Trash2 class="size-4" />
                                Delete
                            </Button>
                        </template>
                    </ConfirmDialog>
                </template>

                <!-- Non-owner: claim controls -->
                <template v-else>
                    <ConfirmDialog
                        v-if="item.can.purchase && !item.is_purchased"
                        title="Claim this gift?"
                        :description="`Let the group know you're getting “${item.title}”. ${item.owner_name ?? 'They'} will never see it.`"
                        confirm-label="Claim it"
                        :processing="processing"
                        @confirm="markPurchased"
                    >
                        <template #trigger>
                            <Button size="sm">
                                <Gift class="size-4" />
                                Claim gift
                            </Button>
                        </template>
                        <div class="grid gap-2">
                            <label
                                class="text-sm font-medium"
                                for="purchase-note"
                            >
                                Optional note for other gift-givers
                            </label>
                            <Textarea
                                id="purchase-note"
                                v-model="purchaseNote"
                                placeholder="e.g. ordered, arriving next week"
                            />
                        </div>
                    </ConfirmDialog>

                    <Button
                        v-if="item.is_purchased && item.purchase?.can_unmark"
                        variant="ghost"
                        size="sm"
                        :disabled="processing"
                        @click="unmarkPurchased"
                    >
                        Unclaim
                    </Button>
                </template>
            </div>
        </div>
    </div>
</template>
