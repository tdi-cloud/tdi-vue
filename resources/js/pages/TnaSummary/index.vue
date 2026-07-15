<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Gauge, Search, Building2, MapPin, CalendarClock,
    Eye, AlertTriangle, CheckCircle2, BarChart3,
} from 'lucide-vue-next';
import TnaSummaryDashboardModal from '@/components/TnaSummaryDashboardModal.vue';

interface Priority {
    unit: string;
    score: number;
    label: string;
    needs_training: boolean;
}

interface AssessmentRow {
    id: number;
    name: string;
    position: string;
    period: string;
    empcode: string | null;
    region: string | null;
    office: string | null;
    office_division: string | null;
    reviewed_at: string | null;
    top_priorities: Priority[];
}

interface PaginatedAssessments {
    data: AssessmentRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    assessments: PaginatedAssessments;
    regions: string[];
    officesByRegion: Record<string, string[]>;
    units: string[];
    filters: {
        search?: string;
        region?: string;
        office?: string;
        unit?: string;
        per_page?: string;
    };
}>();

const search = ref(props.filters.search ?? '');
const region = ref(props.filters.region ?? 'all');
const office = ref(props.filters.office ?? 'all');
const unit = ref(props.filters.unit ?? 'all');

const allOffices = computed(() =>
    Array.from(new Set(Object.values(props.officesByRegion).flat())).sort()
);

const availableOffices = computed(() =>
    region.value !== 'all' ? (props.officesByRegion[region.value] ?? []) : allOffices.value
);

// Kapag pinalitan ang region, i-reset ang office kung wala na ito sa
// bagong listahan ng offices ng napiling region.
watch(region, () => {
    if (office.value !== 'all' && ! availableOffices.value.includes(office.value)) {
        office.value = 'all';
    }
});

let debounce: ReturnType<typeof setTimeout>;
watch([search, region, office, unit], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('tna-summary.index'), {
            search: search.value || undefined,
            region: region.value !== 'all' ? region.value : undefined,
            office: office.value !== 'all' ? office.value : undefined,
            unit: unit.value !== 'all' ? unit.value : undefined,
        }, { preserveScroll: true, preserveState: true, replace: true });
    }, 350);
});

const clearFilters = () => {
    search.value = '';
    region.value = 'all';
    office.value = 'all';
    unit.value = 'all';
};

const hasActiveFilters = () => search.value || region.value !== 'all' || office.value !== 'all' || unit.value !== 'all';

const showDashboard = ref(false);

const priorityColor = (label: string) => {
    if (label === 'Not Competent') return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400';
    if (label === 'Slightly Competent') return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400';
    return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400';
};
</script>

