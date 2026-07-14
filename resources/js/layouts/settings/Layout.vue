<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { KeyRound, Palette, Settings2, UserRound } from 'lucide-vue-next';

const sidebarNavItems: (NavItem & { icon: typeof UserRound; color: string })[] = [
    {
        title: 'Profile',
        href: '/settings/profile',
        icon: UserRound,
        color: 'text-blue-600 dark:text-blue-400',
    },
    {
        title: 'Password',
        href: '/settings/password',
        icon: KeyRound,
        color: 'text-amber-600 dark:text-amber-400',
    },
    {
        title: 'Appearance',
        href: '/settings/appearance',
        icon: Palette,
        color: 'text-violet-600 dark:text-violet-400',
    },
];

const currentPath = window.location.pathname;
</script>

<template>
    <div class="px-4 py-6">
        <div class="mb-8 flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 shadow-sm">
                <Settings2 class="h-5.5 w-5.5 text-white" />
            </div>
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">Settings</h2>
                <p class="mt-0.5 text-sm text-muted-foreground">Manage your profile and account settings</p>
            </div>
        </div>

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-8 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-52">
                <nav class="flex flex-col gap-1 rounded-2xl border bg-muted/30 p-2">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="[
                            'w-full justify-start gap-2.5 rounded-xl',
                            currentPath === item.href ? 'bg-background shadow-sm font-semibold' : 'hover:bg-background/60',
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" :class="item.color" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>

                <!-- Decorative illustration (large screens only) -->
                <div class="mt-4 hidden overflow-hidden rounded-2xl border bg-gradient-to-br from-blue-50 to-indigo-50 p-4 dark:from-blue-950/20 dark:to-indigo-950/20 lg:block">
                    <svg viewBox="0 0 160 120" class="mx-auto h-24 w-auto" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="80" cy="104" rx="55" ry="7" fill="currentColor" class="text-blue-100 dark:text-blue-900/40" />
                        <circle cx="80" cy="55" r="38" fill="currentColor" class="text-blue-100 dark:text-blue-900/30" />
                        <path
                            d="M80 30 a25 25 0 1 0 0.1 0z M80 45 a10 10 0 1 0 0.1 0z"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="4"
                            class="text-blue-500 dark:text-blue-400"
                        />
                        <circle cx="122" cy="30" r="12" fill="currentColor" class="text-amber-100 dark:text-amber-900/40" />
                        <path d="M117 30 h10 M122 25 v10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-amber-500" />
                    </svg>
                    <p class="mt-2 text-center text-xs font-medium text-muted-foreground">Customize your account the way you like it.</p>
                </div>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-6">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
