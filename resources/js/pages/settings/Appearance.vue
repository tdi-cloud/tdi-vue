<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';
import { Palette } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="rounded-2xl border p-5 md:p-6">
                <HeadingSmall
                    title="Appearance settings"
                    description="Update your account's appearance settings"
                    :icon="Palette"
                    icon-class="bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-400"
                />
                <AppearanceTabs class="mt-5" />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
