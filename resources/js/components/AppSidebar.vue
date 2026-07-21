<script setup lang="ts">
// import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
// import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BookOpen,
    Folder,
    ChartPie,
    CalendarRange,
    Users,
    ChartBarStacked,
    Earth,
    FileStack,
    ClipboardList,
    Gauge,
    Map,
    ShieldCheck,
    Images,
    ClipboardCheck,
} from 'lucide-vue-next';
import { useFlash } from '@/composables/useFlash';
import { Toaster } from '@/components/ui/toast';
import AppLogo from './AppLogo.vue';

useFlash();

const page = usePage();

const isAdmin = computed(() => {
    const access = (page.props.auth as any)?.user?.access;
    return access === 'admin' || access === 'superadmin';
});

const isSuperAdmin = computed(() => (page.props.auth as any)?.user?.access === 'superadmin');

const isNhrdcMember = computed(() => (page.props.auth as any)?.isNhrdcMember === true);

const allNavItems: NavItem[] = [
    // Mga monitoring / tracking / compliance na page
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: ChartPie,
        color: 'text-indigo-600',
        group: 'Monitoring',
    },
    {
        title: 'Employees',
        url: '/employees',
        icon: Users,
        color: 'text-blue-500',
        group: 'Monitoring',
    },
    {
        title: 'TPMR Monitoring',
        url: '/tpmr',
        icon: ChartBarStacked,
        color: 'text-emerald-500',
        group: 'Monitoring',
    },
    {
        title: 'Requirements Tracker',
        url: '/requirements-tracker',
        icon: ClipboardList,
        color: 'text-rose-500',
        group: 'Monitoring',
    },
    {
        title: 'TNA Summary',
        url: '/tna-summary',
        icon: Gauge,
        color: 'text-indigo-500',
        group: 'Monitoring',
    },
    {
        title: 'Employees Map',
        url: '/employees-map',
        icon: Map,
        color: 'text-teal-500',
        group: 'Monitoring',
    },

    // Mga hindi monitoring — records/management na page
    {
        title: 'Programs',
        url: '/programs',
        icon: BookOpen,
        color: 'text-violet-500',
        group: 'Management',
    },
    {
        title: 'Calendar',
        url: '/calendar',
        icon: CalendarRange,
        color: 'text-cyan-500',
        group: 'Management',
    },
    {
        title: 'Foreign Programs',
        url: '/foreign-programs',
        icon: Earth,
        color: 'text-blue-600',
        group: 'Management',
    },
    {
        title: 'Supporting Docs',
        url: '/supporting-documents',
        icon: FileStack,
        color: 'text-amber-500',
        group: 'Management',
    },
];

const superAdminNavItems: NavItem[] = [
    {
        title: 'User Management',
        url: '/user-management',
        icon: ShieldCheck,
        color: 'text-rose-500',
        group: 'Management',
    },
    {
        title: 'Homepage Images',
        url: '/site-images',
        icon: Images,
        color: 'text-fuchsia-500',
        group: 'Management',
    },
];

const nhrdcNavItems: NavItem[] = [
    {
        title: 'Interview Ratings',
        url: '/nhrdc/programs',
        icon: ClipboardCheck,
        color: 'text-indigo-500',
        group: 'Management',
    },
];

const mainNavItems = computed<NavItem[]>(() => {
    let items: NavItem[] = [];
    if (isAdmin.value) {
        items = isSuperAdmin.value ? [...allNavItems, ...superAdminNavItems] : allNavItems;
    }
    if (isNhrdcMember.value) {
        items = [...items, ...nhrdcNavItems];
    }
    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
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
                        <Link :href="isAdmin ? route('dashboard') : route('home')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>

                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain v-if="isAdmin || isNhrdcMember" :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <!-- <NavUser /> -->
        </SidebarFooter>
    </Sidebar>
    <slot />

    <Toaster />
</template>