<template>
    <Head title="TNA Summary" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-600 flex items-center justify-center shadow-sm shrink-0">
                        <Gauge class="h-5.5 w-5.5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-extrabold">TNA Summary</h1>
                        <p class="text-sm text-muted-foreground mt-0.5">
                            All employees with a finalized Training Needs Analysis Result, and their top training priorities.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl shadow-sm transition-colors"
                    @click="showDashboard = true"
                >
                    <BarChart3 class="h-4 w-4" /> Dashboard Summary
                </button>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="relative flex-1 min-w-64">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name or empcode..."
                            class="w-full border rounded-xl pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-background shadow-lg"
                        />
                    </div>

                    <select v-model="region" class="border rounded-xl px-3 py-2.5 text-sm bg-background shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="all">All Regions</option>
                        <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
                    </select>

                    <select v-model="office" class="border rounded-xl px-3 py-2.5 text-sm bg-background shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="all">All Offices</option>
                        <option v-for="o in availableOffices" :key="o" :value="o">{{ o }}</option>
                    </select>

                    <select v-model="unit" class="border rounded-xl px-3 py-2.5 text-sm bg-background shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 max-w-64">
                        <option value="all">All Units of Competency</option>
                        <option v-for="u in units" :key="u" :value="u">{{ u }}</option>
                    </select>

                    <button
                        v-if="hasActiveFilters()"
                        type="button"
                        @click="clearFilters"
                        class="text-xs text-muted-foreground hover:text-foreground px-2 py-2 transition-colors"
                    >
                        Clear filters
                    </button>
                </div>

                <p class="text-xs text-muted-foreground">
                    Showing {{ assessments.from ?? 0 }}–{{ assessments.to ?? 0 }} of {{ assessments.total }} employee(s)
                </p>
            </div>

            <!-- List -->
            <div class="rounded-2xl border overflow-hidden shadow-sm bg-background">
                <table v-if="assessments.data.length" class="w-full text-sm">
                    <thead>
                        <tr class="bg-foreground text-background">
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Employee</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Region / Office</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Period</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Top 3 Training Priorities</th>
                            <th class="text-right font-bold px-4 py-3 text-xs uppercase tracking-wide">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="row in assessments.data" :key="row.id" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-bold text-sm leading-tight">{{ row.name?.toUpperCase() }}</p>
                                <p class="text-xs text-muted-foreground">{{ row.position }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-xs text-muted-foreground flex items-center gap-1">
                                    <MapPin class="h-3 w-3 shrink-0" /> {{ row.region ?? '—' }}
                                </p>
                                <p class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                    <Building2 class="h-3 w-3 shrink-0" /> {{ row.office_division ?? row.office ?? '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-semibold">{{ row.period }}</p>
                                <p class="text-xs text-muted-foreground flex items-center gap-1">
                                    <CalendarClock class="h-3 w-3 shrink-0" /> {{ row.reviewed_at ?? '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="row.top_priorities.length" class="flex flex-col gap-1">
                                    <span
                                        v-for="p in row.top_priorities"
                                        :key="p.unit"
                                        class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2 py-0.5 rounded-full w-fit max-w-full"
                                        :class="priorityColor(p.label)"
                                    >
                                        <AlertTriangle class="h-3 w-3 shrink-0" />
                                        <span class="truncate">{{ p.unit }}</span>
                                        <span class="shrink-0">({{ p.score }})</span>
                                    </span>
                                </div>
                                <span v-else class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">
                                    <CheckCircle2 class="h-3 w-3" /> No urgent training needs
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="route('tna.result.show', row.id)"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border hover:bg-muted/50 transition-colors"
                                >
                                    <Eye class="h-3.5 w-3.5" /> View Result
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty state -->
                <div v-else class="flex flex-col items-center justify-center py-16 px-6 text-center gap-3">
                    <svg viewBox="0 0 200 160" class="h-40 w-auto" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="100" cy="142" rx="70" ry="8" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                        <rect x="55" y="45" width="90" height="80" rx="6" fill="currentColor" class="text-indigo-100 dark:text-indigo-900/30" />
                        <rect x="68" y="60" width="64" height="6" rx="3" fill="currentColor" class="text-indigo-300 dark:text-indigo-700/60" />
                        <rect x="68" y="75" width="64" height="6" rx="3" fill="currentColor" class="text-indigo-300 dark:text-indigo-700/60" />
                        <rect x="68" y="90" width="40" height="6" rx="3" fill="currentColor" class="text-indigo-300 dark:text-indigo-700/60" />
                        <circle cx="145" cy="100" r="18" fill="currentColor" class="text-blue-100 dark:text-blue-900/40" />
                        <path d="M138 100 l5 5 l10 -10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-blue-500" />
                    </svg>
                    <p class="text-sm font-bold text-slate-500">No TNA Results found</p>
                    <p class="text-xs text-slate-400 max-w-xs">
                        {{ hasActiveFilters() ? 'No employees match your filters.' : 'No employee has a finalized TNA Result yet.' }}
                    </p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="assessments.data.length" class="flex items-center justify-between text-sm">
                <p class="text-xs text-muted-foreground">
                    Showing {{ assessments.from ?? 0 }}–{{ assessments.to ?? 0 }} of {{ assessments.total }}
                </p>
                <div class="flex items-center gap-1">
                    <template v-for="link in assessments.links" :key="link.label">
                        <a v-if="link.url"
                            :href="link.url"
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'hover:bg-muted text-muted-foreground'"
                            v-html="link.label.includes('Previous') ? '&lsaquo;' : link.label.includes('Next') ? '&rsaquo;' : link.label"
                        />
                        <span v-else
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-xs text-muted-foreground opacity-40"
                            v-html="link.label.includes('Previous') ? '&lsaquo;' : link.label.includes('Next') ? '&rsaquo;' : link.label"
                        />
                    </template>
                </div>
            </div>

        </div>

        <TnaSummaryDashboardModal
            v-if="showDashboard"
            :filters="{ search: search || undefined, region: region !== 'all' ? region : undefined, office: office !== 'all' ? office : undefined }"
            @close="showDashboard = false"
        />
    </AppLayout>
</template>
