import { useIntersectionObserver } from '@vueuse/core';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export type UseWindowedListReturn<T> = {
    /** The current slice to render. */
    displayed: ComputedRef<T[]>;
    /** Whether more items remain beyond the current slice. */
    hasMore: ComputedRef<boolean>;
    /** Attach to a sentinel element rendered below the list. */
    anchor: Ref<HTMLElement | null>;
    /** Shrink the window back to the first page (call when the list changes). */
    reset: () => void;
};

/**
 * Render a large in-memory list in growing slices as a sentinel element scrolls
 * into view — infinite scroll without server round-trips. The whole list stays
 * in memory (so client-side search/sort/filter keep working); only the number
 * of rendered items grows.
 */
export function useWindowedList<T>(
    source: Ref<T[]> | ComputedRef<T[]>,
    pageSize = 24,
): UseWindowedListReturn<T> {
    const renderLimit = ref(pageSize);

    const displayed = computed(() => source.value.slice(0, renderLimit.value));
    const hasMore = computed(() => renderLimit.value < source.value.length);

    const reset = () => {
        renderLimit.value = pageSize;
    };

    const anchor = ref<HTMLElement | null>(null);
    useIntersectionObserver(
        anchor,
        ([entry]) => {
            if (entry?.isIntersecting && hasMore.value) {
                renderLimit.value += pageSize;
            }
        },
        { rootMargin: '600px' },
    );

    return { displayed, hasMore, anchor, reset };
}
