<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Gift, LayoutGrid, Mail, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as invitationsIndex } from '@/routes/admin/invitations';
import { index as usersIndex } from '@/routes/admin/users';
import { index as wishlistsIndex } from '@/routes/wishlists';
import type { NavItem, User } from '@/types';

const page = usePage<{ auth: { user: User } }>();
const isAdmin = computed(() => page.props.auth.user?.is_admin === true);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (isAdmin.value) {
        items.push({ title: 'Dashboard', href: dashboard(), icon: LayoutGrid });
    }

    items.push({ title: 'Wishlists', href: wishlistsIndex(), icon: Gift });

    if (isAdmin.value) {
        items.push(
            { title: 'Invitations', href: invitationsIndex(), icon: Mail },
            { title: 'Users', href: usersIndex(), icon: Users },
        );
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
