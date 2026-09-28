<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Bookmark, ChevronDown, Smartphone } from '@lucide/vue';
import { computed } from 'vue';
import { store } from '@/actions/App/Http/Controllers/WishlistItemController';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import WishlistItemForm from '@/components/WishlistItemForm.vue';
import { bookmarkletHref, readSharedProduct } from '@/lib/bookmarklet';
import { create } from '@/routes/wishlist-items';
import type { SelectOption } from '@/types';

const props = defineProps<{
    priorities: SelectOption[];
    visibilities: SelectOption[];
    // A link handed over by the bookmarklet or the phone's share sheet.
    prefill: { url: string; title: string | null } | null;
}>();

// Reads the product off any store page and opens this page with it.
const bookmarklet = computed(() =>
    bookmarkletHref(new URL(create().url, window.location.origin).href),
);

// Details the bookmarklet read off the store page, carried in the fragment.
const sharedDetails = props.prefill
    ? readSharedProduct(window.location.hash)
    : null;
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
                :initial="
                    props.prefill ? { url: props.prefill.url } : undefined
                "
                :auto-fetch="!!props.prefill"
                :fallback-title="props.prefill?.title"
                :shared-details="sharedDetails"
            />
        </div>

        <!-- Quick-add shortcuts -->
        <Collapsible
            class="rounded-3xl border-2 border-border bg-card px-6 py-4"
        >
            <CollapsibleTrigger
                class="group flex w-full items-center justify-between text-left text-sm font-medium"
            >
                Add wishes straight from any store
                <ChevronDown
                    class="size-4 transition group-data-[state=open]:rotate-180"
                />
            </CollapsibleTrigger>
            <CollapsibleContent class="mt-4 grid gap-4 text-sm">
                <div class="flex gap-3">
                    <Bookmark class="mt-0.5 size-4 shrink-0 text-gold" />
                    <div class="grid gap-2">
                        <p>
                            <span class="font-medium">On a computer:</span>
                            drag this button to your bookmarks bar. On any
                            product page, click it to add that product here — it
                            even works on stores like Amazon. Already have the
                            old one? Drag this one over it.
                        </p>
                        <a
                            :href="bookmarklet"
                            class="inline-flex w-fit items-center gap-1.5 rounded-full bg-holly px-3 py-1.5 text-xs font-semibold text-white shadow-sm"
                            @click.prevent
                        >
                            <Bookmark class="size-3.5" />
                            + Wishlist
                        </a>
                    </div>
                </div>
                <div class="flex gap-3">
                    <Smartphone class="mt-0.5 size-4 shrink-0 text-gold" />
                    <p>
                        <span class="font-medium">On Android:</span> open this
                        site in Chrome and choose <em>Add to Home screen</em>.
                        After that, Wishlist shows up in the Share menu of any
                        shopping app or browser.
                    </p>
                </div>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
