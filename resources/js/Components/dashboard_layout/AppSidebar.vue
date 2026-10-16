<script setup lang="ts">
import NavMain from '@/Components/dashboard_layout/NavMain.vue';
import NavProjects from '@/Components/dashboard_layout/NavProjects.vue';
import NavUser from '@/Components/dashboard_layout/NavUser.vue';
import TeamSwitcher from '@/Components/dashboard_layout/TeamSwitcher.vue';
import type { SidebarProps } from '@/Components/ui/sidebar';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
} from '@/Components/ui/sidebar';
import {
    AudioWaveform,
    BookOpen,
    Bot,
    Command,
    Frame,
    GalleryVerticalEnd,
    Map,
    PieChart,
    Settings2,
    SquareTerminal,
    House,
    HandCoins,
} from 'lucide-vue-next';
const props = withDefaults(defineProps<SidebarProps>(), {
    collapsible: 'icon',
});
// This is sample data.
const data = {
    user: {
        name: 'shadcn',
        email: 'm@example.com',
        avatar: '/avatars/shadcn.jpg',
    },
    teams: [
        {
            name: 'SOFOM Pro+',
            logo: GalleryVerticalEnd,
            plan: 'Dashboard',
        },
    ],
    navMain: [
        {
            title: 'Playground',
            url: '#',
            icon: SquareTerminal,
            isActive: true,
            items: [
                {
                    title: 'History',
                    url: '#',
                },
                {
                    title: 'Starred',
                    url: '#',
                },
                {
                    title: 'Settings',
                    url: '#',
                },
            ],
        },
        {
            title: 'Models',
            url: '#',
            icon: Bot,
            items: [
                {
                    title: 'Genesis',
                    url: '#',
                },
                {
                    title: 'Explorer',
                    url: '#',
                },
                {
                    title: 'Quantum',
                    url: '#',
                },
            ],
        },
        {
            title: 'Documentation',
            url: '#',
            icon: BookOpen,
            items: [
                {
                    title: 'Introduction',
                    url: '#',
                },
                {
                    title: 'Get Started',
                    url: '#',
                },
                {
                    title: 'Tutorials',
                    url: '#',
                },
                {
                    title: 'Changelog',
                    url: '#',
                },
            ],
        },
        {
            title: 'Settings',
            url: '#',
            icon: Settings2,
            items: [
                {
                    title: 'General',
                    url: '#',
                },
                {
                    title: 'Team',
                    url: '#',
                },
                {
                    title: 'Billing',
                    url: '#',
                },
                {
                    title: 'Limits',
                    url: '#',
                },
            ],
        },
    ],
    projects: [
        {
            name: 'Inicio',
            url: '/dashboard',
            icon: House,
            roles: ['Administrator', 'Advisor', 'Customer'],
        },
        {
            name: 'Perfil',
            url: '/profile',
            icon: HandCoins,
            roles: ['Administrator', 'Advisor', 'Customer'],
        },
    ],
};
</script>
<template>
    <Sidebar v-bind="props">
        <SidebarHeader>
            <TeamSwitcher :teams="data.teams" />
        </SidebarHeader>
        <SidebarContent>
            <!-- <NavUser :user="data.user" />
            <NavMain :items="data.navMain" /> -->
            <NavProjects :projects="data.projects" />
        </SidebarContent>
        <!-- <SidebarFooter>
        </SidebarFooter> -->
        <SidebarRail />
    </Sidebar>
</template>
