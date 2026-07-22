<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardCheck, Calendar, Building2, Users, ChevronRight, Search, X, SlidersHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

const props = defineProps<{ programs: ForeignProgram[] }>();

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

function yearOf(program: ForeignProgram): number | null {
    const y = new Date(program.program_start + 'T00:00:00').getFullYear();
    return isNaN(y) ? null : y;
}

// ── Search & filters ─────────────────────────────────────────────────────────

const search       = ref('');
const filterSponsor = ref('');
const filterYear    = ref('');

const sponsorOptions = computed(() =>
    [...new Set(props.programs.map(p => p.organizing_sponsor).filter(Boolean))].sort()
);

const yearOptions = computed(() =>
    [...new Set(props.programs.map(yearOf).filter((y): y is number => y !== null))].sort((a, b) => b - a)
);

const hasActiveFilters = computed(() => !!(search.value || filterSponsor.value || filterYear.value));

const clearFilters = () => {
    search.value = '';
    filterSponsor.value = '';
    filterYear.value = '';
};

const filteredPrograms = computed(() => props.programs.filter(p => {
    if (search.value && !p.program_title.toLowerCase().includes(search.value.toLowerCase())) return false;
    if (filterSponsor.value && p.organizing_sponsor !== filterSponsor.value) return false;
    if (filterYear.value && yearOf(p) !== Number(filterYear.value)) return false;
    return true;
}));
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

            <template v-else>
                <!-- Search & Filter Bar -->
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1 max-w-sm">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search programs..."
                                class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-background shadow-sm"
                            />
                        </div>
                        <select v-model="filterSponsor" class="border rounded-lg px-2.5 py-2 text-xs bg-background shadow-sm">
                            <option value="">All Sponsors</option>
                            <option v-for="s in sponsorOptions" :key="s" :value="s">{{ s }}</option>
                        </select>
                        <select v-model="filterYear" class="border rounded-lg px-2.5 py-2 text-xs bg-background shadow-sm">
                            <option value="">All Years</option>
                            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground px-2 py-1.5 rounded-lg border bg-muted/30">
                            <SlidersHorizontal class="h-3.5 w-3.5" />
                            <span>Filters</span>
                        </div>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
                            @click="clearFilters"
                        >
                            <X class="h-3.5 w-3.5" /> Clear all
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Showing {{ filteredPrograms.length }} of {{ programs.length }} program(s)
                    </p>
                </div>

                <div v-if="!filteredPrograms.length" class="rounded-2xl border border-dashed py-14 text-center text-sm text-muted-foreground">
                    No programs match your search or filters.
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <Link
                    v-for="program in filteredPrograms"
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
            </template>
        </div>
    </AppLayout>
</template>
