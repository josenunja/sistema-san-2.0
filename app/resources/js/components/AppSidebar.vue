<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BookOpen,
    FolderGit2,
    LayoutGrid,
    Store,
    ShieldCheck,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import type { NavItem } from '@/types';

const page = usePage();
const mainNavItems = computed<NavItem[]>(() => [
    ...(page.props.platformAccess
        ? [
              {
                  title: 'Administración general',
                  href: page.props.platformAccess.requiresMfa
                      ? '/settings/security'
                      : page.props.platformAccess.manageUrl,
                  icon: ShieldCheck,
              },
          ]
        : []),
    {
        title: 'Mi negocio',
        href: page.props.businessAccess?.requiresMfa
            ? '/settings/security'
            : dashboard(),
        icon: LayoutGrid,
    },
    ...(page.props.businessAccess
        ? [
              {
                  title: 'Ver mi tienda',
                  href: page.props.businessAccess.storeUrl,
                  icon: Store,
              },
              {
                  title: 'Seguridad de mi cuenta',
                  href: '/settings/security',
                  icon: ShieldCheck,
              },
          ]
        : []),
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Código del sistema',
        href: 'https://github.com/josenunja/sistema-san-2.0',
        icon: FolderGit2,
    },
    {
        title: 'Ayuda',
        href: '/dashboard',
        icon: BookOpen,
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
