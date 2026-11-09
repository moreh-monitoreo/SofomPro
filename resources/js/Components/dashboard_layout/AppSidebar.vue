<script setup lang="ts">
import NavProjects from '@/Components/dashboard_layout/NavProjects.vue';
import TeamSwitcher from '@/Components/dashboard_layout/TeamSwitcher.vue';
import type { SidebarProps } from '@/Components/ui/sidebar';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarRail,
} from '@/Components/ui/sidebar';
import { GalleryVerticalEnd, House, HandCoins } from 'lucide-vue-next';
const props = withDefaults(defineProps<SidebarProps>(), {
    collapsible: 'icon',
});
const data = {
    teams: [
        {
            name: 'SOFOM Pro+',
            logo: GalleryVerticalEnd,
            plan: 'Dashboard',
        },
    ],
    projects: [
        {
            name: 'Inicio',
            url: '/dashboard',
            icon: House,
            roles: ['Administrator', 'Advisor', 'Analista de crédito', 'Gerente', 'Customer'],
        },
        {
            name: 'Perfil',
            url: '/profile',
            icon: HandCoins,
            roles: ['Administrator', 'Advisor', 'Analista de crédito', 'Gerente', 'Customer'],
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
            <NavProjects :projects="data.projects" />
        </SidebarContent>
        <SidebarRail />
    </Sidebar>
</template>
