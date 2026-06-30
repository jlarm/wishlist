<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { Check, ImageOff, Sparkles, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import ProductMetadataController from '@/actions/App/Http/Controllers/ProductMetadataController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { SelectOption } from '@/types';

type ProductMetadata = {
    title: string | null;
    description: string | null;
    price: string | null;
    images: string[];
};

type WishlistItemFormData = {
    title: string;
    description: string;
    url: string;
    image_url: string;
    price: string;
    size: string;
    color: string;
    priority: string;
    notes: string;
    visibility_status: string;
};

const props = defineProps<{
    priorities: SelectOption[];
    visibilities: SelectOption[];
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
    initial?: Partial<WishlistItemFormData>;
}>();

const form = useForm<WishlistItemFormData>({
    title: props.initial?.title ?? '',
    description: props.initial?.description ?? '',
    url: props.initial?.url ?? '',
    image_url: props.initial?.image_url ?? '',
    price: props.initial?.price ?? '',
    size: props.initial?.size ?? '',
    color: props.initial?.color ?? '',
    priority: props.initial?.priority ?? 'medium',
    notes: props.initial?.notes ?? '',
    visibility_status: props.initial?.visibility_status ?? 'visible',
});

const imageFailed = ref(false);
const suggestedImages = ref<string[]>([]);
const fetchError = ref<string | null>(null);
const fetchSuccess = ref<string | null>(null);
const fetchDialogOpen = ref(false);

const metadata = useHttp<{ url: string }>({ url: '' });

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px]';

function fetchMetadata() {
    if (!form.url) {
        return;
    }

    fetchError.value = null;
    fetchSuccess.value = null;
    metadata.url = form.url;

    metadata.post(ProductMetadataController.url(), {
        onSuccess: (response: unknown) => {
            const data = response as ProductMetadata;

            // Only fill empty fields so we never clobber what the user typed.
            if (data.title && !form.title) {
                form.title = data.title;
            }

            if (data.description && !form.description) {
                form.description = data.description;
            }

            if (data.price && !form.price) {
                form.price = data.price;
            }

            suggestedImages.value = data.images;

            if (data.images.length > 0 && !form.image_url) {
                selectImage(data.images[0]);
            }

            const foundAnything =
                data.images.length > 0 ||
                !!data.title ||
                !!data.description ||
                !!data.price;

            if (foundAnything) {
                fetchSuccess.value = data.images.length
                    ? 'Pulled in the details. Pick an image below, then tap Done.'
                    : 'Pulled in the details. Tap Done to review them.';
            } else {
                fetchError.value =
                    "We reached that page but couldn't pull any details — some stores (like Amazon and Walmart) block this. Close this and fill it in yourself.";
            }
        },
        onError: () => {
            fetchError.value =
                "That link couldn't be read. Check the URL is correct and try again.";
        },
        // Any non-validation failure (server error, blocked, network) — never
        // leave the user staring at a spinner that resolved into nothing.
        onHttpException: () => {
            fetchError.value =
                "We couldn't read that link just now. You can still fill in the details below.";
        },
        onNetworkError: () => {
            fetchError.value =
                "Couldn't reach that link. Check your connection and the URL, then try again.";
        },
    }).catch(() => {
        // The handlers above already surfaced a message; useHttp rethrows on
        // non-422 failures, so swallow it to avoid an unhandled rejection.
    });
}

function selectImage(url: string) {
    form.image_url = url;
    imageFailed.value = false;
}

function dropSuggestedImage(url: string) {
    suggestedImages.value = suggestedImages.value.filter(
        (image) => image !== url,
    );
}

