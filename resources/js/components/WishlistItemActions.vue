<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SurpriseMergeController from '@/actions/App/Http/Controllers/Admin/SurpriseMergeController';
import ReceivedItemController from '@/actions/App/Http/Controllers/ReceivedItemController';
import ReservationConfirmationController from '@/actions/App/Http/Controllers/ReservationConfirmationController';
import ShipmentTrackingController from '@/actions/App/Http/Controllers/ShipmentTrackingController';
import WishlistItemController from '@/actions/App/Http/Controllers/WishlistItemController';
import WishlistItemPurchaseController from '@/actions/App/Http/Controllers/WishlistItemPurchaseController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Carrier, WishlistItem } from '@/types';

const props = defineProps<{
    item: WishlistItem;
    // Stretch the claim button group to fill the width (used in the card grid).
    block?: boolean;
    // Leave out reserve/bought/delivered/release, e.g. where status is edited inline.
    hideClaimControls?: boolean;
}>();

const purchaseNote = ref('');
// What the giver actually paid, asked for whenever they mark it bought.
const pricePaid = ref<string | number>('');
// Optional tracking number, asked for alongside the price when buying.
const newTrackingNumber = ref('');
// The tracking dialog edits whatever is on file, so keep it in step.
const trackingNumber = ref(props.item.purchase?.tracking_number ?? '');
const carrier = ref<Carrier | ''>(props.item.purchase?.carrier ?? '');
const processing = ref(false);

const carrierOptions: { value: Carrier | ''; label: string }[] = [
    { value: '', label: 'Detect automatically' },
    { value: 'ups', label: 'UPS' },
    { value: 'usps', label: 'USPS' },
    { value: 'fedex', label: 'FedEx' },
    { value: 'dhl', label: 'DHL' },
    { value: 'amazon', label: 'Amazon' },
    { value: 'other', label: 'Other' },
];

watch(
    () => [props.item.purchase?.tracking_number, props.item.purchase?.carrier],
    ([number, savedCarrier]) => {
        trackingNumber.value = number ?? '';
        carrier.value = (savedCarrier as Carrier | null | undefined) ?? '';
    },
);

const pricePaidPayload = computed(() =>
    pricePaid.value === '' || pricePaid.value === null
        ? null
        : String(pricePaid.value),
);

// Whether a non-owner has any claim action available — used to avoid rendering
// an empty bordered button group when the item is claimed by someone else.
const hasClaimActions = computed(
    () =>
        (props.item.can.purchase && !props.item.is_purchased) ||
        props.item.purchase?.can_mark_bought ||
        props.item.purchase?.can_mark_delivered ||
        props.item.purchase?.needs_confirmation ||
        props.item.purchase?.can_edit_tracking ||
        (props.item.is_purchased && props.item.purchase?.can_unmark),
);

// Shared segmented button-group container. Stretches to full width in the card.
const groupClass = computed(() => [
    'divide-x divide-input overflow-hidden rounded-md border border-input',
    props.block ? 'flex w-full [&>*]:flex-1' : 'inline-flex',
]);

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
        {
            status: 'purchased',
            price_paid: pricePaidPayload.value,
            tracking_number: newTrackingNumber.value || null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                pricePaid.value = '';
                newTrackingNumber.value = '';
            },
        },
    );
}

function advanceClaim(status: 'purchased' | 'delivered') {
    processing.value = true;
    router.patch(
        WishlistItemPurchaseController.update(props.item.id).url,
        status === 'purchased'
            ? {
                  status,
                  price_paid: pricePaidPayload.value,
                  tracking_number: newTrackingNumber.value || null,
              }
            : { status },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                pricePaid.value = '';
                newTrackingNumber.value = '';
            },
        },
    );
}

