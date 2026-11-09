<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/Components/ui/sidebar';
import {
    History,
    type LucideIcon,
    FileText,
    HandCoins,
    Percent,
    KanbanSquare,
    AlertTriangle,
    ShieldCheck,
} from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { hasAccess } from '@/lib/auth';
defineProps<{
    projects: {
        name: string;
        url: string;
        icon: LucideIcon;
        roles: Array<string>;
    }[];
}>();
const page = usePage();
</script>
<template>
    <SidebarGroup class="group-data-[collapsible=icon]:hidden">
        <SidebarGroupLabel class="my-4">
            <img
                :src="page.props.auth.user.avatar ? page.props.auth.user.avatar : 'https://ui-avatars.com/api/?name=' + page.props.auth.user.name"
                alt="logo"
                class="w-10 h-10 rounded-full"
            />
            <div class="flex flex-col">
                <span class="text-xl text-sidebar-foreground ml-2">{{ page.props.auth.user.name }}</span>
                <span class="text-sm text-sidebar-foreground/70 ml-2">{{ page.props.auth.user.email }}</span>
            </div>
        </SidebarGroupLabel>

        <SidebarGroupLabel>Menú</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in projects" :key="item.name">
                <SidebarMenuButton
                    as-child
                    v-if="hasAccess(item.roles, page.props.auth.roles)"
                >
                    <a :href="item.url">
                        <component :is="item.icon" />
                        <span>{{ item.name }}</span>
                    </a>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>

        <template v-if="hasAccess(['Administrator', 'Advisor', 'Analista de crédito', 'Gerente'], page.props.auth.roles)">
            <SidebarGroupLabel>Agente</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child>
                        <a :href="route('loans.index')">
                            <HandCoins />
                            <span>Préstamos</span>
                        </a>
                    </SidebarMenuButton>
                    <SidebarMenuButton as-child>
                        <a :href="route('pipeline.index')">
                            <KanbanSquare />
                            <span>Pipeline</span>
                        </a>
                    </SidebarMenuButton>
                    <SidebarMenuButton as-child>
                        <a :href="route('portfolio.overdue')">
                            <AlertTriangle />
                            <span>Cartera vencida</span>
                        </a>
                    </SidebarMenuButton>
                    <SidebarMenuButton as-child>
                        <a :href="route('loans.history')">
                            <History />
                            <span>Historial Préstamos</span>
                        </a>
                    </SidebarMenuButton>
                    <SidebarMenuButton as-child>
                        <a :href="route('requests.index')">
                            <FileText />
                            <span>Solicitudes no revisadas</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>


            <SidebarGroupLabel>Estadisticas</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child>
                        <a :href="route('statistics.payments')">
                            <History />
                            <span>Pagos</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <SidebarGroupLabel>Configuración</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child>
                        <a :href="route('document-types.index')">
                            <History />
                            <span>Tipos de Documentos</span>
                        </a>
                    </SidebarMenuButton>
                    <SidebarMenuButton v-if="hasAccess(['Administrator'], page.props.auth.roles)" as-child>
                        <a :href="route('admin.loan-products.index')">
                            <Percent />
                            <span>Productos y tasas</span>
                        </a>
                    </SidebarMenuButton>
                    <SidebarMenuButton v-if="hasAccess(['Administrator'], page.props.auth.roles)" as-child>
                        <a :href="route('admin.audit.index')">
                            <ShieldCheck />
                            <span>Bitácora de auditoría</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </template>
    </SidebarGroup>
</template>
