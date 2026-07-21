<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardCheck, Calendar, Building2, Users, ChevronRight } from 'lucide-vue-next';

interface ForeignProgram {
    id: number;
    program_title: string;
    program_start: string;
    program_end: string;
    organizing_sponsor: string;
    slots: number;
    status: string;
    nominees_count: number;
    rated_nominees_count: number;
}

defineProps<{ programs: ForeignProgram[] }>();

const formatDate = (date?: string | null) => {
    if (!date) return '—';
    const d = date.includes('T') ? new Date(date) : new Date(date + 'T00:00:00');
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

function progressPercent(program: ForeignProgram) {
    if (!program.nominees_count) return 0;
    return Math.round((program.rated_nominees_count / program.nominees_count) * 100);
}
</script>

<template>
    <Head title="Interview Ratings" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4">
            <div class="flex items-center gap-2">
                <ClipboardCheck class="h-5 w-5 text-indigo-600" />
                <h1 class="text-lg font-bold">Interview Ratings</h1>
            </div>
            <p class="text-sm text-muted-foreground -mt-3">
                Select a program to rate the interview of its nominees. Your ratings are your own — other NHRDC
                members' scores are never shown or affected.
            </p>

            <div v-if="!programs.length" class="rounded-2xl border border-dashed py-14 text-center text-sm text-muted-foreground">
                No programs with nominees yet.
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <Link
                    v-for="program in programs"
                    :key="program.id"
                    :href="route('nhrdc.programs.show', program.id)"
                    class="rounded-2xl border bg-background shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition-all"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold truncate">{{ program.program_title }}</p>
                            <p class="text-xs text-muted-foreground flex items-center gap-1.5 mt-1">
                                <Building2 class="h-3 w-3" /> {{ program.organizing_sponsor }}
                            </p>
                            <p class="text-xs text-muted-foreground flex items-center gap-1.5 mt-0.5">
                                <Calendar class="h-3 w-3" /> {{ formatDate(program.program_start) }} – {{ formatDate(program.program_end) }}
                            </p>
                        </div>
                        <ChevronRight class="h-4 w-4 text-muted-foreground shrink-0 mt-1" />
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center justify-between text-[11px] font-semibold text-muted-foreground mb-1">
                            <span class="flex items-center gap-1"><Users class="h-3 w-3" /> Rated by me</span>
                            <span>{{ program.rated_nominees_count }} / {{ program.nominees_count }}</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="progressPercent(program) >= 100 ? 'bg-emerald-500' : 'bg-indigo-500'"
                                :style="{ width: progressPercent(program) + '%' }"
                            />
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
