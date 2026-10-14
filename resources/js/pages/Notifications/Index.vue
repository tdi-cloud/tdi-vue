<script setup lang="ts">
import NotificationsPanel, { type NotificationPage } from '@/components/NotificationsPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';

defineProps<{
    notifications: NotificationPage;
    filter: 'all' | 'unread';
    counts: { all: number; unread: number };
}>();

// Admins work inside the admin layout; everyone else uses the public site navbar.
const page = usePage();
const isAdmin = computed(() => ['admin', 'superadmin'].includes((page.props.auth as any)?.user?.access));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: route('notifications.page') }];
</script>

<template>
    <Head title="Notifications" />

    <AppLayout v-if="isAdmin" :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4 p-4 md:p-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 shadow-md">
                    <Bell class="h-5 w-5 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold leading-none">Notifications</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Updates about your programs, requirements and tasks.</p>
                </div>
            </div>
            <NotificationsPanel :notifications="notifications" :filter="filter" :counts="counts" />
        </div>
    </AppLayout>

    <div v-else class="min-h-screen bg-background text-foreground">
        <TheNavbar />
        <!-- Compact header band (dark in both themes so the white navbar text stays readable) -->
        <section class="bg-gradient-to-br from-[#0c1739] via-[#0f1c48] to-[#13275f] px-4 pb-7 pt-24 text-white sm:px-8">
            <div class="mx-auto max-w-4xl">
                <Link :href="route('home')" class="inline-flex items-center gap-1.5 text-sm font-semibold text-white/75 transition hover:text-white">
                    <ArrowLeft class="h-4 w-4" /> Back to Home
                </Link>
                <h1 class="mt-3 flex items-center gap-2.5 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    <Bell class="h-7 w-7 text-amber-300" /> Notifications
                </h1>
                <p class="mt-1.5 text-sm text-blue-100/80">Updates about your programs, requirements and tasks.</p>
            </div>
        </section>
        <main class="mx-auto max-w-4xl px-4 py-6 sm:px-8">
            <NotificationsPanel :notifications="notifications" :filter="filter" :counts="counts" />
        </main>
        <TheFooter />
    </div>
</template>
