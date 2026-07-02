<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    ChevronDown,
    Gift,
    Globe,
    LayoutGrid,
    Link2,
    Plus,
    RotateCw,
    Search,
    Table as TableIcon,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import WishlistShareController from '@/actions/App/Http/Controllers/WishlistShareController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import WishlistItemCard from '@/components/WishlistItemCard.vue';
import WishlistItemTable from '@/components/WishlistItemTable.vue';
import { useWindowedList } from '@/composables/useWindowedList';
import { create as createItem } from '@/routes/wishlist-items';
import { show as wishlistShow } from '@/routes/wishlists';
import type { WishlistItem } from '@/types';

const props = defineProps<{
    owner: {
        id: number;
        name: string;
        is_me: boolean;
        share_token: string | null;
    };
    items: WishlistItem[];
    people: { id: number; name: string; is_me: boolean }[];
}>();

const shareProcessing = ref(false);

// The public, no-login URL for this list, or null when sharing is off.
const publicUrl = computed(() =>
    props.owner.share_token
        ? new URL(`/shared/${props.owner.share_token}`, window.location.origin)
              .href
        : null,
);

function enableSharing() {
    shareProcessing.value = true;
    router.post(
        WishlistShareController.store.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => (shareProcessing.value = false),
        },
    );
}

function disableSharing() {
    shareProcessing.value = true;
    router.delete(WishlistShareController.destroy.url(), {
        preserveScroll: true,
        onFinish: () => (shareProcessing.value = false),
    });
}

async function copyPublicLink() {
    if (!publicUrl.value) {
        return;
    }

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(publicUrl.value);
        } else {
            copyWithFallback(publicUrl.value);
        }

        toast.success('Public link copied to clipboard.');
    } catch {
        toast.error('Could not copy link.');
    }
}

const search = ref('');
const sortBy = ref<'priority' | 'newest' | 'price_asc' | 'price_desc'>(
    'priority',
);
const priorityFilter = ref<string>('all');
const tagFilter = ref<string>('all');
const domainFilter = ref<string>('all');
// Only meaningful on someone else's list — the owner never sees claim state.
const hidePurchased = ref(false);

// The retailer host for an item's link (e.g. "amazon.com"), or null when it has
// no (parseable) URL. "www." is stripped so links group under one domain.
function itemDomain(url: string | null): string | null {
    if (!url) {
        return null;
    }

    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return null;
    }
}

// Card vs table layout, remembered across visits.
type ViewMode = 'cards' | 'table';
const STORAGE_KEY = 'wishlist_view';
const view = ref<ViewMode>(
    (typeof localStorage !== 'undefined' &&
        (localStorage.getItem(STORAGE_KEY) as ViewMode)) ||
        'cards',
);
watch(view, (mode) => {
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem(STORAGE_KEY, mode);
    }
});

// Every distinct tag across the list, for the filter dropdown.
const allTags = computed(() => {
    const tags = new Set<string>();
    props.items.forEach((item) => item.tags.forEach((tag) => tags.add(tag)));

    return [...tags].sort((a, b) => a.localeCompare(b));
});

