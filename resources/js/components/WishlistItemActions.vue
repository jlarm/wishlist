<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ExternalLink, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import WishlistItemController from '@/actions/App/Http/Controllers/WishlistItemController';
import WishlistItemPurchaseController from '@/actions/App/Http/Controllers/WishlistItemPurchaseController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { WishlistItem } from '@/types';

const props = defineProps<{
    item: WishlistItem;
    // Stretch the claim button group to fill the width (used in the card grid).
    block?: boolean;
}>();

const purchaseNote = ref('');
const processing = ref(false);

// Whether a non-owner has any claim action available — used to avoid rendering
// an empty bordered button group when the item is claimed by someone else.
const hasClaimActions = computed(
    () =>
        (props.item.can.purchase && !props.item.is_purchased) ||
        props.item.purchase?.can_mark_bought ||
        props.item.purchase?.can_mark_delivered ||
        (props.item.is_purchased && props.item.purchase?.can_unmark),
);

function reserve() {
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

// Skip the reservation step and claim the item as already bought.
function buyOutright() {
    processing.value = true;
    router.post(
        WishlistItemPurchaseController.store(props.item.id).url,
        { status: 'purchased' },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function advanceClaim(status: 'purchased' | 'delivered') {
    processing.value = true;
    router.patch(
        WishlistItemPurchaseController.update(props.item.id).url,
        { status },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function release() {
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
    <div class="flex flex-wrap items-center gap-2">
        <Button v-if="item.url" as-child variant="outline" size="sm">
            <a :href="item.url" target="_blank" rel="noopener noreferrer">
                <ExternalLink class="size-4" />
                View
            </a>
        </Button>

        <!-- Owner controls — never any purchase state -->
        <template v-if="item.is_owner">
            <Button v-if="item.can.update" as-child variant="outline" size="sm">
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

        <!-- Non-owner: claim controls, kept together as one button group -->
        <div
            v-else-if="hasClaimActions"
            :class="[
                'divide-x divide-input overflow-hidden rounded-md border border-input',
                block ? 'flex w-full [&>*]:flex-1' : 'inline-flex',
            ]"
        >
            <ConfirmDialog
                v-if="item.can.purchase && !item.is_purchased"
                title="Reserve this gift?"
                :description="`Let the group know you're planning to get “${item.title}”. ${item.owner_name ?? 'They'} will never see it. You can mark it as bought later.`"
                confirm-label="Reserve it"
                :processing="processing"
                @confirm="reserve"
            >
                <template #trigger>
                    <Button variant="ghost" size="sm" class="rounded-none">
                        Reserve
                    </Button>
                </template>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="purchase-note">
                        Optional note for other gift-givers
                    </label>
                    <Textarea
                        id="purchase-note"
                        v-model="purchaseNote"
                        placeholder="e.g. planning to order this week"
                    />
                </div>
            </ConfirmDialog>

            <!-- Skip reserving and claim it as already bought -->
            <Button
                v-if="item.can.purchase && !item.is_purchased"
                variant="ghost"
                size="sm"
                class="rounded-none"
                :disabled="processing"
                @click="buyOutright"
            >
                Mark as bought
            </Button>

            <Button
                v-if="item.purchase?.can_mark_bought"
                variant="ghost"
                size="sm"
                class="rounded-none"
                :disabled="processing"
                @click="advanceClaim('purchased')"
            >
                Mark as bought
            </Button>

            <Button
                v-if="item.purchase?.can_mark_delivered"
                variant="ghost"
                size="sm"
                class="rounded-none"
                :disabled="processing"
                @click="advanceClaim('delivered')"
            >
                Mark as delivered
            </Button>

            <Button
                v-if="item.is_purchased && item.purchase?.can_unmark"
                variant="ghost"
                size="sm"
                class="rounded-none"
                :disabled="processing"
                @click="release"
            >
                Release
            </Button>
        </div>
    </div>
</template>
