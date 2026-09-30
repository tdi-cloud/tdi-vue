<script setup lang="ts">
import EmployeeProgressModal from '@/components/EmployeeProgressModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowRight, Building2, CheckCircle2, ChevronDown, Hash, MapPin, Search, SlidersHorizontal, Users, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    EMPCODE: string;
    FIRSTNAME: string;
    LASTNAME: string;
    MI: string;
    POSITION: string;
    'OFFICE/DIVISION': string;
    'PLANTILLA STATUS': string;
    REGION: string;
    OFFICE: string;
    SEX: string;
    SG: string;
    name: string;
    initials: string;
    avatar_color: string;
    avatar: string | null;
    progress_stats: {
        total_programs: number;
        completed_programs: number;
        total_hours: number;
        hours_completed: number;
    };
    submission_stats: {
        total_requirements: number;
        approved_submissions: number;
    };
}

interface PaginatedEmployees {
    data: Employee[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    employees: PaginatedEmployees;
    regions: string[];
    offices: string[];
    plantillaStatuses: string[];
    filters: {
        search?: string;
        region?: string;
        office?: string;
        plantilla?: string;
        per_page?: string;
    };
}>();

// Filters
const search = ref(props.filters.search ?? '');
const region = ref(props.filters.region ?? 'all');
const office = ref(props.filters.office ?? 'all');
const plantilla = ref(props.filters.plantilla ?? 'all');
const perPage = ref(props.filters.per_page ?? '10');

// Kapag nagbago ang region, i-reset ang office selection dahil magbabago rin ang mga choices nito.
watch(region, () => {
    office.value = 'all';
});

const hasActiveFilters = computed(
    () => search.value !== '' || region.value !== 'all' || office.value !== 'all' || plantilla.value !== 'all' || perPage.value !== '10',
);

const clearFilters = () => {
    search.value = '';
    region.value = 'all';
    office.value = 'all';
    plantilla.value = 'all';
    perPage.value = '10';
};

let debounce: ReturnType<typeof setTimeout>;
watch([search, region, office, plantilla, perPage], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('employees.index'),
            {
                search: search.value || undefined,
                region: region.value !== 'all' ? region.value : undefined,
                office: office.value !== 'all' ? office.value : undefined,
                plantilla: plantilla.value !== 'all' ? plantilla.value : undefined,
                per_page: perPage.value !== '10' ? perPage.value : undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 350);
});

// Detail Modal
const selectedEmpcode = ref<string | null>(null);

const openDetails = (emp: Employee) => {
    selectedEmpcode.value = emp.EMPCODE;
};

const closeModal = () => {
    selectedEmpcode.value = null;
};

// Helpers
const plantillaColor = (status: string) => {
    const s = status?.toUpperCase();
    if (s === 'PERMANENT')
        return 'bg-emerald-50 text-emerald-700 border-emerald-200/80 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20';
    if (s === 'JOB ORDER') return 'bg-amber-50 text-amber-700 border-amber-200/80 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20';
    if (s === 'CTI') return 'bg-blue-50 text-blue-700 border-blue-200/80 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/20';
    return 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-500/10 dark:text-slate-300 dark:border-slate-500/20';
};

const progressPercent = (num: number, denom: number) => (denom > 0 ? Math.min(100, Math.round((num / denom) * 100)) : 0);

const formatHours = (hours: number) => (Number.isInteger(hours) ? hours : Number(hours.toFixed(1)));

const paginationLabel = (label: string) => (label.includes('Previous') ? '&lsaquo;' : label.includes('Next') ? '&rsaquo;' : label);

// Shared progress metric definitions, used by both the desktop table and mobile cards.
const progressMetrics = (emp: Employee) => [
    {
        key: 'programs',
        label: 'Programs',
        value: `${emp.progress_stats.completed_programs} / ${emp.progress_stats.total_programs}`,
        percent: progressPercent(emp.progress_stats.completed_programs, emp.progress_stats.total_programs),
        bar: 'from-emerald-500 to-emerald-400 dark:from-emerald-500 dark:to-emerald-400/80',
    },
    {
        key: 'hours',
        label: 'Hours',
        value: `${formatHours(emp.progress_stats.hours_completed)} / ${formatHours(emp.progress_stats.total_hours)} hrs`,
        percent: progressPercent(emp.progress_stats.hours_completed, emp.progress_stats.total_hours),
        bar: 'from-indigo-500 to-blue-400 dark:from-indigo-500 dark:to-blue-400/80',
    },
    {
        key: 'submissions',
        label: 'Submissions',
        value: `${emp.submission_stats.approved_submissions} / ${emp.submission_stats.total_requirements}`,
        percent: progressPercent(emp.submission_stats.approved_submissions, emp.submission_stats.total_requirements),
        bar: 'from-violet-500 to-violet-400 dark:from-violet-500 dark:to-violet-400/80',
    },
];

