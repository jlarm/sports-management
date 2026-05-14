<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarRange,
    LayoutGrid,
    Layers,
    MapPin,
    UserPlus,
    UsersRound,
} from 'lucide-vue-next';
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
import { index as divisionsIndex } from '@/routes/divisions';
import { index as invitationsIndex } from '@/routes/invitations';
import { index as locationsIndex } from '@/routes/locations';
import { index as playersIndex } from '@/routes/players';
import { index as seasonsIndex } from '@/routes/seasons';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const organizationNavItems: NavItem[] = [
    {
        title: 'Players',
        href: playersIndex(),
        icon: UsersRound,
    },
    {
        title: 'Seasons',
        href: seasonsIndex(),
        icon: CalendarRange,
    },
    {
        title: 'Divisions',
        href: divisionsIndex(),
        icon: Layers,
    },
    {
        title: 'Locations',
        href: locationsIndex(),
        icon: MapPin,
    },
    {
        title: 'Invitations',
        href: invitationsIndex(),
        icon: UserPlus,
    },
];
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
            <NavMain :items="organizationNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