// Every distinct retailer domain across the list, for the filter dropdown.
const allDomains = computed(() => {
    const domains = new Set<string>();
    props.items.forEach((item) => {
        const domain = itemDomain(item.url);

        if (domain) {
            domains.add(domain);
        }
    });

    return [...domains].sort((a, b) => a.localeCompare(b));
});

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

    if (tagFilter.value !== 'all') {
        result = result.filter((item) => item.tags.includes(tagFilter.value));
    }

    if (domainFilter.value !== 'all') {
        result = result.filter(
            (item) => itemDomain(item.url) === domainFilter.value,
        );
    }

    if (!props.owner.is_me && hidePurchased.value) {
        result = result.filter((item) => !item.is_purchased);
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

// Infinite scroll over the filtered list, restarting at the top whenever a
// filter changes (but not when the items prop refreshes after a claim action).
const {
    displayed: displayedItems,
    hasMore,
    anchor: loadMoreAnchor,
    reset: resetWindow,
} = useWindowedList(visibleItems);

watch(
    [search, sortBy, priorityFilter, tagFilter, domainFilter, hidePurchased],
    resetWindow,
);

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
                                ? 'My wishlist'
                                : `${owner.name}'s wishes`
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ items.length }}
                        {{ items.length === 1 ? 'wish' : 'wishes' }} on the list
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <!-- Jump straight to anyone else's list -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline">
                                <Users class="size-4" />
                                Switch list
                                <ChevronDown class="size-4 opacity-60" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            class="max-h-80 w-56 overflow-y-auto"
                        >
                            <DropdownMenuLabel
                                >Jump to a list</DropdownMenuLabel
                            >
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="person in people"
                                :key="person.id"
                                as-child
                            >
                                <Link
                                    :href="wishlistShow(person.id)"
                                    class="flex w-full cursor-pointer items-center justify-between gap-2"
                                >
                                    <span class="truncate">
                                        {{ person.name
                                        }}{{ person.is_me ? ' (you)' : '' }}
                                    </span>
                                    <Check
                                        v-if="person.id === owner.id"
                                        class="size-4 shrink-0"
                                    />
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button variant="outline" @click="copyLink">
                        <Link2 class="size-4" />
                        Copy link
                    </Button>

                    <Dialog v-if="owner.is_me">
                        <DialogTrigger as-child>
                            <Button variant="outline">
                                <Globe class="size-4" />
                                Share publicly
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle
                                    >Share your list publicly</DialogTitle
                                >
                                <DialogDescription>
                                    Create a read-only link anyone can open — no
                                    account needed. It never shows who's claimed
                                    what, so it's safe to share with family.
                                </DialogDescription>
                            </DialogHeader>

                            <div v-if="publicUrl" class="grid gap-3">
                                <div class="flex gap-2">
                                    <Input
                                        :model-value="publicUrl"
                                        readonly
                                        class="flex-1"
                                        @focus="
                                            (e: FocusEvent) =>
                                                (
                                                    e.target as HTMLInputElement
                                                ).select()
                                        "
                                    />
                                    <Button
                                        type="button"
                                        @click="copyPublicLink"
                                    >
                                        <Link2 class="size-4" />
                                        Copy
                                    </Button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        :disabled="shareProcessing"
                                        @click="enableSharing"
                                    >
                                        <RotateCw class="size-4" />
                                        Regenerate
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        :disabled="shareProcessing"
                                        @click="disableSharing"
                                    >
                                        <X class="size-4" />
                                        Turn off
                                    </Button>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Regenerating makes the old link stop
                                    working.
                                </p>
                            </div>

                            <div v-else class="grid gap-3">
                                <p class="text-sm text-muted-foreground">
                                    Public sharing is off. Create a link to let
                                    people outside the group see your list.
                                </p>
                                <Button
                                    type="button"
                                    :disabled="shareProcessing"
                                    @click="enableSharing"
                                >
                                    <Globe class="size-4" />
                                    Create public link
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>

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
            <select
                v-if="allTags.length"
                v-model="tagFilter"
                :class="selectClass"
                aria-label="Filter by tag"
            >
                <option value="all">All tags</option>
                <option v-for="tag in allTags" :key="tag" :value="tag">
                    {{ tag }}
                </option>
            </select>
            <select
                v-if="allDomains.length"
                v-model="domainFilter"
                :class="selectClass"
                aria-label="Filter by store"
            >
                <option value="all">All stores</option>
                <option
                    v-for="domain in allDomains"
                    :key="domain"
                    :value="domain"
                >
                    {{ domain }}
                </option>
            </select>
            <select v-model="sortBy" :class="selectClass" aria-label="Sort by">
                <option value="priority">Sort: Priority</option>
                <option value="newest">Sort: Newest</option>
                <option value="price_asc">Sort: Price (low to high)</option>
                <option value="price_desc">Sort: Price (high to low)</option>
            </select>

            <!-- Hide already-claimed items (never shown on your own list) -->
            <label
                v-if="!owner.is_me"
                class="flex cursor-pointer items-center gap-2 text-sm"
            >
                <Checkbox v-model="hidePurchased" />
                Hide purchased
            </label>

            <!-- Card / table view toggle -->
            <div
                class="ml-auto inline-flex rounded-md border border-input p-0.5"
                role="group"
                aria-label="View mode"
            >
                <Button
                    type="button"
                    :variant="view === 'cards' ? 'secondary' : 'ghost'"
                    size="sm"
                    aria-label="Card view"
                    :aria-pressed="view === 'cards'"
                    @click="view = 'cards'"
                >
                    <LayoutGrid class="size-4" />
                </Button>
                <Button
                    type="button"
                    :variant="view === 'table' ? 'secondary' : 'ghost'"
                    size="sm"
                    aria-label="Table view"
                    :aria-pressed="view === 'table'"
                    @click="view = 'table'"
                >
                    <TableIcon class="size-4" />
                </Button>
            </div>
        </div>

        <!-- Items -->
        <template v-if="visibleItems.length">
            <div
                v-if="view === 'cards'"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <WishlistItemCard
                    v-for="item in displayedItems"
                    :key="item.id"
                    :item="item"
                />
            </div>
            <WishlistItemTable v-else :items="displayedItems" />

            <!-- Infinite-scroll trigger -->
            <div
                v-if="hasMore"
                ref="loadMoreAnchor"
                class="h-1 w-full"
                aria-hidden="true"
            />
        </template>

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
