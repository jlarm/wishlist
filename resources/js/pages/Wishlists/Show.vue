<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Gift, Link2, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import WishlistItemCard from '@/components/WishlistItemCard.vue';
import { create as createItem } from '@/routes/wishlist-items';
import { show as wishlistShow } from '@/routes/wishlists';
import type { WishlistItem } from '@/types';

const props = defineProps<{
    owner: { id: number; name: string; is_me: boolean };
    items: WishlistItem[];
}>();

const search = ref('');
const sortBy = ref<'priority' | 'newest' | 'price_asc' | 'price_desc'>(
    'priority',
);
const priorityFilter = ref<string>('all');

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px]';

const visibleItems = computed(() => {
    let result = [...props.items];

    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        result = result.filter(
            (item) =>
                item.title.toLowerCase().includes(q) ||
                item.description?.toLowerCase().includes(q) ||
                item.color?.toLowerCase().includes(q) ||
                item.size?.toLowerCase().includes(q),
        );
    }

    if (priorityFilter.value !== 'all') {
        result = result.filter(
            (item) => item.priority === priorityFilter.value,
        );
    }

    result.sort((a, b) => {
        switch (sortBy.value) {
            case 'newest':
                return (b.created_at ?? '').localeCompare(a.created_at ?? '');
            case 'price_asc':
                return (
                    Number(a.price ?? Infinity) - Number(b.price ?? Infinity)
                );
            case 'price_desc':
                return (
                    Number(b.price ?? -Infinity) - Number(a.price ?? -Infinity)
                );
            default:
                return b.priority_weight - a.priority_weight;
        }
    });

    return result;
});

async function copyLink() {
    const url = new URL(
        wishlistShow(props.owner.id).url,
        window.location.origin,
    ).href;

    try {
        // navigator.clipboard is only available in secure contexts (https or
        // localhost), so fall back to execCommand on plain-http hosts.
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
        } else {
            copyWithFallback(url);
        }

        toast.success('Wishlist link copied to clipboard.');
    } catch {
        toast.error('Could not copy link.');
    }
}

function copyWithFallback(text: string) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    const copied = document.execCommand('copy');
    document.body.removeChild(textarea);

    if (!copied) {
        throw new Error('Copy command was rejected.');
    }
}
</script>

<template>
    <Head :title="owner.is_me ? 'My wishlist' : `${owner.name}'s wishlist`" />

    <div class="flex flex-col gap-6">
        <!-- Hero -->
        <div
            class="relative overflow-hidden rounded-3xl border-2 border-border bg-card px-6 py-7 backdrop-blur"
        >
            <p
                class="text-xs font-semibold tracking-[0.2em] text-gold uppercase"
            >
                {{
                    owner.is_me ? 'Your Christmas list' : 'Their Christmas list'
                }}
            </p>
            <div class="mt-1 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-semibold sm:text-4xl">
                        {{
                            owner.is_me
                                ? 'Dear Santa…'
                                : `${owner.name}'s wishes`
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ items.length }}
                        {{ items.length === 1 ? 'wish' : 'wishes' }} on the list
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="copyLink">
                        <Link2 class="size-4" />
                        Copy link
                    </Button>
                    <Button v-if="owner.is_me" as-child>
                        <Link :href="createItem()">
                            <Plus class="size-4" />
                            Add a wish
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div
            v-if="items.length"
            class="flex flex-wrap items-center gap-3 rounded-2xl border-2 border-border bg-card p-3"
        >
            <div class="relative flex-1 sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search items…"
                    class="pl-9"
                />
            </div>
            <select
                v-model="priorityFilter"
                :class="selectClass"
                aria-label="Filter by priority"
            >
                <option value="all">All priorities</option>
                <option value="most_wanted">Most wanted</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <select v-model="sortBy" :class="selectClass" aria-label="Sort by">
                <option value="priority">Sort: Priority</option>
                <option value="newest">Sort: Newest</option>
                <option value="price_asc">Sort: Price (low to high)</option>
                <option value="price_desc">Sort: Price (high to low)</option>
            </select>
        </div>

        <!-- Items -->
        <div
            v-if="visibleItems.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <WishlistItemCard
                v-for="item in visibleItems"
                :key="item.id"
                :item="item"
            />
        </div>

        <!-- Empty states -->
        <div
            v-else
            class="flex flex-1 flex-col items-center justify-center gap-3 rounded-3xl border-2 border-dashed border-border bg-card p-12 text-center"
        >
            <div class="rounded-full bg-holly/10 p-4 text-holly">
                <Gift class="size-8" />
            </div>
            <template v-if="items.length">
                <p class="font-display text-lg font-semibold">
                    Nothing matches
                </p>
                <p class="text-sm text-muted-foreground">
                    Try a different search or filter.
                </p>
            </template>
            <template v-else-if="owner.is_me">
                <p class="font-display text-lg font-semibold">
                    Your stocking's empty
                </p>
                <p class="text-sm text-muted-foreground">
                    Add the things you're hoping to find under the tree.
                </p>
                <Button as-child class="mt-2">
                    <Link :href="createItem()">
                        <Plus class="size-4" />
                        Add your first wish
                    </Link>
                </Button>
            </template>
            <template v-else>
                <p class="font-display text-lg font-semibold">No wishes yet</p>
                <p class="text-sm text-muted-foreground">
                    {{ owner.name }} hasn't added anything to their list.
                </p>
            </template>
        </div>
    </div>
</template>