function submit() {
    if (props.method === 'put') {
        form.put(props.submitUrl);
    } else {
        form.post(props.submitUrl);
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <!-- Optional shortcut: try to auto-fill the form from a product link -->
        <div
            class="flex flex-col gap-2 rounded-xl border border-dashed border-border bg-secondary/40 p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-sm font-medium">Have a link to the product?</p>
                <p class="text-xs text-muted-foreground">
                    Try to fetch the details automatically, or just fill the form
                    in yourself.
                </p>
            </div>
            <Dialog v-model:open="fetchDialogOpen">
                <DialogTrigger as-child>
                    <Button type="button" variant="outline">
                        <Sparkles class="size-4" />
                        Fetch from a link
                    </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Fetch from a product link</DialogTitle>
                        <DialogDescription>
                            Paste a link and we'll try to fill in the title,
                            price and images. Some stores block this — if it
                            doesn't work, just close this and fill the form in
                            yourself.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-3">
                        <div class="flex gap-2">
                            <Input
                                v-model="form.url"
                                type="url"
                                placeholder="https://…"
                                class="flex-1"
                                @keydown.enter.prevent="fetchMetadata"
                            />
                            <Button
                                type="button"
                                :disabled="!form.url || metadata.processing"
                                @click="fetchMetadata"
                            >
                                <Spinner v-if="metadata.processing" />
                                <Sparkles v-else class="size-4" />
                                Fetch
                            </Button>
                        </div>

                        <div
                            v-if="fetchError"
                            class="flex items-start gap-2 rounded-lg border border-gold/40 bg-gold/10 px-3 py-2 text-sm text-foreground"
                        >
                            <TriangleAlert
                                class="mt-0.5 size-4 shrink-0 text-gold"
                            />
                            <span>{{ fetchError }}</span>
                        </div>

                        <p
                            v-if="fetchSuccess"
                            class="flex items-start gap-2 text-sm text-holly"
                        >
                            <Check class="mt-0.5 size-4 shrink-0" />
                            <span>{{ fetchSuccess }}</span>
                        </p>

                        <!-- Images pulled from the link — click one to use it -->
                        <div v-if="suggestedImages.length" class="grid gap-2">
                            <p class="text-xs text-muted-foreground">
                                Tap an image to use it.
                            </p>
                            <div
                                class="grid grid-cols-3 gap-2 sm:grid-cols-4"
                            >
                                <button
                                    v-for="image in suggestedImages"
                                    :key="image"
                                    type="button"
                                    class="group relative aspect-square overflow-hidden rounded-lg border-2 bg-muted transition"
                                    :class="
                                        form.image_url === image
                                            ? 'border-holly ring-2 ring-holly/30'
                                            : 'border-border hover:border-muted-foreground'
                                    "
                                    @click="selectImage(image)"
                                >
                                    <img
                                        :src="image"
                                        alt="Suggested product image"
                                        class="h-full w-full object-cover"
                                        @error="dropSuggestedImage(image)"
                                    />
                                    <span
                                        v-if="form.image_url === image"
                                        class="absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-holly text-white"
                                    >
                                        <Check class="size-3" />
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="fetchDialogOpen = false"
                        >
                            Close
                        </Button>
                        <Button type="button" @click="fetchDialogOpen = false">
                            Done
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <div class="grid gap-2">
            <Label for="title"
                >Title <span class="text-destructive">*</span></Label
            >
            <Input
                id="title"
                v-model="form.title"
                required
                autofocus
                placeholder="What do you want?"
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <Label for="description">Description</Label>
            <Textarea
                id="description"
                v-model="form.description"
                placeholder="Add some detail"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="url">Product URL</Label>
                <Input
                    id="url"
                    v-model="form.url"
                    type="url"
                    placeholder="https://…"
                />
                <InputError :message="form.errors.url" />
            </div>
            <div class="grid gap-2">
                <Label for="price">Price</Label>
                <Input
                    id="price"
                    v-model="form.price"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                />
                <InputError :message="form.errors.price" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="image_url">Image URL</Label>
            <Input
                id="image_url"
                v-model="form.image_url"
                type="url"
                placeholder="https://…"
                @input="imageFailed = false"
            />
            <InputError :message="form.errors.image_url" />

            <div
                v-if="form.image_url"
                class="mt-1 flex aspect-video max-w-xs items-center justify-center overflow-hidden rounded-lg border bg-muted"
            >
                <img
                    v-if="!imageFailed"
                    :src="form.image_url"
                    alt="Preview"
                    class="h-full w-full object-cover"
                    @error="imageFailed = true"
                />
                <ImageOff v-else class="size-6 text-muted-foreground" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="size">Size</Label>
                <Input
                    id="size"
                    v-model="form.size"
                    placeholder="M, 10.5, 32x30, One size…"
                />
                <InputError :message="form.errors.size" />
            </div>
            <div class="grid gap-2">
                <Label for="color">Color</Label>
                <Input
                    id="color"
                    v-model="form.color"
                    placeholder="Black, Dark Green, No preference…"
                />
                <InputError :message="form.errors.color" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="priority">Priority</Label>
                <select
                    id="priority"
                    v-model="form.priority"
                    :class="selectClass"
                >
                    <option
                        v-for="option in priorities"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="form.errors.priority" />
            </div>
            <div class="grid gap-2">
                <Label for="visibility_status">Visibility</Label>
                <select
                    id="visibility_status"
                    v-model="form.visibility_status"
                    :class="selectClass"
                >
                    <option
                        v-for="option in visibilities"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="form.errors.visibility_status" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="notes">Notes</Label>
            <Textarea
                id="notes"
                v-model="form.notes"
                placeholder="Anything else worth knowing"
            />
            <InputError :message="form.errors.notes" />
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ submitLabel }}
            </Button>
        </div>
    </form>
</template>
