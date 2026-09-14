<script setup lang="ts">
import EmployeeProgressModal from '@/components/EmployeeProgressModal.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import axios from 'axios';
import { BriefcaseBusiness, CircleCheckBig, CircleDashed, Download, LoaderCircle, Search, Timer, Users } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

/* ===================== PROPS — filters come from dashboard ===================== */

const props = defineProps<{
    target: 'Nationwide' | 'OPCR';
    region: string;
    selectedStatuses: string[];
    office: string;
}>();

// SG selector — local lang ito, para sa supervisory card lang
const SG_OPTIONS = Array.from({ length: 33 }, (_, i) => i + 1); // 1–33
const sgMin = ref<number>(18); // default: SG ≥ 18

/* ===================== STATS DATA ===================== */

interface SupervisoryStats {
    total: number;
    completed: number;
    in_progress: number;
    not_started: number;
    completed_pct: number;
    in_progress_pct: number;
    not_started_pct: number;
    sg_min: number;
}

const loading = ref(false);

const stats = ref<SupervisoryStats>({
    total: 0,
    completed: 0,
    in_progress: 0,
    not_started: 0,
    completed_pct: 0,
    in_progress_pct: 0,
    not_started_pct: 0,
    sg_min: 19,
});

/* ===================== COUNT-UP ANIMATION ===================== */

const animatedCompleted = ref(0);
const animatedInProgress = ref(0);
const animatedNotStarted = ref(0);
const animatedTotal = ref(0);
const animatedPercent = ref(0);
const animatedInProgressPct = ref(0);
const animatedNotStartedPct = ref(0);

const animateNumber = (targetRef: { value: number }, to: number, duration = 1000) => {
    const from = targetRef.value;
    const start = performance.now();
    const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);
    const step = (now: number) => {
        const progress = Math.min((now - start) / duration, 1);
        targetRef.value = Math.round(from + (to - from) * easeOutCubic(progress));
        if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
};

const fetchStats = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('dashboard.supervisory-compliance'), {
            params: {
                region: props.region === 'ALL' ? null : props.region,
                office_filter: props.target,
                plant_status: props.selectedStatuses,
                sg_min: sgMin.value,
                office: props.office,
            },
        });
        stats.value = data;
        const pct = Math.round(data.completed_pct);
        animateNumber(animatedCompleted, data.completed);
        animateNumber(animatedInProgress, data.in_progress);
        animateNumber(animatedNotStarted, data.not_started);
        animateNumber(animatedTotal, data.total);
        animateNumber(animatedPercent, pct);
        animateNumber(animatedInProgressPct, Math.round(data.in_progress_pct));
        animateNumber(animatedNotStartedPct, Math.round(data.not_started_pct));
    } catch (e) {
        console.error('Failed to load supervisory compliance stats:', e);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchStats);
// Re-fetch kapag nagbago ang shared props O ang local sgMin
watch(() => [props.target, props.region, props.selectedStatuses, sgMin.value, props.office], fetchStats, { deep: true });

/* ===================== EMPLOYEE LIST MODAL ===================== */

interface EmployeeRow {
    EMPCODE: string;
    LASTNAME: string;
    FIRSTNAME: string;
    MI: string;
    POSITION: string;
    office_division: string;
    REGION: string;
    SG: string;
    plantilla_status: string;
    total_hours: number;
}

const showListModal = ref(false);
const listType = ref<'completed' | 'in_progress' | 'not_started'>('completed');
const listLoading = ref(false);
const listSearch = ref('');
const employees = ref<EmployeeRow[]>([]);

const selectedEmpcode = ref<string | null>(null);

const openList = async (type: 'completed' | 'in_progress' | 'not_started') => {
    listType.value = type;
    listSearch.value = '';
    employees.value = [];
    showListModal.value = true;
    listLoading.value = true;
    try {
        const { data } = await axios.get(route('dashboard.supervisory-compliance.list'), {
            params: {
                type,
                region: props.region === 'ALL' ? null : props.region,
                office_filter: props.target,
                plant_status: props.selectedStatuses,
                sg_min: sgMin.value,
                office: props.office,
            },
        });
        employees.value = data.employees;
    } catch (e) {
        console.error('Failed to load supervisory employee list:', e);
    } finally {
        listLoading.value = false;
    }
};

