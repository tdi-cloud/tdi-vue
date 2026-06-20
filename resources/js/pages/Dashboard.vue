<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import TrainingComplianceCard from '../components/TrainingComplianceCard.vue';
import SupervisoryComplianceCard from '../components/SupervisoryComplianceCard.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

defineProps<{ name?: string }>();

/* ===================== SHARED FILTERS ===================== */

const REGIONS = [
    'ALL', 'CO', 'NCR', 'R1', 'R2', 'R3', 'R4A', 'R4B', 'R5',
    'NIR', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12',
    'CAR', 'CARAGA',
];

const STATUS_OPTIONS = ['PERMANENT', 'JOB ORDER', 'CONTRACTUAL', 'CTI'];

const target           = ref<'Nationwide' | 'OPCR'>('Nationwide');
const region           = ref('ALL');
const selectedStatuses = ref<string[]>(STATUS_OPTIONS.filter((s) => s !== 'JOB ORDER'));

const toggleStatus = (status: string) => {
    if (selectedStatuses.value.includes(status)) {
        selectedStatuses.value = selectedStatuses.value.filter((s) => s !== status);
    } else {
        selectedStatuses.value = [...selectedStatuses.value, status];
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <!-- ===================== SHARED FILTER BAR ===================== -->
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card px-5 py-3 shadow-sm">

                <!-- Target filter -->
                <Select v-model="target">
                    <SelectTrigger class="h-9 w-44 text-xs font-semibold">
                        <SelectValue placeholder="Select target" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem class="text-xs" value="Nationwide">Nationwide Target</SelectItem>
                        <SelectItem class="text-xs" value="OPCR">OPCR Target</SelectItem>
                    </SelectContent>
                </Select>

                <!-- Region filter -->
                <Select v-model="region">
                    <SelectTrigger class="h-9 w-36 text-xs font-semibold">
                        <SelectValue placeholder="Select region" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="r in REGIONS" :key="r" class="text-xs" :value="r">
                            {{ r === 'ALL' ? 'All Regions' : r }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <!-- Divider -->
                <div class="h-5 w-px bg-border"></div>

                <!-- Plantilla status checkboxes -->
                <div class="flex flex-wrap items-center gap-3">
                    <label
                        v-for="status in STATUS_OPTIONS"
                        :key="status"
                        class="flex items-center gap-1.5 cursor-pointer select-none"
                    >
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded-full accent-amber-500 cursor-pointer"
                            :checked="selectedStatuses.includes(status)"
                            @change="toggleStatus(status)"
                        />
                        <span class="text-[11px] font-bold tracking-wide text-slate-400">{{ status }}</span>
                    </label>
                </div>

            </div>

            <!-- ===================== TWO CARDS SIDE BY SIDE ===================== -->
            <div class="grid gap-4 md:grid-cols-2">
                <TrainingComplianceCard
                    :target="target"
                    :region="region"
                    :selected-statuses="selectedStatuses"
                />
                <SupervisoryComplianceCard
                    :target="target"
                    :region="region"
                    :selected-statuses="selectedStatuses"
                />
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>

            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <PlaceholderPattern />
            </div>

        </div>
    </AppLayout>
</template>