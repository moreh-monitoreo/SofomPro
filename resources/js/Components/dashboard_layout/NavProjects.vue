<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/Components/ui/sidebar';
import {
    Folder,
    Forward,
    History,
    type LucideIcon,
    MoreHorizontal,
    Trash2,
    FileText,
    HandCoins,
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
const { isMobile } = useSidebar();

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
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <SidebarMenuAction show-on-hover>
                            <MoreHorizontal />
                            <span class="sr-only">More</span>
                        </SidebarMenuAction>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        class="w-48 rounded-lg"
                        :side="isMobile ? 'bottom' : 'right'"
                        :align="isMobile ? 'end' : 'start'"
                    >
                        <DropdownMenuItem>
                            <Folder class="text-muted-foreground" />
                            <span>View Project</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem>
                            <Forward class="text-muted-foreground" />
                            <span>Share Project</span>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem>
                            <Trash2 class="text-muted-foreground" />
                            <span>Delete Project</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton class="text-sidebar-foreground/70">
                    <MoreHorizontal class="text-sidebar-foreground/70" />
                    <span>More</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>

        <template v-if="hasAccess(['Administrator', 'Advisor'], page.props.auth.roles)">
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
                </SidebarMenuItem>
            </SidebarMenu>
        </template>
    </SidebarGroup>
</template>
