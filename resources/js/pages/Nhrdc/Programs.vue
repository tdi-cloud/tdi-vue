<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    Calendar,
    CheckCircle2,
    ChevronDown,
    CircleDashed,
    ClipboardCheck,
    FolderOpen,
    Search,
    SlidersHorizontal,
    Timer,
    Users,
    X,
} from 'lucide-vue-next';
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

type RatingStatus = 'not-started' | 'in-progress' | 'complete';

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

function ratingStatus(program: ForeignProgram): RatingStatus {
    if (program.nominees_count && program.rated_nominees_count >= program.nominees_count) return 'complete';
    if (program.rated_nominees_count > 0) return 'in-progress';
    return 'not-started';
}

function remainingCount(program: ForeignProgram): number {
    return Math.max(program.nominees_count - program.rated_nominees_count, 0);
}

const STATUS_META: Record<RatingStatus, { label: string; badge: string; bar: string; cta: string }> = {
    'not-started': {
        label: 'Not Started',
        badge: 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300',
        bar: 'bg-slate-400 dark:bg-slate-500',
        cta: 'Start Rating',
    },
    'in-progress': {
        label: 'In Progress',
        badge: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300',
        bar: 'bg-gradient-to-r from-indigo-500 to-blue-500',
        cta: 'Continue Rating',
    },
    complete: {
        label: 'Complete',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
        bar: 'bg-gradient-to-r from-emerald-500 to-emerald-400',
        cta: 'Review Ratings',
    },
};

// ── Summary metrics (derived from the programs prop only) ────────────────────

const totalNominees = computed(() => props.programs.reduce((sum, p) => sum + (p.nominees_count || 0), 0));
const completedPrograms = computed(() => props.programs.filter((p) => ratingStatus(p) === 'complete').length);

// ── Search & filters ─────────────────────────────────────────────────────────

const search = ref('');
const filterSponsor = ref('');
const filterYear = ref('');

const sponsorOptions = computed(() => [...new Set(props.programs.map((p) => p.organizing_sponsor).filter(Boolean))].sort());

const yearOptions = computed(() => [...new Set(props.programs.map(yearOf).filter((y): y is number => y !== null))].sort((a, b) => b - a));

const hasActiveFilters = computed(() => !!(search.value || filterSponsor.value || filterYear.value));

const activeFilterCount = computed(() => [search.value, filterSponsor.value, filterYear.value].filter(Boolean).length);

const clearFilters = () => {
    search.value = '';
    filterSponsor.value = '';
    filterYear.value = '';
};

const filteredPrograms = computed(() =>
    props.programs.filter((p) => {
        if (search.value && !p.program_title.toLowerCase().includes(search.value.toLowerCase())) return false;
        if (filterSponsor.value && p.organizing_sponsor !== filterSponsor.value) return false;
        if (filterYear.value && yearOf(p) !== Number(filterYear.value)) return false;
        return true;
    }),
);

const fieldClass =
    'h-10 w-full rounded-xl border border-slate-200 bg-white text-sm text-slate-800 shadow-sm transition-colors placeholder:text-slate-400 hover:border-slate-300 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:hover:border-slate-600 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/20';
</script>

