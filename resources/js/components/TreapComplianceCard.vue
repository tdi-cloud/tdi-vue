<script setup lang="ts">
import EmployeeProgressModal from '@/components/EmployeeProgressModal.vue';
import RegionalRankingModal, { type RankedRegion } from '@/components/RegionalRankingModal.vue';
import {
    Award,
    ChevronRight,
    ClipboardCheck,
    Download,
    FileCheck,
    FileX,
    Loader2,
    Medal,
    Search,
    TrendingUp,
    Trophy,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps<{
    target: string;
    region: string;
    selectedStatuses: string[];
    office: string;
    year: string;
}>();

// ─── State ───────────────────────────────────────────────────────────────────

interface TreapData {
    total: number;
    submitted: number;
    not_submitted: number;
    submitted_pct: number;
    not_submitted_pct: number;
    regions: string[];
    regions_submitted: number[];
    regions_not_submitted: number[];
}

interface EmployeeRow {
    empcode: string;
    name: string;
    position: string;
    office_division: string;
    office: string;
    region: string;
    plantilla_status: string;
    program_title: string | null;
    batch_name: string;
    date_start: string;
    date_end: string;
    due_date: string;
}

const data = ref<TreapData | null>(null);
const loading = ref(false);

// ─── Modal state ─────────────────────────────────────────────────────────────

const modalOpen = ref(false);
const modalType = ref<'submitted' | 'not_submitted'>('submitted');
const modalRegion = ref('ALL');
const modalEmployees = ref<EmployeeRow[]>([]);
const modalLoading = ref(false);
const modalCount = ref(0);
const modalSearch = ref('');

const filteredModalEmployees = computed(() => {
    const q = modalSearch.value.trim().toLowerCase();
    if (!q) return modalEmployees.value;
    return modalEmployees.value.filter((emp) =>
        [emp.name, emp.empcode, emp.position, emp.office_division, emp.office, emp.region, emp.batch_name, emp.program_title].some((field) =>
            field?.toLowerCase().includes(q),
        ),
    );
});

const selectedEmpcode = ref<string | null>(null);

// ─── Fetch main data ─────────────────────────────────────────────────────────

async function fetchData() {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            region: props.region,
            office: props.office,
            office_filter: props.target,
            year: props.year,
        });
        props.selectedStatuses.forEach((s) => params.append('plant_status[]', s));
        const res = await fetch(`/dashboard/treap-compliance?${params}`);
        data.value = await res.json();
    } finally {
        loading.value = false;
    }
}

watch(() => [props.region, props.selectedStatuses, props.office, props.target, props.year], fetchData, { immediate: true, deep: true });

// ─── Fetch list (drill-down) ─────────────────────────────────────────────────

async function fetchList(type: 'submitted' | 'not_submitted', reg = 'ALL') {
    modalType.value = type;
    modalRegion.value = reg;
    modalOpen.value = true;
    modalLoading.value = true;
    modalEmployees.value = [];
    modalSearch.value = '';

    try {
        const params = new URLSearchParams({
            region: props.region,
            office: props.office,
            office_filter: props.target,
            year: props.year,
            type,
            reg,
        });
        props.selectedStatuses.forEach((s) => params.append('plant_status[]', s));
        const res = await fetch(`/dashboard/treap-compliance-list?${params}`);
        const json = await res.json();
        modalEmployees.value = json.employees;
        modalCount.value = json.count;
    } finally {
        modalLoading.value = false;
    }
}

// ─── Chart event handlers ─────────────────────────────────────────────────────

function onDonutClick(_e: any, _chart: any, opts: any) {
    if (opts.dataPointIndex < 0) return;
    const type = opts.dataPointIndex === 0 ? 'submitted' : 'not_submitted';
    fetchList(type, 'ALL');
}

function onBarClick(_e: any, _chart: any, opts: any) {
    if (opts.dataPointIndex < 0) return;
    const regions = data.value?.regions ?? REGIONS;
    const reg = regions[opts.dataPointIndex];
    const type = opts.seriesIndex === 0 ? 'submitted' : 'not_submitted';
    fetchList(type, reg);
}

// ─── Donut chart ─────────────────────────────────────────────────────────────