function saveTracking() {
    processing.value = true;
    router.put(
        ShipmentTrackingController.update(props.item.id).url,
        {
            tracking_number: trackingNumber.value,
            carrier: carrier.value || null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function removeTracking() {
    processing.value = true;
    router.delete(ShipmentTrackingController.destroy(props.item.id).url, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
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

// Answer a stale-reservation reminder: "yes, I'm still getting this".
function keepReservation() {
    processing.value = true;
    router.post(
        ReservationConfirmationController.store(props.item.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function markReceived() {
    router.post(
        ReceivedItemController.store(props.item.id).url,
        {},
        { preserveScroll: true },
    );
}

// Fold the matching surprise's claim into this wish and drop the surprise.
function mergeSurprise() {
    if (!props.item.surprise_match) {
        return;
    }

    processing.value = true;
    router.post(
        SurpriseMergeController.store(props.item.surprise_match.id).url,
        { wish_id: props.item.id },
        {
            preserveScroll: true,
            onFinish: () => (processing.value = false),
        },
    );
}

function deleteItem() {
    router.delete(WishlistItemController.destroy(props.item.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            v-if="item.url"
            as-child
            variant="outline"
            size="sm"
            :class="{ 'w-full': block }"
        >
            <a :href="item.url" target="_blank" rel="noopener noreferrer">
                <ExternalLink class="size-4" />
                View
            </a>
        </Button>

        <!-- Owner controls — never any purchase state -->
        <div
            v-if="item.is_owner && (item.can.update || item.can.delete)"
            :class="groupClass"
        >
            <Button
                v-if="item.can.update"
                as-child
                variant="ghost"
                size="sm"
                class="rounded-none"
            >
                <Link :href="WishlistItemController.edit(item.id).url">
                    Edit
                </Link>
            </Button>

            <ConfirmDialog
                v-if="item.can.update && !item.is_received"
                title="Got this one?"
                :description="`“${item.title}” will move to your received gifts and come off your list for everyone.`"
                confirm-label="Mark received"
                @confirm="markReceived"
            >
                <template #trigger>
                    <Button variant="ghost" size="sm" class="rounded-none">
                        Received
                    </Button>
                </template>
            </ConfirmDialog>

            <ConfirmDialog
                v-if="item.can.delete"
                title="Delete this item?"
                :description="`“${item.title}” will be removed from your wishlist.`"
                confirm-label="Delete"
                confirm-variant="destructive"
                @confirm="deleteItem"
            >
                <template #trigger>
                    <Button variant="ghost" size="sm" class="rounded-none">
                        Delete
                    </Button>
                </template>
            </ConfirmDialog>
        </div>

        <!-- Non-owner: claim controls, kept together as one button group -->
        <div
            v-else-if="hasClaimActions && !hideClaimControls"
            :class="groupClass"
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
            <ConfirmDialog
                v-if="item.can.purchase && !item.is_purchased"
                title="Mark as bought?"
                :description="`Let the group know you've bought “${item.title}”. ${item.owner_name ?? 'They'} will never see it.`"
                confirm-label="Mark as bought"
                :processing="processing"
                @confirm="buyOutright"
            >
                <template #trigger>
                    <Button variant="ghost" size="sm" class="rounded-none">
                        Mark as bought
                    </Button>
                </template>
                <div class="grid gap-2">
                    <Label :for="`price-paid-${item.id}`">
                        Price you paid (optional)
                    </Label>
                    <Input
                        :id="`price-paid-${item.id}`"
                        v-model="pricePaid"
                        type="number"
                        step="0.01"
                        min="0"
                        inputmode="decimal"
                        :placeholder="item.price ?? '0.00'"
                    />
                    <p class="text-xs text-muted-foreground">
                        Leave blank to use the listed price.
                    </p>
                    <Label :for="`tracking-number-${item.id}`" class="mt-2">
                        Tracking number (optional)
                    </Label>
                    <Input
                        :id="`tracking-number-${item.id}`"
                        v-model="newTrackingNumber"
                        autocomplete="off"
                        placeholder="e.g. 1Z999AA10123456784"
                    />
                </div>
            </ConfirmDialog>

            <!-- Reply to the stale-reservation reminder email -->
            <Button
                v-if="item.purchase?.needs_confirmation"
                variant="ghost"
                size="sm"
                class="rounded-none"
                :disabled="processing"
                @click="keepReservation"
            >
                Keep it
            </Button>

            <ConfirmDialog
                v-if="item.purchase?.can_mark_bought"
                title="Mark as bought?"
                :description="`Move “${item.title}” from reserved to bought.`"
                confirm-label="Mark as bought"
                :processing="processing"
                @confirm="advanceClaim('purchased')"
            >
                <template #trigger>
                    <Button variant="ghost" size="sm" class="rounded-none">
                        Mark as bought
                    </Button>
                </template>
                <div class="grid gap-2">
                    <Label :for="`price-paid-${item.id}`">
                        Price you paid (optional)
                    </Label>
                    <Input
                        :id="`price-paid-${item.id}`"
                        v-model="pricePaid"
                        type="number"
                        step="0.01"
                        min="0"
                        inputmode="decimal"
                        :placeholder="item.price ?? '0.00'"
                    />
                    <p class="text-xs text-muted-foreground">
                        Leave blank to use the listed price.
                    </p>
                    <Label :for="`tracking-number-${item.id}`" class="mt-2">
                        Tracking number (optional)
                    </Label>
                    <Input
                        :id="`tracking-number-${item.id}`"
                        v-model="newTrackingNumber"
                        autocomplete="off"
                        placeholder="e.g. 1Z999AA10123456784"
                    />
                </div>
            </ConfirmDialog>

            <ConfirmDialog
                v-if="item.purchase?.can_edit_tracking"
                :title="
                    item.purchase.tracking_number
                        ? 'Update tracking'
                        : 'Add tracking'
                "
                :description="`Follow where “${item.title}” is. The carrier is worked out from the number unless you pick one.`"
                confirm-label="Save tracking"
                :processing="processing"
                @confirm="saveTracking"
            >
                <template #trigger>
                    <Button variant="ghost" size="sm" class="rounded-none">
                        Tracking
                    </Button>
                </template>
                <div class="grid gap-2">
                    <Label :for="`tracking-edit-${item.id}`">
                        Tracking number
                    </Label>
                    <Input
                        :id="`tracking-edit-${item.id}`"
                        v-model="trackingNumber"
                        autocomplete="off"
                        placeholder="e.g. 1Z999AA10123456784"
                    />
                    <Label :for="`carrier-${item.id}`" class="mt-2">
                        Carrier
                    </Label>
                    <select
                        :id="`carrier-${item.id}`"
                        v-model="carrier"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option
                            v-for="option in carrierOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <button
                        v-if="item.purchase.tracking_number"
                        type="button"
                        class="justify-self-start text-xs text-muted-foreground underline-offset-2 hover:text-cranberry hover:underline"
                        :disabled="processing"
                        @click="removeTracking"
                    >
                        Remove tracking
                    </button>
                </div>
            </ConfirmDialog>

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

        <!-- Admins only: merge a matching surprise into this wish -->
        <div v-if="item.surprise_match?.can_merge" :class="groupClass">
            <ConfirmDialog
                title="Merge the surprise into this wish?"
                :description="`The surprise “${item.surprise_match.title}” will be removed, and anything claimed on it moves onto “${item.title}”. ${item.owner_name ?? 'They'} still won't see who has it.`"
                confirm-label="Merge"
                :processing="processing"
                @confirm="mergeSurprise"
            >
                <template #trigger>
                    <Button variant="ghost" size="sm" class="rounded-none">
                        Merge surprise
                    </Button>
                </template>
            </ConfirmDialog>
        </div>

        <!-- Admins look after surprises, which their owner never sees -->
        <div
            v-if="item.is_surprise && (item.can.update || item.can.delete)"
            :class="groupClass"
        >
            <Button
                v-if="item.can.update"
                as-child
                variant="ghost"
                size="sm"
                class="rounded-none"
            >
                <Link :href="WishlistItemController.edit(item.id).url">
                    Edit
                </Link>
            </Button>

            <ConfirmDialog
                v-if="item.can.delete"
                title="Delete this item?"
                :description="`“${item.title}” will be removed from ${item.owner_name ? `${item.owner_name}'s` : 'their'} list.`"
                confirm-label="Delete"
                confirm-variant="destructive"
                @confirm="deleteItem"
            >
                <template #trigger>
                    <Button variant="ghost" size="sm" class="rounded-none">
                        Delete
                    </Button>
                </template>
            </ConfirmDialog>
        </div>
    </div>
</template>