const selectClass =
    'h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-9 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:border-slate-300 focus:border-indigo-400 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700/70 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-500/20';
</script>

<template>
    <Head title="Employee Progress" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-6 bg-slate-50/60 p-4 dark:bg-transparent sm:p-6 lg:p-8">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="mt-1.5 hidden h-8 w-1 shrink-0 rounded-full bg-gradient-to-b from-indigo-500 to-blue-500 sm:block" />
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-indigo-600 dark:text-indigo-400">
                            Learning &amp; Development
                        </p>
                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-3xl">Employee Progress</h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Track individual employee training progress and achievements</p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-3 self-start rounded-2xl border border-slate-200/80 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900/60 sm:self-auto"
                >
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400"
                    >
                        <Users class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Employees</p>
                        <p class="text-lg font-bold tabular-nums leading-tight text-slate-900 dark:text-white">
                            {{ employees.total.toLocaleString() }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Search + Filters -->
            <section class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:p-5">
                    <div class="relative flex-1">
                        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search employee, office, or employee code..."
                            class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50/70 pl-10 pr-10 text-sm text-slate-800 shadow-inner shadow-slate-100/50 transition placeholder:text-slate-400 hover:border-slate-300 focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700/70 dark:bg-slate-950/40 dark:text-slate-100 dark:shadow-none dark:placeholder:text-slate-500 dark:hover:border-slate-600 dark:focus:border-indigo-500 dark:focus:bg-slate-950/60 dark:focus:ring-indigo-500/20"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-2.5 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                            aria-label="Clear search"
                            @click="search = ''"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-2 sm:justify-end">
                        <div class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <SlidersHorizontal class="h-3.5 w-3.5" />
                            <span>Filters</span>
                        </div>
                        <Transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="opacity-0 translate-x-1"
                            leave-active-class="transition duration-150 ease-in"
                            leave-to-class="opacity-0"
                        >
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:text-slate-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                @click="clearFilters"
                            >
                                <X class="h-3.5 w-3.5" /> Clear all
                            </button>
                        </Transition>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4">
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="filter-region"
                            class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                        >
                            <MapPin class="h-3 w-3 text-indigo-500 dark:text-indigo-400" /> Region
                        </label>
                        <div class="relative">
                            <select id="filter-region" v-model="region" :class="selectClass">
                                <option value="all">All</option>
                                <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="filter-office"
                            class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                        >
                            <Building2 class="h-3 w-3 text-indigo-500 dark:text-indigo-400" /> Office
                        </label>
                        <div class="relative">
                            <select id="filter-office" v-model="office" :class="selectClass">
                                <option value="all">All</option>
                                <option v-for="o in offices" :key="o" :value="o">{{ o }}</option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="filter-plantilla"
                            class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                        >
                            <CheckCircle2 class="h-3 w-3 text-indigo-500 dark:text-indigo-400" /> Plantilla Status
                        </label>
                        <div class="relative">
                            <select id="filter-plantilla" v-model="plantilla" :class="selectClass">
                                <option value="all">All</option>
                                <option v-for="p in plantillaStatuses" :key="p" :value="p">{{ p?.toUpperCase() }}</option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="filter-per-page"
                            class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                        >
                            <Hash class="h-3 w-3 text-indigo-500 dark:text-indigo-400" /> Show per page
                        </label>
                        <div class="relative">
                            <select id="filter-per-page" v-model="perPage" :class="selectClass">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Results -->
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
                <!-- Summary strip -->
                <div class="flex flex-wrap items-center gap-x-8 gap-y-2 border-b border-slate-100 px-4 py-3.5 dark:border-slate-800 sm:px-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Employees</span>
                        <span class="text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ employees.total.toLocaleString() }}</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Currently Showing</span>
                        <span class="text-sm font-bold tabular-nums text-slate-900 dark:text-white"
                            >{{ employees.from ?? 0 }}–{{ employees.to ?? 0 }}</span
                        >
                    </div>
                </div>

                <!-- Desktop / tablet table -->
                <div class="hidden md:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200/80 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-900">
                                <th
                                    class="hidden px-6 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-indigo-900/60 dark:text-indigo-200/60 xl:table-cell"
                                >
                                    Empcode
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-indigo-900/60 dark:text-indigo-200/60 lg:px-6 xl:pl-4"
                                >
                                    Employee
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-indigo-900/60 dark:text-indigo-200/60 lg:px-4"
                                >
                                    Plantilla
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-indigo-900/60 dark:text-indigo-200/60 lg:px-4"
                                >
                                    Program Progress
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-indigo-900/60 dark:text-indigo-200/60 lg:px-4"
                                >
                                    Hours Progress
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-indigo-900/60 dark:text-indigo-200/60 lg:px-4"
                                >
                                    Submission Progress
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-indigo-900/60 dark:text-indigo-200/60 lg:px-6"
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <TransitionGroup tag="tbody" class="divide-y divide-slate-100 dark:divide-slate-800/80" appear>
                            <tr
                                v-for="(emp, index) in employees.data"
                                :key="emp.id"
                                class="group transition-colors duration-200 hover:bg-indigo-50/40 dark:hover:bg-indigo-500/[0.04]"
                                :style="{ animationDelay: `${index * 40}ms` }"
                            >
                                <td class="hidden whitespace-nowrap px-6 py-4 font-mono text-xs text-slate-500 dark:text-slate-400 xl:table-cell">
                                    {{ emp.EMPCODE }}
                                </td>
                                <td class="px-4 py-4 lg:px-6 xl:pl-4">
                                    <div class="flex items-center gap-3">
                                        <Avatar
                                            class="h-9 w-9 shrink-0 overflow-hidden rounded-full ring-2 ring-white dark:ring-slate-900"
                                            :class="emp.avatar_color"
                                        >
                                            <AvatarImage v-if="emp.avatar" :src="emp.avatar" :alt="emp.name" />
                                            <AvatarFallback
                                                class="flex h-full w-full items-center justify-center rounded-full bg-transparent text-xs font-bold text-white"
                                            >
                                                {{ emp.initials }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold leading-tight text-slate-900 dark:text-slate-100">
                                                {{ emp.name?.toUpperCase() }}
                                            </p>
                                            <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ emp['OFFICE/DIVISION'] }}</p>
                                            <p class="mt-0.5 font-mono text-[11px] text-slate-400 dark:text-slate-500 xl:hidden">{{ emp.EMPCODE }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4 lg:px-4">
                                    <span
                                        class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wide"
                                        :class="plantillaColor(emp['PLANTILLA STATUS'])"
                                    >
                                        {{ emp['PLANTILLA STATUS'] }}
                                    </span>
                                </td>
                                <td v-for="metric in progressMetrics(emp)" :key="metric.key" class="min-w-[130px] px-3 py-4 lg:min-w-[150px] lg:px-4">
                                    <div class="mb-1.5 flex items-baseline justify-between gap-2 text-xs">
                                        <span class="whitespace-nowrap font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{
                                            metric.value
                                        }}</span>
                                        <span class="font-medium tabular-nums text-slate-500 dark:text-slate-400">{{ metric.percent }}%</span>
                                    </div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r transition-[width] duration-700 ease-out"
                                            :class="metric.bar"
                                            :style="{ width: metric.percent + '%' }"
                                        />
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right lg:px-6">
                                    <button
                                        type="button"
                                        @click="openDetails(emp)"
                                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition-all duration-200 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-500/15 dark:border-slate-700 dark:bg-transparent dark:text-slate-300 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-300"
                                    >
                                        <Users class="h-3.5 w-3.5" />
                                        <span class="hidden lg:inline">View Details</span>
                                        <ArrowRight class="h-3 w-3 transition-transform duration-200 group-hover:translate-x-0.5" />
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="employees.data?.length === 0" key="empty-state">
                                <td colspan="7" class="px-4 py-16">
                                    <div class="flex flex-col items-center text-center">
                                        <span
                                            class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                                        >
                                            <Users class="h-6 w-6" />
                                        </span>
                                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No employees found.</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Try adjusting your search or filters.</p>
                                    </div>
                                </td>
                            </tr>
                        </TransitionGroup>
                    </table>
                </div>

                <!-- Mobile cards -->
                <TransitionGroup tag="div" class="divide-y divide-slate-100 dark:divide-slate-800/80 md:hidden" appear>
                    <article v-for="(emp, index) in employees.data" :key="emp.id" class="p-4" :style="{ animationDelay: `${index * 40}ms` }">
                        <div class="flex items-start gap-3">
                            <Avatar class="h-10 w-10 shrink-0 overflow-hidden rounded-full" :class="emp.avatar_color">
                                <AvatarImage v-if="emp.avatar" :src="emp.avatar" :alt="emp.name" />
                                <AvatarFallback
                                    class="flex h-full w-full items-center justify-center rounded-full bg-transparent text-xs font-bold text-white"
                                >
                                    {{ emp.initials }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold leading-tight text-slate-900 dark:text-slate-100">{{ emp.name?.toUpperCase() }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ emp['OFFICE/DIVISION'] }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wide"
                                        :class="plantillaColor(emp['PLANTILLA STATUS'])"
                                    >
                                        {{ emp['PLANTILLA STATUS'] }}
                                    </span>
                                    <span class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ emp.EMPCODE }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50/80 p-3 dark:bg-slate-950/30">
                            <div v-for="metric in progressMetrics(emp)" :key="metric.key">
                                <div class="mb-1.5 flex items-baseline justify-between gap-2 text-xs">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{
                                        metric.label
                                    }}</span>
                                    <span class="tabular-nums">
                                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ metric.value }}</span>
                                        <span class="ml-2 font-medium text-slate-500 dark:text-slate-400">{{ metric.percent }}%</span>
                                    </span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-slate-200/70 dark:bg-slate-800">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r transition-[width] duration-700 ease-out"
                                        :class="metric.bar"
                                        :style="{ width: metric.percent + '%' }"
                                    />
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="openDetails(emp)"
                            class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition-colors duration-200 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-transparent dark:text-slate-300 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-300"
                        >
                            <Users class="h-3.5 w-3.5" /> View Details <ArrowRight class="h-3 w-3" />
                        </button>
                    </article>

                    <div v-if="employees.data?.length === 0" key="empty-state" class="flex flex-col items-center px-4 py-14 text-center">
                        <span
                            class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                        >
                            <Users class="h-6 w-6" />
                        </span>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No employees found.</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Try adjusting your search or filters.</p>
                    </div>
                </TransitionGroup>

                <!-- Pagination -->
                <div
                    class="flex flex-col items-center gap-3 border-t border-slate-100 bg-slate-50/60 px-4 py-3.5 dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:justify-between sm:px-6"
                >
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Showing
                        <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200"
                            >{{ employees.from ?? 0 }}–{{ employees.to ?? 0 }}</span
                        >
                        of
                        <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ employees.total }}</span>
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-1">
                        <template v-for="link in employees.links" :key="link.label">
                            <a
                                v-if="link.url"
                                :href="link.url"
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs font-medium tabular-nums transition-colors duration-200"
                                :class="
                                    link.active
                                        ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm shadow-indigo-600/20 dark:border-indigo-500 dark:bg-indigo-500'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-300'
                                "
                                v-html="paginationLabel(link.label)"
                            />
                            <span
                                v-else
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs text-slate-400 opacity-60 dark:text-slate-500"
                                v-html="paginationLabel(link.label)"
                            />
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <!-- ===== Employee Progress Modal ===== -->
        <EmployeeProgressModal :empcode="selectedEmpcode" @close="closeModal" />
    </AppLayout>
</template>

<style scoped>
.v-enter-active {
    animation: slideIn 0.35s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.v-leave-active {
    animation: slideOut 0.2s ease both;
}

@keyframes slideIn {
    0% {
        opacity: 0;
        transform: translateY(6px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideOut {
    0% {
        opacity: 1;
    }
    100% {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .v-enter-active,
    .v-leave-active {
        animation: none;
    }
}
</style>
