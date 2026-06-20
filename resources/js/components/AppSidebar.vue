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
    Earth
} from 'lucide-vue-next';
import { useFlash } from '@/composables/useFlash';
import { Toaster } from '@/components/ui/toast';
import AppLogo from './AppLogo.vue';

useFlash();

const page = usePage();

// ⚠️ I-verify: 'admin' ba talaga ang eksaktong value ng `access` column.
const isAdmin = computed(() => (page.props.auth as any)?.user?.access === 'admin');

const allNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: ChartPie,
        color: 'text-indigo-600',
    },

    {
        title: 'Programs',
        url: '/programs',
        icon: BookOpen,
        color: 'text-violet-500',
    },

    {
        title: 'Calendar',
        url: '/calendar',
        icon: CalendarRange,
        color: 'text-cyan-500',
    },

    {
        title: 'Employees',
        url: '/employees',
        icon: Users,
        color: 'text-blue-500',
    },

    {
        title: 'TPMR Monitoring',
        url: '/tpmr',
        icon: ChartBarStacked,
        color: 'text-emerald-500',
    },

    {
        title: 'Foreign Programs',
        url: '/foreign-programs',
        icon: Earth,
        color: 'text-blue-600',
    },
];

// ✅ Admin-only ang lahat ng links na 'to (lahat naka-gate sa 'admin'
// middleware sa routes/web.php), kaya itago kapag hindi admin ang naka-login.
const mainNavItems = computed<NavItem[]>(() => isAdmin.value ? allNavItems : []);

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
            <NavMain v-if="isAdmin" :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <!-- <NavUser /> -->
        </SidebarFooter>
    </Sidebar>
    <slot />

    <Toaster />
</template>