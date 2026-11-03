<script setup lang="ts">
import MyProblemReportsPanel, { type MyProblemReportPage } from '@/components/MyProblemReportsPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, MessageSquareWarning } from 'lucide-vue-next';
import { computed } from 'vue';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';

defineProps<{
    reports: MyProblemReportPage;
    filters: { report?: string };
}>();

// Admins work inside the admin layout; everyone else uses the public site navbar.
const page = usePage();
const isAdmin = computed(() => ['admin', 'superadmin'].includes((page.props.auth as any)?.user?.access));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My problem reports', href: route('problem-reports.mine') }];
</script>

<template>
    <Head title="My problem reports" />

    <AppLayout v-if="isAdmin" :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4 p-4 md:p-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-600 shadow-md">
                    <MessageSquareWarning class="h-5 w-5 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold leading-none">My problem reports</h1>
                    <p class="mt-1 text-sm text-muted-foreground">The problems you reported and the super admins' replies.</p>
                </div>
            </div>
            <MyProblemReportsPanel :reports="reports" :filters="filters" />
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
                    <MessageSquareWarning class="h-7 w-7 text-amber-300" /> My problem reports
                </h1>
                <p class="mt-1.5 text-sm text-blue-100/80">The problems you reported and the super admins' replies.</p>
            </div>
        </section>
        <main class="mx-auto max-w-4xl px-4 py-6 sm:px-8">
            <MyProblemReportsPanel :reports="reports" :filters="filters" />
        </main>
        <TheFooter />
    </div>
</template>
