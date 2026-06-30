<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Gift, LayoutDashboard, Sparkles, TreePine, Users } from '@lucide/vue';
import { computed } from 'vue';
import Snowfall from '@/components/Snowfall.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes';
import {
    index as wishlistsIndex,
    show as wishlistShow,
} from '@/routes/wishlists';
import type { User } from '@/types';

const page = usePage<{ auth: { user: User } }>();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.is_admin === true);

const { isCurrentUrl } = useCurrentUrl();
const { getInitials } = useInitials();

const myList = computed(() => wishlistShow(user.value.id as number));

const navLink =
    'flex items-center gap-1.5 rounded-full px-3 py-2 font-medium transition hover:bg-white/10';
</script>

<template>
    <div class="candlelight relative flex min-h-svh flex-col">
        <Snowfall />

        <header
            class="sticky top-0 z-30 border-b border-white/10 bg-[hsl(158_44%_8%)]/85 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4"
            >
                <Link
                    :href="myList"
                    class="flex items-center gap-2.5"
                    aria-label="Home"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-full bg-holly text-white shadow-sm ring-2 ring-gold/50"
                    >
                        <TreePine class="size-5" />
                    </span>
                    <span
                        class="font-display text-xl leading-none font-semibold tracking-tight text-white"
                    >
                        Wishlist
                    </span>
                </Link>

                <nav class="flex items-center gap-1 text-sm">
                    <Link
                        :href="myList"
                        :class="[
                            navLink,
                            isCurrentUrl(myList.url)
                                ? 'bg-white/15 text-white'
                                : 'text-white/70',
                        ]"
                    >
                        <Gift class="size-4" />
                        <span class="hidden sm:inline">My list</span>
                    </Link>
                    <Link
                        :href="wishlistsIndex()"
                        :class="[
                            navLink,
                            isCurrentUrl(wishlistsIndex().url)
                                ? 'bg-white/15 text-white'
                                : 'text-white/70',
                        ]"
                    >
                        <Users class="size-4" />
                        <span class="hidden sm:inline">Everyone</span>
                    </Link>
                    <Link
                        v-if="isAdmin"
                        :href="dashboard()"
                        :class="[navLink, 'text-white/70']"
                    >
                        <LayoutDashboard class="size-4" />
                        <span class="hidden sm:inline">Dashboard</span>
                    </Link>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                class="ml-1 flex items-center gap-2 rounded-full border border-white/15 bg-white/10 py-1 pr-1 pl-3 text-white transition hover:bg-white/15"
                                aria-label="Account menu"
                            >
                                <span
                                    class="hidden text-sm font-medium md:inline"
                                >
                                    {{ user.name }}
                                </span>
                                <span
                                    class="flex size-8 items-center justify-center rounded-full bg-gold text-xs font-semibold text-[hsl(158_44%_10%)]"
                                >
                                    {{ getInitials(user.name) }}
                                </span>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-56" align="end">
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </nav>
            </div>
            <div class="garland-stripe h-1.5 w-full opacity-90" />
        </header>

        <main class="relative z-10 mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <slot />
        </main>

        <footer
            class="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-center gap-2 px-4 py-6 text-xs text-white/55"
        >
            <Sparkles class="size-3.5 text-gold" />
            <span>Made merry for your group. Surprises stay secret.</span>
        </footer>

        <Toaster />
    </div>
</template>
