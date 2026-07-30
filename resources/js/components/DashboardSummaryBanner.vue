<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Users, GraduationCap, CalendarCheck, ClipboardList } from 'lucide-vue-next';

const props = defineProps<{
    summary: {
        employees: number;
        programs: number;
        active_batches: number;
        pending_submissions: number;
    };
}>();

const page = usePage<SharedData>();

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
});

const firstName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? '');

const stats = computed(() => [
    { label: 'Employees Monitored', value: props.summary.employees, icon: Users },
    { label: 'Programs Conducted', value: props.summary.programs, icon: GraduationCap },
    { label: 'Active Batches', value: props.summary.active_batches, icon: CalendarCheck },
    { label: 'Pending Submissions', value: props.summary.pending_submissions, icon: ClipboardList },
]);
</script>

<template>
    <div class="rounded-2xl bg-gradient-to-br from-blue-800 via-blue-700 to-sky-600 px-6 py-5 text-white shadow-md">
        <p class="text-lg font-bold">{{ greeting }}<template v-if="firstName">, {{ firstName }}</template> 👋</p>
        <p class="mt-0.5 text-sm text-white/80">Here's a quick look at what's happening across your programs today.</p>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur-sm"
            >
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15">
                    <component :is="stat.icon" class="h-4.5 w-4.5" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-extrabold leading-tight">{{ stat.value.toLocaleString() }}</p>
                    <p class="truncate text-[11px] font-medium text-white/75">{{ stat.label }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