const donutOptions = computed(() => ({
    chart: {
        type: 'donut',
        fontFamily: 'inherit',
        toolbar: { show: false },
        events: { dataPointSelection: onDonutClick },
        background: 'transparent',
    },
    labels: ['Submitted', 'Not Submitted'],
    colors: ['#10B981', '#F43F5E'],
    fill: {
        type: 'solid',
        opacity: [0.45, 0.35],
    },
    stroke: {
        show: true,
        width: 1.5,
        colors: ['#10B981', '#F43F5E'],
        lineCap: 'round',
    },
    legend: {
        position: 'bottom',
        fontSize: '12px',
        labels: { colors: ['#059669', '#f43f5e'] },
    },
    plotOptions: {
        pie: {
            startAngle: -90,
            endAngle: 270,
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Submitted',
                        color: '#059669',
                        fontSize: '13px',
                        fontWeight: 700,
                        formatter: () => (data.value ? `${data.value.submitted_pct}%` : '0%'),
                    },
                    value: {
                        color: '#059669',
                        fontWeight: 700,
                    },
                },
            },
        },
    },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (val: number) => `${val} employees` } },
    states: { hover: { filter: { type: 'lighten', value: 0.1 } } },
}));

const donutSeries = computed(() => (data.value ? [data.value.submitted, data.value.not_submitted] : [0, 1]));

// ─── Bar chart ────────────────────────────────────────────────────────────────

const REGIONS = ['CO', 'NCR', 'R1', 'R2', 'R3', 'R4A', 'R4B', 'R5', 'NIR', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12', 'CAR', 'CARAGA'];

const barOptions = computed(() => ({
    chart: {
        type: 'bar',
        fontFamily: 'inherit',
        toolbar: { show: false },
        stacked: true,
        stackType: '100%',
        events: { dataPointSelection: onBarClick },
    },
    plotOptions: {
        bar: {
            horizontal: true,
            borderRadius: 4,
            borderRadiusApplication: 'end',
            barHeight: '60%',
        },
    },
    colors: ['#10b981', '#f43f5e'],
    xaxis: {
        categories: data.value?.regions ?? REGIONS,
        labels: {
            style: { fontSize: '11px', colors: '#64748B' },
            formatter: (val: number) => `${Math.round(val)}%`,
        },
    },
    yaxis: {
        labels: { style: { fontSize: '11px', colors: '#64748B' } },
    },
    legend: { position: 'top', fontSize: '12px', labels: { colors: '#64748B' } },
    dataLabels: {
        enabled: true,
        style: { fontSize: '10px', colors: ['#fff'] },
        formatter: (val: number, opts: any) => {
            const raw = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex];
            return raw > 0 ? raw : '';
        },
    },
    tooltip: { y: { formatter: (val: number) => `${val} employees` } },
    fill: { opacity: 1 },
    grid: { borderColor: 'rgba(148, 163, 184, 0.18)' },
}));

const barSeries = computed(() => [
    { name: 'Submitted', data: data.value?.regions_submitted ?? Array(18).fill(0) },
    { name: 'Not Submitted', data: data.value?.regions_not_submitted ?? Array(18).fill(0) },
]);

// ─── Top 3 regional compliance ───────────────────────────────────────────────

// Rank 1 emerald, rank 2 blue, rank 3 neutral — each with its own icon.
const RANK_BADGES = [
    {
        icon: Trophy,
        classes: 'bg-emerald-100 text-emerald-700 ring-emerald-300/60 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-500/30',
    },
    { icon: Medal, classes: 'bg-blue-100 text-blue-700 ring-blue-300/60 dark:bg-blue-900/40 dark:text-blue-300 dark:ring-blue-500/30' },
    { icon: Award, classes: 'bg-slate-200 text-slate-600 ring-slate-300/70 dark:bg-slate-700/60 dark:text-slate-300 dark:ring-slate-500/30' },
];

function toCount(value: unknown): number {
    const n = Number(value);
    return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0;
}

// The backend computes regions_not_submitted as (regional total − submitted)
// from the same filtered, per-obligation query as the charts, so
// submitted + not_submitted is exactly the regional total required. Regions
// outside the region filter come back as 0 / 0 and drop out via total > 0.
const rankedRegions = computed<RankedRegion[]>(() => {
    const d = data.value;
    if (!d || !Array.isArray(d.regions)) return [];

    return d.regions
        .map((region, order) => {
            const submitted = toCount(d.regions_submitted?.[order]);
            const total = submitted + toCount(d.regions_not_submitted?.[order]);
            return { region, submitted, total, order };
        })
        .filter((r) => r.region && r.total > 0)
        .sort(
            (a, b) =>
                // Exact rate comparison via cross-multiplication, then more
                // submissions, then the canonical region order.
                b.submitted * a.total - a.submitted * b.total || b.submitted - a.submitted || a.order - b.order,
        )
        .map((r) => ({ region: r.region, submitted: r.submitted, total: r.total, rate: Math.round((r.submitted / r.total) * 1000) / 10 }));
});

