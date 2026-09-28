<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, GripVertical } from '@lucide/vue';
import { ref } from 'vue';
import WishlistOrderController from '@/actions/App/Http/Controllers/WishlistOrderController';
import { Button } from '@/components/ui/button';
import type { WishlistItem } from '@/types';

const props = defineProps<{
    items: WishlistItem[];
}>();

const emit = defineEmits<{
    (e: 'done'): void;
}>();

// Work on a local copy so Cancel leaves the page untouched.
const ordered = ref<WishlistItem[]>(
    [...props.items].sort((a, b) => a.position - b.position),
);
const draggingIndex = ref<number | null>(null);
const saving = ref(false);

function move(from: number, to: number) {
    if (to < 0 || to >= ordered.value.length || from === to) {
        return;
    }

    const next = [...ordered.value];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);
    ordered.value = next;
}

// Native drag-and-drop for mouse users; the arrow buttons cover touch screens.
function onDragStart(index: number, event: DragEvent) {
    draggingIndex.value = index;
    event.dataTransfer?.setData('text/plain', String(index));
}

function onDragEnter(index: number) {
    if (draggingIndex.value === null || draggingIndex.value === index) {
        return;
    }

    move(draggingIndex.value, index);
    draggingIndex.value = index;
}

function save() {
    saving.value = true;
    router.put(
        WishlistOrderController.update().url,
        { ids: ordered.value.map((item) => item.id) },
        {
            preserveScroll: true,
            onSuccess: () => emit('done'),
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-2xl border-2 border-border bg-card p-3"
    >
        <div class="flex flex-wrap items-center justify-between gap-2 px-1">
            <p class="text-sm text-muted-foreground">
                Drag items (or use the arrows) so your favourites come first.
                Everyone sees your list in this order.
            </p>
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="saving"
                    @click="emit('done')"
                >
                    Cancel
                </Button>
                <Button type="button" :disabled="saving" @click="save">
                    Save order
                </Button>
            </div>
        </div>

        <ol class="flex flex-col gap-1.5">
            <li
                v-for="(item, index) in ordered"
                :key="item.id"
                draggable="true"
                class="flex items-center gap-3 rounded-xl border border-border bg-background px-2 py-2 transition"
                :class="{ 'opacity-50': draggingIndex === index }"
                @dragstart="onDragStart(index, $event)"
                @dragenter.prevent="onDragEnter(index)"
                @dragover.prevent
                @dragend="draggingIndex = null"
            >
                <GripVertical
                    class="size-4 shrink-0 cursor-grab text-muted-foreground"
                />
                <span
                    class="w-6 shrink-0 text-right text-xs font-semibold text-muted-foreground tabular-nums"
                >
                    {{ index + 1 }}
                </span>
                <img
                    :src="item.image_url ?? '/tree.webp'"
                    :alt="item.title"
                    class="size-9 shrink-0 rounded-md bg-muted object-cover"
                />
                <span class="min-w-0 flex-1 truncate text-sm font-medium">
                    {{ item.title }}
                </span>
                <div class="flex shrink-0">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :aria-label="`Move ${item.title} up`"
                        :disabled="index === 0"
                        @click="move(index, index - 1)"
                    >
                        <ArrowUp class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :aria-label="`Move ${item.title} down`"
                        :disabled="index === ordered.length - 1"
                        @click="move(index, index + 1)"
                    >
                        <ArrowDown class="size-4" />
                    </Button>
                </div>
            </li>
        </ol>
    </div>
</template>
