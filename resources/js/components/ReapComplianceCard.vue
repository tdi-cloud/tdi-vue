<script setup lang="ts">
import EmployeeProgressModal from '@/components/EmployeeProgressModal.vue';
import { FileText, FileX, Loader2, TrendingUp, Users, X } from 'lucide-vue-next';
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

interface ReapData {
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
    batch_name: string;
    date_end: string;
    due_date: string;
}

const data = ref<ReapData | null>(null);
const loading = ref(false);

// ─── Modal state ─────────────────────────────────────────────────────────────

const modalOpen = ref(false);
const modalType = ref<'submitted' | 'not_submitted'>('submitted');
const modalRegion = ref('ALL');
const modalEmployees = ref<EmployeeRow[]>([]);
const modalLoading = ref(false);
const modalCount = ref(0);

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
        const res = await fetch(`/dashboard/reap-compliance?${params}`);
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
        const res = await fetch(`/dashboard/reap-compliance-list?${params}`);
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
    colors: ['#6366f1', '#f59e0b'],
    fill: {
        type: 'solid',
        opacity: [0.45, 0.35],
    },
    stroke: {
        show: true,
        width: 1.5,
        colors: ['#6366f1', '#f59e0b'],
        lineCap: 'round',
    },
    legend: {
        position: 'bottom',
        fontSize: '12px',
        labels: { colors: ['#6366f1', '#f59e0b'] },
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
                        color: '#6366f1',
                        fontSize: '13px',
                        fontWeight: 700,
                        formatter: () => (data.value ? `${data.value.submitted_pct}%` : '0%'),
                    },
                    value: {
                        color: '#6366f1',
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
    colors: ['#818cf8', '#d97706'],
    xaxis: {
        categories: data.value?.regions ?? REGIONS,
        labels: {
            style: { fontSize: '11px' },
            formatter: (val: number) => `${Math.round(val)}%`,
        },
    },
    yaxis: {
        labels: { style: { fontSize: '11px' } },
    },
    legend: { position: 'top', fontSize: '12px' },
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
    grid: { borderColor: '#f1f5f9' },
}));

const barSeries = computed(() => [
    { name: 'Submitted', data: data.value?.regions_submitted ?? Array(18).fill(0) },
    { name: 'Not Submitted', data: data.value?.regions_not_submitted ?? Array(18).fill(0) },
]);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatDate(d: string | null) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const modalTitle = computed(() => {
    const label = modalType.value === 'submitted' ? 'Submitted' : 'Not Yet Submitted';
    return modalRegion.value === 'ALL' ? `REAP — ${label} (All Regions)` : `REAP — ${label} · ${modalRegion.value}`;
});
</script>

<template>
    <div class="relative isolate flex flex-col gap-5 overflow-hidden rounded-2xl border bg-card p-5 shadow-sm">
        <!-- Decorative background texture — purely visual, sits behind existing content via negative z-index -->
        <div class="tdi-texture-reentry pointer-events-none absolute inset-0 z-[-1] opacity-[0.05] dark:opacity-[0.08]" aria-hidden="true"></div>

        <!-- Header -->
        <div class="flex items-center gap-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-950/50">
                <FileText class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <h2 class="text-sm font-extrabold uppercase tracking-wide text-indigo-700 dark:text-indigo-400">
                    Terminal Report &amp; Re-entry Action Plan (REAP)
                </h2>
                <p class="text-xs text-muted-foreground">REAP submission compliance · click any chart segment to see the list</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent" />
        </div>

        <template v-else-if="data">
            <!-- Stat row -->
            <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-0.5 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                    <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                        <Users class="h-3 w-3" /> Total Required
                    </p>
                    <p class="text-2xl font-extrabold">{{ data.total.toLocaleString() }}</p>
                </div>
                <button
                    class="flex cursor-pointer flex-col gap-0.5 rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-left transition-all hover:brightness-95 dark:border-indigo-800/40 dark:bg-indigo-950/20"
                    @click="fetchList('submitted', 'ALL')"
                >
                    <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-400">
                        <FileText class="h-3 w-3" /> Submitted
                    </p>
                    <p class="text-2xl font-extrabold text-indigo-700 dark:text-indigo-300">{{ data.submitted.toLocaleString() }}</p>
                    <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ data.submitted_pct }}%</p>
                </button>
                <button
                    class="flex cursor-pointer flex-col gap-0.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-left transition-all hover:brightness-95 dark:border-amber-800/40 dark:bg-amber-950/20"
                    @click="fetchList('not_submitted', 'ALL')"
                >
                    <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">
                        <FileX class="h-3 w-3" /> Not Submitted
                    </p>
                    <p class="text-2xl font-extrabold text-amber-600 dark:text-amber-400">{{ data.not_submitted.toLocaleString() }}</p>
                    <p class="text-xs font-semibold text-amber-500 dark:text-amber-400">{{ data.not_submitted_pct }}%</p>
                </button>
            </div>

            <!-- Charts row -->
            <div class="grid grid-cols-1 items-start gap-5 md:grid-cols-[220px_1fr]">
                <!-- Donut -->
                <div class="flex flex-col items-center">
                    <p class="mb-1 text-xs font-semibold text-muted-foreground">Overall Rate</p>
                    <VueApexCharts type="donut" height="220" :options="donutOptions" :series="donutSeries" />
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
            <p class="text-sm">No REAP data available.</p>
        </div>
    </div>

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
                            :class="modalType === 'submitted' ? 'bg-indigo-100 dark:bg-indigo-950/50' : 'bg-amber-100 dark:bg-amber-950/50'"
                        >
                            <FileText v-if="modalType === 'submitted'" class="h-4 w-4 text-indigo-600" />
                            <FileX v-else class="h-4 w-4 text-amber-500" />
                        </div>
                        <div>
                            <p class="text-sm font-bold">{{ modalTitle }}</p>
                            <p class="text-xs text-muted-foreground">{{ modalLoading ? 'Loading…' : `${modalCount} employee(s)` }}</p>
                        </div>
                    </div>
                    <button class="rounded-lg p-1 text-muted-foreground hover:text-foreground" @click="modalOpen = false">
                        <X class="h-4 w-4" />
                    </button>
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
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">Batch</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">Due Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(emp, i) in modalEmployees"
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
                                        class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                                    >
                                        {{ emp.region }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-xs text-muted-foreground">{{ emp.batch_name }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <span
                                        class="text-xs font-semibold"
                                        :class="modalType === 'not_submitted' ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                                    >
                                        {{ formatDate(emp.due_date) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="modalEmployees.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-muted-foreground">No employees found.</td>
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
   in the homepage "Digital Resources" section, tinted indigo for the
   Continuous Learning / Re-entry Pathway motif. */
.tdi-texture-reentry {
    background-image:
        linear-gradient(rgba(99, 102, 241, 0.9) 1px, transparent 1px), linear-gradient(90deg, rgba(99, 102, 241, 0.9) 1px, transparent 1px);
    background-size: 26px 26px;
    -webkit-mask-image: radial-gradient(ellipse 260px 260px at 100% 100%, black 0%, transparent 75%);
    mask-image: radial-gradient(ellipse 260px 260px at 100% 100%, black 0%, transparent 75%);
}
</style>
