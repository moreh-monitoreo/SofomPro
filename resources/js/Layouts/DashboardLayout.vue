<script lang="ts">
export const description = 'A sidebar that collapses to icons.';
export const iframeHeight = '800px';
export const containerClass = 'w-full h-full';
</script>
<script setup lang="ts">
import AppSidebar from '@/Components/dashboard_layout/AppSidebar.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { Separator } from '@/Components/ui/separator';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/Components/ui/sidebar';
</script>
<template>
    <SidebarProvider>
        <AppSidebar />
        <SidebarInset>
            <header
                class="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 bg-vermilion-500 text-white"
            >
                <div class="flex items-center gap-2 px-4">
                    <SidebarTrigger class="-ml-1 text-white" />
                    <Separator orientation="vertical" class="mr-2 h-4 bg-white/20" />
                    <Breadcrumb>
                        <BreadcrumbList>
                            <BreadcrumbItem class="hidden md:block">
                                <BreadcrumbLink href="#" class="text-white hover:text-white/80">
                                    SOFOM Pro+
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator class="hidden md:block text-white/50" />
                            <BreadcrumbItem>
                                <BreadcrumbPage class="text-white">Acceso Cliente</BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
                <div>
                    <DropdownLink
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="text-white hover:text-white/80"
                    >
                        Log Out
                    </DropdownLink>
                </div>
            </header>
            <slot></slot>
        </SidebarInset>
    </SidebarProvider>
</template>