<template>
    <Head title="Interview Ratings" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-6 bg-slate-50/60 p-4 dark:bg-slate-950 sm:p-6">
            <!-- Page header -->
            <header
                class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-7"
            >
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div
                        class="absolute inset-0 opacity-[0.04] dark:opacity-[0.06]"
                        style="
                            background-image:
                                linear-gradient(to right, currentColor 1px, transparent 1px),
                                linear-gradient(to bottom, currentColor 1px, transparent 1px);
                            background-size: 28px 28px;
                            mask-image: linear-gradient(to left, black, transparent 70%);
                        "
                    />
                    <div class="absolute -right-16 -top-24 h-72 w-72 rounded-full bg-indigo-500/[0.06] blur-2xl dark:bg-indigo-400/[0.08]" />
                    <div class="absolute -bottom-20 right-32 h-48 w-48 rounded-full border border-indigo-500/[0.08]" />
                </div>

                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-indigo-600 dark:text-indigo-400">
                            NHRDC • Assessment Workspace
                        </p>
                        <h1 class="mt-2 flex items-center gap-2.5 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-50">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-blue-700 text-white shadow-sm"
                            >
                                <ClipboardCheck class="h-[18px] w-[18px]" />
                            </span>
                            Interview Ratings
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            Review your assigned foreign training programs and complete the interview rating for each nominee. Your ratings are your
                            own — other NHRDC members' scores are never shown or affected.
                        </p>
                    </div>

                    <dl v-if="programs.length" class="grid grid-cols-3 gap-2 sm:gap-3 lg:min-w-[380px]">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/40 sm:px-4">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Programs</dt>
                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900 dark:text-slate-50">{{ programs.length }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/40 sm:px-4">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nominees</dt>
                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900 dark:text-slate-50">{{ totalNominees }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/40 sm:px-4">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Completed</dt>
                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                {{ completedPrograms
                                }}<span class="text-sm font-medium text-slate-400 dark:text-slate-500">/{{ programs.length }}</span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </header>

            <div
                v-if="!programs.length"
                class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <FolderOpen class="h-8 w-8 text-slate-300 dark:text-slate-600" />
                <p class="text-sm font-medium text-slate-600 dark:text-slate-300">No programs with nominees yet.</p>
            </div>

            <template v-else>
                <!-- Search & filter toolbar -->
                <div class="flex flex-col gap-3">
                    <div
                        class="flex flex-col gap-2.5 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:flex-row md:items-center"
                    >
                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search programs..."
                                aria-label="Search programs"
                                :class="[fieldClass, 'pl-10 pr-3']"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-2.5 md:flex md:items-center">
                            <div class="relative md:w-48">
                                <select
                                    v-model="filterSponsor"
                                    aria-label="Filter by sponsor"
                                    :class="[fieldClass, 'appearance-none truncate pl-3 pr-9']"
                                >
                                    <option value="">All Sponsors</option>
                                    <option v-for="s in sponsorOptions" :key="s" :value="s">{{ s }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            </div>
                            <div class="relative md:w-36">
                                <select v-model="filterYear" aria-label="Filter by year" :class="[fieldClass, 'appearance-none pl-3 pr-9']">
                                    <option value="">All Years</option>
                                    <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2 md:justify-start">
                            <div
                                class="inline-flex h-10 items-center gap-1.5 rounded-xl border px-3 text-xs font-medium transition-colors"
                                :class="
                                    hasActiveFilters
                                        ? 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300'
                                        : 'border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400'
                                "
                            >
                                <SlidersHorizontal class="h-3.5 w-3.5" />
                                <span>Filters</span>
                                <span
                                    v-if="activeFilterCount"
                                    class="flex h-4 min-w-4 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white"
                                >
                                    {{ activeFilterCount }}
                                </span>
                            </div>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex h-10 items-center gap-1 rounded-xl px-3 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                                @click="clearFilters"
                            >
                                <X class="h-3.5 w-3.5" /> Clear all
                            </button>
                        </div>
                    </div>

                    <p class="flex items-center gap-2 px-1 text-xs text-slate-500 dark:text-slate-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500" aria-hidden="true" />
                        Showing <span class="font-semibold text-slate-700 dark:text-slate-200">{{ filteredPrograms.length }}</span> of
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ programs.length }}</span> program(s)
                    </p>
                </div>

                <div
                    v-if="!filteredPrograms.length"
                    class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <Search class="h-7 w-7 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">No programs match your search or filters.</p>
                    <button
                        type="button"
                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Link
                        v-for="program in filteredPrograms"
                        :key="program.id"
                        :href="route('nhrdc.programs.show', program.id)"
                        class="group relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500/50"
                    >
                        <div
                            class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-indigo-500/[0.04] transition-opacity group-hover:opacity-100 dark:bg-indigo-400/[0.05]"
                            aria-hidden="true"
                        />

                        <div class="relative flex flex-1 flex-col gap-4 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                                    Foreign Training Program
                                </p>
                                <span
                                    class="inline-flex shrink-0 items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="STATUS_META[ratingStatus(program)].badge"
                                >
                                    <CheckCircle2 v-if="ratingStatus(program) === 'complete'" class="h-3 w-3" />
                                    <Timer v-else-if="ratingStatus(program) === 'in-progress'" class="h-3 w-3" />
                                    <CircleDashed v-else class="h-3 w-3" />
                                    {{ STATUS_META[ratingStatus(program)].label }}
                                </span>
                            </div>

                            <div class="flex flex-col gap-2">
                                <h2
                                    class="break-words text-base font-semibold leading-snug text-slate-900 transition-colors group-hover:text-indigo-700 dark:text-slate-50 dark:group-hover:text-indigo-300"
                                >
                                    {{ program.program_title }}
                                </h2>
                                <div class="flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400 sm:flex-row sm:flex-wrap sm:gap-x-4">
                                    <span class="flex min-w-0 items-center gap-1.5">
                                        <Building2 class="h-3.5 w-3.5 shrink-0 text-slate-400 dark:text-slate-500" />
                                        <span class="break-words">{{ program.organizing_sponsor }}</span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <Calendar class="h-3.5 w-3.5 shrink-0 text-slate-400 dark:text-slate-500" />
                                        {{ formatDate(program.program_start) }} – {{ formatDate(program.program_end) }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-auto rounded-xl border border-slate-100 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-800/40">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p
                                        class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                                    >
                                        <Users class="h-3 w-3" /> Your Rating Progress
                                    </p>
                                    <p class="text-xs font-semibold tabular-nums text-slate-500 dark:text-slate-400">
                                        {{ progressPercent(program) }}%
                                    </p>
                                </div>
                                <p class="mt-1 text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                    <span class="text-lg font-bold text-slate-900 dark:text-slate-50">{{ program.rated_nominees_count }}</span>
                                    / {{ program.nominees_count }} rated
                                </p>
                                <div
                                    class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-200/80 dark:bg-slate-700/60"
                                    role="progressbar"
                                    :aria-valuenow="progressPercent(program)"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    :aria-label="`${program.rated_nominees_count} of ${program.nominees_count} nominees rated`"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="STATUS_META[ratingStatus(program)].bar"
                                        :style="{ width: progressPercent(program) + '%' }"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="relative flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                <template v-if="remainingCount(program)">
                                    <span class="font-semibold text-amber-600 dark:text-amber-400">{{ remainingCount(program) }}</span>
                                    nominee{{ remainingCount(program) === 1 ? '' : 's' }} remaining
                                </template>
                                <template v-else-if="program.nominees_count">All nominees rated</template>
                                <template v-else>No nominees yet</template>
                            </p>
                            <span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ STATUS_META[ratingStatus(program)].cta }}
                                <ArrowRight class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                            </span>
                        </div>
                    </Link>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