const topRegions = computed(() => rankedRegions.value.slice(0, 3));

// ─── Regional ranking modal ──────────────────────────────────────────────────

const rankingOpen = ref(false);

// Drill-down opened from the ranking modal stacks on top of it.
function onRankingRegionSelect(region: string) {
    fetchList('submitted', region);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatDate(d: string | null) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

// ─── CSV export ──────────────────────────────────────────────────────────────

// Sorted by region (same order as the chart) then name, so the file can be
// split and forwarded to each region easily.
function downloadCsv() {
    const rows = [...filteredModalEmployees.value].sort((a, b) => {
        const regionOrder = REGIONS.indexOf(a.region) - REGIONS.indexOf(b.region);
        return regionOrder !== 0 ? regionOrder : a.name.localeCompare(b.name);
    });
    if (!rows.length) return;
    const escape = (val: unknown) => {
        const s = String(val ?? '');
        return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const requirementStatus = modalType.value === 'submitted' ? 'Submitted' : 'Not Yet Submitted';
    const header = [
        '#',
        'REGION',
        'EMPCODE',
        'NAME',
        'POSITION',
        'OFFICE/DIVISION',
        'PLANTILLA STATUS',
        'PROGRAM',
        'BATCH',
        'TRAINING START DATE',
        'TRAINING END DATE',
        'REQUIREMENT',
        'DUE DATE',
        'STATUS',
    ];
    const lines = rows.map((e, i) =>
        [
            i + 1,
            e.region,
            e.empcode,
            e.name,
            e.position,
            e.office_division,
            e.plantilla_status,
            e.program_title,
            e.batch_name,
            e.date_start,
            e.date_end,
            'TREAP',
            e.due_date,
            requirementStatus,
        ]
            .map(escape)
            .join(','),
    );
    const csv = [header.join(','), ...lines].join('\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `treap-${modalType.value.replace('_', '-')}-${modalRegion.value.toLowerCase()}-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

const modalTitle = computed(() => {
    const label = modalType.value === 'submitted' ? 'Submitted' : 'Not Yet Submitted';
    return modalRegion.value === 'ALL' ? `TREAP — ${label} (All Regions)` : `TREAP — ${label} · ${modalRegion.value}`;
});
</script>

<template>
    <div class="tdi-card tdi-accent-emerald tdi-accent-to-amber relative isolate flex flex-col gap-5 overflow-hidden rounded-2xl border p-5">
        <!-- Decorative background texture — purely visual, sits behind existing content via negative z-index -->
        <div class="tdi-texture-document pointer-events-none absolute inset-0 z-[-1] opacity-[0.025] dark:opacity-[0.04]" aria-hidden="true"></div>

        <!-- Header -->
        <div class="flex items-center gap-2">
            <div class="tdi-icon-chip flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                <FileCheck class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <h2 class="text-sm font-extrabold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">Terminal Report (TREAP)</h2>
                <p class="text-xs text-muted-foreground">Overdue TREAP submission compliance · click any chart segment to see the list</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-emerald-500 border-t-transparent" />
        </div>

        <template v-else-if="data">
            <!-- Stat row — clickable -->
            <div class="grid grid-cols-3 gap-3">
                <div class="tdi-surface flex flex-col gap-0.5 rounded-xl p-3">
                    <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                        <Users class="h-3 w-3" /> Total Required
                    </p>
                    <p class="text-2xl font-extrabold">{{ data.total.toLocaleString() }}</p>
                </div>
                <button
                    class="flex cursor-pointer flex-col gap-0.5 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-left transition-all hover:brightness-95 dark:border-emerald-800/40 dark:bg-emerald-950/20"
                    @click="fetchList('submitted', 'ALL')"
                >
                    <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                        <FileCheck class="h-3 w-3" /> Submitted
                    </p>
                    <p class="text-2xl font-extrabold text-emerald-700 dark:text-emerald-300">{{ data.submitted.toLocaleString() }}</p>
                    <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ data.submitted_pct }}%</p>
                </button>
                <button
                    class="flex cursor-pointer flex-col gap-0.5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-left transition-all hover:brightness-95 dark:border-rose-800/40 dark:bg-rose-950/20"
                    @click="fetchList('not_submitted', 'ALL')"
                >
                    <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-400">
                        <FileX class="h-3 w-3" /> Not Submitted
                    </p>
                    <p class="text-2xl font-extrabold text-rose-600 dark:text-rose-400">{{ data.not_submitted.toLocaleString() }}</p>
                    <p class="text-xs font-semibold text-rose-500 dark:text-rose-400">{{ data.not_submitted_pct }}%</p>
                </button>
            </div>

            <!-- Charts row -->
            <div class="grid grid-cols-1 items-start gap-5 md:grid-cols-[220px_1fr]">
                <!-- Left column: donut + top 3 regional compliance -->
                <div class="flex min-w-0 flex-col gap-4">
                    <!-- Donut -->
                    <div class="flex flex-col items-center">
                        <p class="mb-1 text-xs font-semibold text-muted-foreground">Overall Rate</p>
                        <VueApexCharts type="donut" height="220" :options="donutOptions" :series="donutSeries" />
                    </div>

                    <!-- Top 3 regional compliance -->
                    <section aria-labelledby="treap-regional-monitoring-heading" class="tdi-surface relative isolate overflow-hidden rounded-xl p-3">
                        <!-- Decorative trophy watermark — behind all content, never interactive -->
                        <div class="pointer-events-none absolute inset-0 -z-10 select-none" aria-hidden="true">
                            <div class="treap-trophy-glow absolute -bottom-12 -right-10 h-52 w-52 rounded-full" />
                            <svg
                                class="absolute -bottom-4 -right-3 h-40 w-36 -rotate-[8deg] opacity-[0.08] dark:opacity-[0.11]"
                                viewBox="0 0 120 140"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <defs>
                                    <linearGradient id="treap-trophy-gold" x1="0" y1="0" x2="1" y2="0">
                                        <stop offset="0" stop-color="#6b4c14" />
                                        <stop offset="0.28" stop-color="#e2be5c" />
                                        <stop offset="0.42" stop-color="#fff3c4" />
                                        <stop offset="0.6" stop-color="#d1a237" />
                                        <stop offset="1" stop-color="#7c5a1b" />
                                    </linearGradient>
                                    <linearGradient id="treap-trophy-rim" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0" stop-color="#fff6d6" />
                                        <stop offset="1" stop-color="#b8892c" />
                                    </linearGradient>
                                    <linearGradient id="treap-trophy-base" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="#a77a2a" />
                                        <stop offset="0.45" stop-color="#e8c873" />
                                        <stop offset="1" stop-color="#5e4212" />
                                    </linearGradient>
                                    <radialGradient id="treap-trophy-shadow" cx="0.5" cy="0.5" r="0.5">
                                        <stop offset="0" stop-color="#000" stop-opacity="0.5" />
                                        <stop offset="1" stop-color="#000" stop-opacity="0" />
                                    </radialGradient>
                                </defs>
                                <ellipse cx="60" cy="132" rx="34" ry="5" fill="url(#treap-trophy-shadow)" />
                                <!-- Handles -->
                                <path d="M30 28C14 28 11 52 32 60" stroke="url(#treap-trophy-base)" stroke-width="6" stroke-linecap="round" />
                                <path d="M90 28C106 28 109 52 88 60" stroke="url(#treap-trophy-base)" stroke-width="6" stroke-linecap="round" />
                                <!-- Cup -->
                                <path d="M28 18H92V40C92 66 78 82 60 84C42 82 28 66 28 40Z" fill="url(#treap-trophy-gold)" />
                                <ellipse cx="60" cy="18" rx="32" ry="5" fill="url(#treap-trophy-rim)" />
                                <ellipse cx="60" cy="18.5" rx="27" ry="3" fill="#6b4c14" fill-opacity="0.45" />
                                <!-- Specular highlight -->
                                <path d="M37 26C36 48 43 66 52 77C45 64 41 46 42 26Z" fill="#fff" fill-opacity="0.55" />
                                <!-- Star emblem -->
                                <path
                                    d="M60 34L63.5 41.1L71.4 42.3L65.7 47.8L67 55.6L60 51.9L53 55.6L54.3 47.8L48.6 42.3L56.5 41.1Z"
                                    fill="#fff8dc"
                                    fill-opacity="0.7"
                                />
                                <!-- Stem, collar and base -->
                                <path d="M54 83H66L64 99H56Z" fill="url(#treap-trophy-gold)" />
                                <rect x="47" y="98" width="26" height="5" rx="2" fill="url(#treap-trophy-rim)" />
                                <rect x="43" y="103" width="34" height="10" rx="2" fill="url(#treap-trophy-base)" />
                                <path d="M40 113H80L85 129H35Z" fill="url(#treap-trophy-gold)" />
                                <rect x="51" y="117" width="18" height="6" rx="1" fill="#fff6d6" fill-opacity="0.35" />
                            </svg>
                        </div>
                        <div class="mb-2 flex items-start gap-1.5">
                            <ClipboardCheck class="mt-px h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                            <div class="min-w-0 flex-1">
                                <h3
                                    id="treap-regional-monitoring-heading"
                                    class="text-[11px] font-bold uppercase leading-tight tracking-wide text-emerald-700 dark:text-emerald-400"
                                >
                                    Top Regions
                                </h3>
                                <p class="text-[10px] leading-snug text-muted-foreground">Top 3 by compliance rate</p>
                            </div>
                            <button
                                v-if="rankedRegions.length"
                                type="button"
                                class="group inline-flex shrink-0 items-center rounded-md px-1 text-[10px] font-semibold text-cyan-700 transition-colors hover:text-cyan-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500/50 dark:text-cyan-400 dark:hover:text-cyan-300"
                                aria-haspopup="dialog"
                                aria-label="View the full regional compliance ranking"
                                @click="rankingOpen = true"
                            >
                                View all
                                <ChevronRight
                                    class="h-3 w-3 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>

                        <ol v-if="topRegions.length" class="flex flex-col gap-1">
                            <li v-for="(entry, index) in topRegions" :key="entry.region">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 motion-reduce:transition-none"
                                    :aria-label="`Rank ${index + 1}: ${entry.region}, ${entry.rate}% compliance. View submitted employees.`"
                                    @click="fetchList('submitted', entry.region)"
                                >
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full ring-1"
                                        :class="RANK_BADGES[index].classes"
                                        aria-hidden="true"
                                    >
                                        <component :is="RANK_BADGES[index].icon" class="h-3.5 w-3.5" />
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-xs font-semibold">{{ entry.region }}</span>
                                    <span class="shrink-0 text-xs font-bold tabular-nums text-emerald-700 dark:text-emerald-400"
                                        >{{ entry.rate }}%</span
                                    >
                                </button>
                            </li>
                        </ol>

                        <p v-else class="py-2 text-center text-[11px] text-muted-foreground">No regional data for the current filters.</p>
                    </section>
                </div>

                <!-- Bar -->
                <div>
                    <p class="mb-1 text-xs font-semibold text-muted-foreground">Per Region — click a bar to see names</p>
                    <VueApexCharts type="bar" :height="Math.max(280, (data.regions?.length ?? 18) * 28)" :options="barOptions" :series="barSeries" />
                </div>
            </div>
        </template>

        <!-- Empty -->
        <div v-else class="flex flex-col items-center justify-center gap-2 py-12 text-muted-foreground">
            <TrendingUp class="h-10 w-10 opacity-20" />
            <p class="text-sm">No TREAP data available.</p>
        </div>
    </div>

    <RegionalRankingModal
        :open="rankingOpen"
        :regions="rankedRegions"
        :year="year"
        :paused="modalOpen || selectedEmpcode !== null"
        @close="rankingOpen = false"
        @select-region="onRankingRegionSelect"
    />

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- Drill-down Modal                                           -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
            @click.self="modalOpen = false"
        >
            <div class="flex max-h-[85vh] w-full max-w-7xl flex-col overflow-hidden rounded-2xl bg-background shadow-xl">
                <!-- Modal header -->
                <div class="flex shrink-0 items-center justify-between gap-4 border-b px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                            :class="modalType === 'submitted' ? 'bg-emerald-100 dark:bg-emerald-950/50' : 'bg-rose-100 dark:bg-rose-950/50'"
                        >
                            <FileCheck v-if="modalType === 'submitted'" class="h-4 w-4 text-emerald-600" />
                            <FileX v-else class="h-4 w-4 text-rose-500" />
                        </div>
                        <div>
                            <p class="text-sm font-bold">{{ modalTitle }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    modalLoading
                                        ? 'Loading…'
                                        : modalSearch.trim()
                                          ? `${filteredModalEmployees.length} of ${modalCount} employee(s)`
                                          : `${modalCount} employee(s)`
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="modalLoading || filteredModalEmployees.length === 0"
                            @click="downloadCsv"
                        >
                            <Download class="h-3.5 w-3.5" /> Download CSV
                        </button>
                        <button class="rounded-lg p-1 text-muted-foreground hover:text-foreground" @click="modalOpen = false">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Search -->
                <div v-if="!modalLoading" class="shrink-0 border-b px-5 py-3">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="modalSearch"
                            type="text"
                            placeholder="Search by name, employee code, position, office, or region…"
                            class="h-9 w-full rounded-lg border bg-background pl-9 pr-8 text-sm shadow-none outline-none focus:ring-2 focus:ring-emerald-500/30"
                        />
                        <button
                            v-if="modalSearch"
                            type="button"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                            aria-label="Clear search"
                            @click="modalSearch = ''"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="modalLoading" class="flex justify-center py-12">
                    <Loader2 class="h-7 w-7 animate-spin text-muted-foreground" />
                </div>

                <!-- Table -->
                <div v-else class="flex-1 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="w-8 px-4 py-3 text-left font-semibold">#</th>
                                <th class="px-4 py-3 text-left font-semibold">Name</th>
                                <th class="px-4 py-3 text-left font-semibold">Position</th>
                                <th class="px-4 py-3 text-left font-semibold">Office/Division</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">Region</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">Batch / Program</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">Due Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(emp, i) in filteredModalEmployees"
                                :key="emp.empcode + i"
                                class="cursor-pointer transition-colors hover:bg-muted/30"
                                @click="selectedEmpcode = emp.empcode"
                            >
                                <td class="px-4 py-2.5 text-xs text-muted-foreground">{{ i + 1 }}</td>
                                <td class="px-4 py-2.5">
                                    <p class="font-semibold leading-tight">{{ emp.name }}</p>
                                    <p class="text-[11px] text-muted-foreground">{{ emp.empcode }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-xs text-muted-foreground">{{ emp.position }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-xs text-muted-foreground">{{ emp.office_division }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <span
                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                                    >
                                        {{ emp.region }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-xs">
                                    <p class="whitespace-nowrap text-muted-foreground">{{ emp.batch_name }}</p>
                                    <p
                                        v-if="emp.program_title"
                                        class="max-w-xs truncate text-[11px] text-muted-foreground/80"
                                        :title="emp.program_title"
                                    >
                                        {{ emp.program_title }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <span
                                        class="text-xs font-semibold"
                                        :class="modalType === 'not_submitted' ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'"
                                    >
                                        {{ formatDate(emp.due_date) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="modalEmployees.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-muted-foreground">No employees found.</td>
                            </tr>
                            <tr v-else-if="filteredModalEmployees.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-muted-foreground">No employees match your search.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </Teleport>

    <EmployeeProgressModal :empcode="selectedEmpcode" @close="selectedEmpcode = null" />
</template>

<style scoped>
/* TDI Institutional Micro-Texture — same fine grid + radial-fade technique used
   in the homepage "Digital Resources" section, tinted emerald for the
   Documentation / Records motif, faded from both corners. */
.tdi-texture-document {
    background-image:
        linear-gradient(rgba(52, 211, 153, 0.9) 1px, transparent 1px), linear-gradient(90deg, rgba(52, 211, 153, 0.9) 1px, transparent 1px);
    background-size: 22px 22px;
    -webkit-mask-image:
        radial-gradient(ellipse 200px 200px at 0% 0%, black 0%, transparent 70%),
        radial-gradient(ellipse 200px 200px at 100% 100%, black 0%, transparent 70%);
    mask-image:
        radial-gradient(ellipse 200px 200px at 0% 0%, black 0%, transparent 70%),
        radial-gradient(ellipse 200px 200px at 100% 100%, black 0%, transparent 70%);
}

/* Soft champagne-gold / emerald glow behind the Top Regions trophy watermark. */
.treap-trophy-glow {
    background: radial-gradient(circle at 50% 50%, rgba(234, 200, 120, 0.22) 0%, rgba(16, 185, 129, 0.08) 45%, transparent 70%);
}

:global(.dark) .treap-trophy-glow {
    background: radial-gradient(circle at 50% 50%, rgba(212, 167, 72, 0.12) 0%, rgba(16, 185, 129, 0.06) 45%, transparent 70%);
}
</style>
