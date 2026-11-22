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
    useSidebar,
} from '@/components/ui/sidebar';
import { useMotionLevel } from '@/composables/useMotionLevel';
import { claimSidebarIntro } from '@/composables/useSidebarMemory';
import { type SidebarNavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarRange,
    ChartBarStacked,
    ChartPie,
    ClipboardCheck,
    ClipboardList,
    Earth,
    FilePenLine,
    FileStack,
    Gauge,
    Images,
    Inbox,
    Map,
    MessageSquareWarning,
    PlayCircle,
    ShieldCheck,
    Target,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import '../../css/sidebar-motion.css';
import AppLogo from './AppLogo.vue';

/**
 * Optional sidebar effects. Set a flag to false to turn that effect off.
 * Timing and distances for every sidebar animation live in resources/css/sidebar-motion.css.
 */
const sidebarFeatures = {
    /** Soft glow that follows the mouse inside the hovered item. */
    spotlight: true,
    /** Thin scrollbar that only appears while hovering the sidebar. */
    customScrollbar: true,
};

const page = usePage();
const { isMobile } = useSidebar();
const motionLevel = useMotionLevel();

// Load-in stagger: first sidebar mount after a full page load only, desktop only, never for reduced motion.
const playIntro = claimSidebarIntro() && motionLevel.value !== 'still' && !isMobile.value;

const isAdmin = computed(() => {
    const access = (page.props.auth as any)?.user?.access;
    return access === 'admin' || access === 'superadmin';
});

const isSuperAdmin = computed(() => (page.props.auth as any)?.user?.access === 'superadmin');

// NHRDC members and their FSTP-assigned representatives both get the Interview Ratings area.
const canRateAsNhrdc = computed(() => (page.props.auth as any)?.canRateAsNhrdc === true);

const canAccessForeignPrograms = computed(() => (page.props.auth as any)?.canAccessForeignPrograms === true);

// `motion` picks the icon's hover animation (see SidebarIconMotion); items without one get a small scale pop.
const allNavItems: SidebarNavItem[] = [
    // Mga monitoring / tracking / compliance na page
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: ChartPie,
        color: 'text-indigo-600',
        group: 'Monitoring',
        motion: 'rotate',
    },
    {
        title: 'Employees',
        url: '/employees',
        icon: Users,
        color: 'text-blue-500',
        group: 'Monitoring',
        motion: 'bounce',
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
        title: 'Action Plan Monitoring',
        url: '/action-plan-monitoring',
        icon: Target,
        color: 'text-violet-600',
        group: 'Monitoring',
        motion: 'pulse',
    },
    {
        title: 'Submissions',
        url: '/submissions',
        icon: Inbox,
        color: 'text-blue-600',
        group: 'Monitoring',
        motion: 'drop',
    },
    {
        title: 'TNA Summary',
        url: '/tna-summary',
        icon: Gauge,
        color: 'text-indigo-500',
        group: 'Monitoring',
        motion: 'swing',
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
        motion: 'flip',
    },
    {
        title: 'Foreign Programs',
        url: '/foreign-programs',
        icon: Earth,
        color: 'text-blue-600',
        group: 'Management',
        motion: 'spin',
    },
    {
        title: 'Supporting Docs',
        url: '/supporting-documents',
        icon: FileStack,
        color: 'text-amber-500',
        group: 'Management',
    },
    {
        title: 'How-to Videos',
        url: '/how-to-videos',
        icon: PlayCircle,
        color: 'text-sky-500',
        group: 'Management',
        motion: 'pop',
    },
    {
        title: 'Public Forms',
        url: '/public-forms',
        icon: FilePenLine,
        color: 'text-blue-600',
        group: 'Management',
    },
];

const superAdminNavItems: SidebarNavItem[] = [
    {
        title: 'User Management',
        url: '/user-management',
        icon: ShieldCheck,
        color: 'text-rose-500',
        group: 'Management',
        motion: 'bounce',
    },
    {
        title: 'Homepage Images',
        url: '/site-images',
        icon: Images,
        color: 'text-fuchsia-500',
        group: 'Management',
    },
    {
        title: 'Problem Reports',
        url: '/problem-reports',
        icon: MessageSquareWarning,
        color: 'text-amber-500',
        group: 'Management',
        motion: 'wiggle',
    },
    {
        title: 'Employees from Registration',
        url: '/employees-from-registration',
        icon: UserPlus,
        color: 'text-blue-500',
        group: 'Management',
        motion: 'bounce',
    },
];

const nhrdcNavItems: SidebarNavItem[] = [
    {
        title: 'Interview Ratings',
        url: '/nhrdc/programs',
        icon: ClipboardCheck,
        color: 'text-indigo-500',
        group: 'Management',
    },
];

const mainNavItems = computed<SidebarNavItem[]>(() => {
    let items: SidebarNavItem[] = [];
    if (isAdmin.value) {
        items = isSuperAdmin.value ? [...allNavItems, ...superAdminNavItems] : allNavItems;
        // Foreign Programs is limited to FSTP unit members (and superadmins)
        if (!canAccessForeignPrograms.value) {
            items = items.filter((item) => item.url !== '/foreign-programs');
        }
    }
    if (canRateAsNhrdc.value) {
        items = [...items, ...nhrdcNavItems];
    }
    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem :class="{ 'sb-enter': playIntro }">
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="isAdmin ? route('dashboard') : route('home')" class="sb-logo-link">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent :class="{ 'sb-scroll': sidebarFeatures.customScrollbar }">
            <NavMain v-if="isAdmin || canRateAsNhrdc" :items="mainNavItems" :intro="playIntro" :spotlight="sidebarFeatures.spotlight" />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <!-- <NavUser /> -->
            <p class="px-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden" :title="(page.props.appVersion as any)?.full">
                {{ (page.props.appVersion as any)?.short }}
            </p>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