const filteredEmployees = computed(() => {
    const q = listSearch.value.trim().toLowerCase();
    if (!q) return employees.value;
    return employees.value.filter((e) =>
        [e.EMPCODE, e.LASTNAME, e.FIRSTNAME, e.MI, e.POSITION, e.office_division, e.REGION, e.SG, e.plantilla_status]
            .join(' ')
            .toLowerCase()
            .includes(q),
    );
});

const fullName = (e: EmployeeRow) => `${e.LASTNAME}, ${e.FIRSTNAME}${e.MI ? ' ' + e.MI : ''}`;

const downloadCsv = () => {
    const rows = filteredEmployees.value;
    if (!rows.length) return;
    const escape = (val: unknown) => {
        const s = String(val ?? '');
        return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const header = ['#', 'EMPCODE', 'NAME', 'POSITION', 'OFFICE/DIVISION', 'REGION', 'SG', 'STATUS', 'TOTAL HRS'];
    const lines = rows.map((e, i) =>
        [i + 1, e.EMPCODE, fullName(e), e.POSITION, e.office_division, e.REGION, e.SG, e.plantilla_status, e.total_hours].map(escape).join(','),
    );
    const csv = [header.join(','), ...lines].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `supervisory-${listType.value}-employees.csv`;
    link.click();
    URL.revokeObjectURL(url);
};

/* ===================== APEXCHART — triple radial bar ===================== */

const series = computed(() => [Math.round(stats.value.completed_pct), Math.round(stats.value.in_progress_pct)]);

const chartOptions = computed(() => ({
    chart: {
        type: 'radialBar',
        fontFamily: 'inherit',
        animations: { enabled: true, speed: 1000, dynamicAnimation: { enabled: true, speed: 1000 } },
        sparkline: { enabled: true },
    },
    colors: ['#34d399', '#38bdf8'],
    stroke: { lineCap: 'round' },
    plotOptions: {
        radialBar: {
            startAngle: 0,
            endAngle: 360,
            hollow: { size: '45%' },
            track: { background: 'rgba(125, 211, 252, 0.18)', strokeWidth: '100%', margin: 4 },
            dataLabels: { name: { show: false }, value: { show: false } },
        },
    },
}));
</script>

<template>
    <div class="rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
        <!-- HEADER -->
        <div class="flex flex-wrap items-center gap-2 border-b px-5 py-3">
            <h2 class="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-blue-900 dark:text-blue-300">
                <BriefcaseBusiness class="h-4 w-4" /> Supervisory / Managerial Training
                <LoaderCircle v-if="loading" class="h-3.5 w-3.5 animate-spin text-blue-500" />
            </h2>
        </div>

        <!-- CONTENT -->
        <div class="px-5 py-4">
            <div class="flex flex-col items-center justify-center gap-6 sm:flex-row sm:gap-10">
                <!-- LEFT: counts -->
                <div class="flex flex-row items-center gap-8 sm:flex-col sm:items-start sm:gap-6">
                    <button
                        type="button"
                        class="group -mx-2 -my-1 cursor-pointer rounded-xl px-2 py-1 text-left transition-colors hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                        @click="openList('completed')"
                    >
                        <span
                            class="mb-1 block w-fit rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-500 opacity-0 transition-opacity group-hover:opacity-100 dark:bg-emerald-950/60"
                            >view</span
                        >
                        <p class="text-3xl font-extrabold tabular-nums leading-none text-emerald-500">
                            {{ animatedCompleted.toLocaleString() }}
                            <span class="text-lg font-bold text-emerald-400">· {{ animatedPercent }}%</span>
                        </p>
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-bold text-emerald-600"><CircleCheckBig class="h-4 w-4" /> Completed</p>
                    </button>

                    <button
                        type="button"
                        class="group -mx-2 -my-1 cursor-pointer rounded-xl px-2 py-1 text-left transition-colors hover:bg-sky-50 dark:hover:bg-sky-950/40"
                        @click="openList('in_progress')"
                    >
                        <span
                            class="mb-1 block w-fit rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-500 opacity-0 transition-opacity group-hover:opacity-100 dark:bg-sky-950/60"
                            >view</span
                        >
                        <p class="text-3xl font-extrabold tabular-nums leading-none text-sky-500">
                            {{ animatedInProgress.toLocaleString() }}
                            <span class="text-lg font-bold text-sky-400">· {{ animatedInProgressPct }}%</span>
                        </p>
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-bold text-sky-600"><Timer class="h-4 w-4" /> In Progress</p>
                    </button>
                </div>

                <!-- CENTER: chart -->
                <div class="relative">
                    <VueApexCharts type="radialBar" width="230" height="230" :options="chartOptions" :series="series" />
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <p class="text-xl font-extrabold tabular-nums leading-none text-emerald-500">{{ animatedPercent }}%</p>
                        <p class="mt-0.5 text-[9px] font-semibold text-slate-400">Completed</p>
                        <div class="mt-1 h-px w-8 bg-slate-200 dark:bg-slate-700"></div>
                        <p class="mt-1 text-base font-extrabold tabular-nums leading-none text-sky-400">{{ animatedInProgressPct }}%</p>
                        <p class="mt-0.5 text-[9px] font-semibold text-slate-400">In Progress</p>
                    </div>
                </div>

                <!-- RIGHT: not started + total + SG selector -->
                <div class="flex flex-col items-center gap-2 sm:items-start">
                    <button
                        type="button"
                        class="group -mx-2 -my-1 cursor-pointer rounded-xl px-2 py-1 text-left transition-colors hover:bg-slate-100 dark:hover:bg-slate-800/60"
                        @click="openList('not_started')"
                    >
                        <p class="text-lg font-extrabold tabular-nums leading-none text-slate-400">
                            {{ animatedNotStarted.toLocaleString() }}
                            <span class="text-xs font-bold text-slate-300">· {{ animatedNotStartedPct }}%</span>
                        </p>
                        <p class="mt-1 flex items-center gap-1.5 text-xs font-bold text-slate-500">
                            <CircleDashed class="h-3.5 w-3.5" /> Not Started
                            <span
                                class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-500 opacity-0 transition-opacity group-hover:opacity-100 dark:bg-slate-800"
                                >view</span
                            >
                        </p>
                    </button>

                    <p class="text-3xl font-extrabold tabular-nums leading-none text-slate-700 dark:text-slate-200">
                        {{ animatedTotal.toLocaleString() }}
                    </p>
                    <p class="flex items-center gap-1.5 text-sm font-bold"><Users class="h-4 w-4" /> Employees</p>
                    <!-- SG filter — dito lang siya, hindi sa shared filter bar -->
                    <div class="mt-1 flex items-center gap-1.5">
                        <span class="text-[11px] font-bold tracking-wide text-slate-400">SG ≥</span>
                        <Select v-model="sgMin">
                            <SelectTrigger class="h-7 w-16 px-2 text-xs font-semibold">
                                <SelectValue>{{ sgMin }}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sg in SG_OPTIONS" :key="sg" class="text-xs" :value="sg">
                                    {{ sg }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <p class="text-[11px] text-slate-400">40 hrs target</p>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-2 flex items-center justify-center gap-5">
                <span class="flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span> Completed (≥ 40 hrs)
                </span>
                <span class="flex items-center gap-1.5 text-xs font-bold text-sky-400">
                    <span class="h-2.5 w-2.5 rounded-full bg-sky-300"></span> In Progress (&lt; 40 hrs)
                </span>
            </div>
        </div>

        <!-- MODAL -->
        <Dialog :open="showListModal" @update:open="showListModal = $event">
            <DialogContent class="flex max-h-[85vh] !max-w-4xl flex-col gap-3 overflow-hidden !rounded-2xl">
                <DialogHeader class="shrink-0">
                    <DialogTitle class="text-lg font-extrabold">
                        <span
                            :class="{
                                'text-emerald-500': listType === 'completed',
                                'text-sky-500': listType === 'in_progress',
                                'text-slate-400': listType === 'not_started',
                            }"
                        >
                            {{ { completed: 'Completed (≥ 40 hrs)', in_progress: 'In Progress', not_started: 'Not Started' }[listType] }}
                        </span>
                        — Supervisory / Managerial Training
                    </DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground">
                        SG ≥ {{ sgMin }} · Based on current filter selection
                    </DialogDescription>
                </DialogHeader>

                <div class="relative shrink-0">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="listSearch" class="h-10 rounded-xl pl-9 text-sm" placeholder="Search by name, position, office, SG..." />
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <div v-if="listLoading" class="flex items-center justify-center py-16">
                        <LoaderCircle class="h-6 w-6 animate-spin text-blue-600" />
                    </div>
                    <table v-else-if="filteredEmployees.length" class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-background">
                            <tr class="border-b text-left text-xs text-muted-foreground">
                                <th class="w-10 px-3 py-2.5 font-bold">#</th>
                                <th class="px-3 py-2.5 font-bold tracking-wide">NAME</th>
                                <th class="px-3 py-2.5 font-bold tracking-wide">POSITION</th>
                                <th class="px-3 py-2.5 font-bold tracking-wide">OFFICE/DIVISION</th>
                                <th class="px-3 py-2.5 text-center font-bold tracking-wide">SG</th>
                                <th class="px-3 py-2.5 font-bold tracking-wide">STATUS</th>
                                <th class="px-3 py-2.5 text-right font-bold tracking-wide">HRS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(emp, i) in filteredEmployees"
                                :key="emp.EMPCODE + i"
                                class="cursor-pointer border-b last:border-b-0 hover:bg-muted/40"
                                @click="selectedEmpcode = emp.EMPCODE"
                            >
                                <td class="px-3 py-2.5 text-muted-foreground">{{ i + 1 }}</td>
                                <td class="px-3 py-2.5 font-bold uppercase">{{ fullName(emp) }}</td>
                                <td class="px-3 py-2.5">{{ emp.POSITION }}</td>
                                <td class="px-3 py-2.5 text-xs text-muted-foreground">{{ emp.office_division }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span
                                        class="inline-block rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-bold text-violet-600 dark:bg-violet-950/60 dark:text-violet-300"
                                    >
                                        {{ emp.SG }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-block whitespace-nowrap rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-600 dark:bg-blue-950/60 dark:text-blue-300"
                                    >
                                        {{ emp.plantilla_status }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right font-extrabold tabular-nums"
                                    :class="emp.total_hours >= 40 ? 'text-emerald-500' : emp.total_hours > 0 ? 'text-sky-500' : 'text-slate-400'"
                                >
                                    {{ emp.total_hours }}<span class="text-[10px] font-normal text-muted-foreground"> hrs</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="flex flex-col items-center justify-center py-16 text-center text-muted-foreground">
                        <p class="text-xs font-semibold">No employees found.</p>
                        <p class="mt-1 text-[11px]">Try a different search or adjust the filters.</p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center justify-between border-t pt-3">
                    <p class="text-sm text-muted-foreground">Showing {{ filteredEmployees.length.toLocaleString() }} employee(s)</p>
                    <Button
                        size="sm"
                        class="rounded-full bg-emerald-500 px-4 text-white hover:bg-emerald-600"
                        :disabled="listLoading || filteredEmployees.length === 0"
                        @click="downloadCsv"
                    >
                        <Download class="mr-1 h-4 w-4" /> Download CSV
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <EmployeeProgressModal :empcode="selectedEmpcode" @close="selectedEmpcode = null" />
    </div>
</template>
