<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, ExternalLink, Gift, Pencil, Trash2, Truck } from '@lucide/vue';
import { ref } from 'vue';
import WishlistItemController from '@/actions/App/Http/Controllers/WishlistItemController';
import WishlistItemPurchaseController from '@/actions/App/Http/Controllers/WishlistItemPurchaseController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { WishlistItem } from '@/types';

const props = defineProps<{
    item: WishlistItem;
}>();

const purchaseNote = ref('');
const processing = ref(false);

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

        <!-- Non-owner: claim controls -->
        <template v-else>
            <ConfirmDialog
                v-if="item.can.purchase && !item.is_purchased"
                title="Reserve this gift?"
                :description="`Let the group know you're planning to get “${item.title}”. ${item.owner_name ?? 'They'} will never see it. You can mark it as bought later.`"
                confirm-label="Reserve it"
                :processing="processing"
                @confirm="reserve"
            >
                <template #trigger>
                    <Button size="sm">
                        <Gift class="size-4" />
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

            <Button
                v-if="item.purchase?.can_mark_bought"
                size="sm"
                :disabled="processing"
                @click="advanceClaim('purchased')"
            >
                <Check class="size-4" />
                Mark as bought
            </Button>

            <Button
                v-if="item.purchase?.can_mark_delivered"
                size="sm"
                :disabled="processing"
                @click="advanceClaim('delivered')"
            >
                <Truck class="size-4" />
                Mark as delivered
            </Button>

            <Button
                v-if="item.is_purchased && item.purchase?.can_unmark"
                variant="ghost"
                size="sm"
                :disabled="processing"
                @click="release"
            >
                Release
            </Button>
        </template>
    </div>
</template>
