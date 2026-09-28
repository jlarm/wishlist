<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarHeart, Trash2 } from '@lucide/vue';
import OccasionController from '@/actions/App/Http/Controllers/Settings/OccasionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/occasions';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Occasions',
                href: edit(),
            },
        ],
    },
});

type Occasion = {
    id: number;
    name: string;
    date: string;
    recurs_annually: boolean;
    next_date: string | null;
};

defineProps<{
    occasions: Occasion[];
}>();

const form = useForm({
    name: '',
    date: '',
    recurs_annually: true,
});

function submit() {
    form.post(OccasionController.store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(occasion: Occasion) {
    router.delete(OccasionController.destroy(occasion.id).url, {
        preserveScroll: true,
    });
}

// Dates arrive as plain YYYY-MM-DD; parse as local time so they don't shift a day.
function formatDate(value: string, withYear: boolean): string {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        ...(withYear ? { year: 'numeric' } : {}),
    });
}
</script>

<template>
    <Head title="Occasions" />

    <h1 class="sr-only">Occasions</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Occasions"
            description="Birthdays, Christmas and other dates your list is for. Everyone sees a countdown, and gift-givers get a reminder 30 and 7 days before."
        />

        <ul v-if="occasions.length" class="space-y-2">
            <li
                v-for="occasion in occasions"
                :key="occasion.id"
                class="flex items-center justify-between gap-3 rounded-xl border border-border bg-card p-4"
            >
                <div class="flex items-center gap-3">
                    <CalendarHeart class="size-5 text-gold" />
                    <div>
                        <p class="text-sm font-medium">{{ occasion.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="occasion.recurs_annually">
                                Every {{ formatDate(occasion.date, false) }}
                            </template>
                            <template v-else>
                                {{ formatDate(occasion.date, true) }}
                            </template>
                            <template v-if="!occasion.next_date">
                                · passed
                            </template>
                        </p>
                    </div>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :aria-label="`Remove ${occasion.name}`"
                    @click="remove(occasion)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </li>
        </ul>

        <form
            class="space-y-4 rounded-xl border border-border bg-card p-4"
            @submit.prevent="submit"
        >
            <p class="text-sm font-medium">Add an occasion</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="occasion-name">Name</Label>
                    <Input
                        id="occasion-name"
                        v-model="form.name"
                        required
                        maxlength="100"
                        placeholder="Birthday"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="occasion-date">Date</Label>
                    <Input
                        id="occasion-date"
                        v-model="form.date"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.date" />
                </div>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <Checkbox v-model="form.recurs_annually" />
                Repeats every year
            </label>

            <Button :disabled="form.processing">Add occasion</Button>
        </form>
    </div>
</template>
