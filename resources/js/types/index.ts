import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

/** One-shot icon animation a sidebar item plays on hover; items without one get a small scale pop. */
export type SidebarIconMotion = 'rotate' | 'bounce' | 'flip' | 'drop' | 'pulse' | 'pop' | 'swing' | 'spin' | 'wiggle';

export interface SidebarNavItem {
    title: string;
    url: string;
    icon: LucideIcon;
    /** Tailwind text color class for the icon, e.g. `text-indigo-600`. */
    color?: string;
    group?: string;
    motion?: SidebarIconMotion;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    unreadNotificationsCount: number;
    authBackground: string | null;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
