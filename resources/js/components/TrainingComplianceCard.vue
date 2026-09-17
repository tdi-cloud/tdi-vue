<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import axios from 'axios';
import { CircleCheckBig, CircleX, Download, GraduationCap, LoaderCircle, Search, Users } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

/* ===================== PROPS — filters come from dashboard ===================== */

const props = defineProps<{
    target: 'Nationwide' | 'OPCR';
    region: string;
    selectedStatuses: string[];
    office: string;
    year: string;
}>();

/* ===================== STATS DATA ===================== */

interface ComplianceStats {
    total: number;
    trained: number;
    not_trained: number;
    trained_percentage: number;
    not_trained_percentage: number;
}

const loading = ref(false);

const stats = ref<ComplianceStats>({
    total: 0,
    trained: 0,
    not_trained: 0,
    trained_percentage: 0,
    not_trained_percentage: 0,
});

/* ===================== COUNT-UP ANIMATION ===================== */

const animatedTrained = ref(0);
const animatedNotTrained = ref(0);
const animatedTotal = ref(0);
const animatedPercent = ref(0);
const chartPercent = ref(100);

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
        const { data } = await axios.get(route('dashboard.training-compliance'), {
            params: {
                region: props.region,
                office_filter: props.target,
                plant_status: props.selectedStatuses,
                office: props.office,
                year: props.year,
            },
        });
        stats.value = data;
        const pct = Math.round(data.trained_percentage);
        chartPercent.value = pct;
        animateNumber(animatedTrained, data.trained);
        animateNumber(animatedNotTrained, data.not_trained);
        animateNumber(animatedTotal, data.total);
        animateNumber(animatedPercent, pct);
    } catch (e) {
        console.error('Failed to load training compliance stats:', e);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchStats);
// Re-fetch kapag nagbago ang shared props
watch(() => [props.target, props.region, props.selectedStatuses, props.office, props.year], fetchStats, { deep: true });

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
}

const showListModal = ref(false);
const listType = ref<'trained' | 'not_trained'>('trained');
const listLoading = ref(false);
const listSearch = ref('');
const employees = ref<EmployeeRow[]>([]);

const openList = async (type: 'trained' | 'not_trained') => {
    listType.value = type;
    listSearch.value = '';
    employees.value = [];
    showListModal.value = true;
    listLoading.value = true;
    try {
        const { data } = await axios.get(route('dashboard.training-compliance.list'), {
            params: {
                type,
                region: props.region,
                office_filter: props.target,
                plant_status: props.selectedStatuses,
                office: props.office,
                year: props.year,
            },
        });
        employees.value = data.employees;
    } catch (e) {
        console.error('Failed to load employee list:', e);
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
    const header = ['#', 'EMPCODE', 'NAME', 'POSITION', 'OFFICE/DIVISION', 'REGION', 'SG', 'STATUS'];
    const lines = rows.map((e, i) =>
        [i + 1, e.EMPCODE, fullName(e), e.POSITION, e.office_division, e.REGION, e.SG, e.plantilla_status].map(escape).join(','),
    );
    const csv = [header.join(','), ...lines].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${listType.value === 'trained' ? 'with-training' : 'no-training'}-employees.csv`;
    link.click();
    URL.revokeObjectURL(url);
};

/* ===================== APEXCHART ===================== */

const series = computed(() => [chartPercent.value]);

const chartOptions = {
    chart: {
        type: 'radialBar',
        fontFamily: 'inherit',
        animations: { enabled: true, speed: 1000, dynamicAnimation: { enabled: true, speed: 1000 } },
        sparkline: { enabled: true },
    },
    colors: ['#34d399'],
    stroke: { lineCap: 'round' },
    plotOptions: {
        radialBar: {
            startAngle: 0,
            endAngle: 360,
            hollow: { size: '60%' },
            track: { background: 'rgba(125, 211, 252, 0.3)', strokeWidth: '100%' },
            dataLabels: { name: { show: false }, value: { show: false } },
        },
    },
};
</script>

<template>
    <div class="relative isolate overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
        <!-- Decorative background texture — purely visual, sits behind existing content via negative z-index -->
        <div class="tdi-texture-learning pointer-events-none absolute inset-0 z-[-1] opacity-[0.05] dark:opacity-[0.08]" aria-hidden="true"></div>

        <!-- HEADER -->
        <div class="flex flex-wrap items-center gap-2 border-b px-5 py-3">
            <h2 class="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-blue-900 dark:text-blue-300">
                <GraduationCap class="h-4 w-4" /> Employees Training Compliance Rate
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
                        @click="openList('trained')"
                    >
                        <span
                            class="mb-1 block w-fit rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-500 opacity-0 transition-opacity group-hover:opacity-100 dark:bg-emerald-950/60"
                            >view</span
                        >
                        <p class="text-3xl font-extrabold tabular-nums leading-none text-emerald-500">{{ animatedTrained.toLocaleString() }}</p>
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-bold text-emerald-600">
                            <CircleCheckBig class="h-4 w-4" /> With Training
                        </p>
                    </button>

                    <button
                        type="button"
                        class="group -mx-2 -my-1 cursor-pointer rounded-xl px-2 py-1 text-left transition-colors hover:bg-sky-50 dark:hover:bg-sky-950/40"
                        @click="openList('not_trained')"
                    >
                        <span
                            class="mb-1 block w-fit rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-500 opacity-0 transition-opacity group-hover:opacity-100 dark:bg-sky-950/60"
                            >view</span
                        >
                        <p class="text-3xl font-extrabold tabular-nums leading-none text-sky-500">{{ animatedNotTrained.toLocaleString() }}</p>
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-bold text-sky-600"><CircleX class="h-4 w-4" /> No Training</p>
                    </button>
                </div>

                <!-- CENTER: chart -->
                <div class="relative">
                    <VueApexCharts type="radialBar" width="230" height="230" :options="chartOptions" :series="series" />
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <p class="text-3xl font-extrabold tabular-nums leading-none text-emerald-500">{{ animatedPercent }}%</p>
                        <p class="mt-1 text-xs font-semibold text-slate-400">With Training</p>
                    </div>
                </div>

                <!-- RIGHT: total -->
                <div class="flex flex-col items-center gap-2 sm:items-start">
                    <p class="text-3xl font-extrabold tabular-nums leading-none text-slate-700 dark:text-slate-200">
                        {{ animatedTotal.toLocaleString() }}
                    </p>
                    <p class="mt-1 flex items-center gap-1.5 text-sm font-bold"><Users class="h-4 w-4" /> Employees</p>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-2 flex items-center justify-center gap-5">
                <span class="flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span> With Training
                </span>
                <span class="flex items-center gap-1.5 text-xs font-bold text-sky-500">
                    <span class="h-2.5 w-2.5 rounded-full bg-sky-300"></span> No Training
                </span>
            </div>
        </div>

        <!-- MODAL -->
        <Dialog :open="showListModal" @update:open="showListModal = $event">
            <DialogContent class="flex max-h-[85vh] !max-w-4xl flex-col gap-3 overflow-hidden !rounded-2xl">
                <DialogHeader class="shrink-0">
                    <DialogTitle class="text-lg font-extrabold">
                        <span :class="listType === 'trained' ? 'text-emerald-500' : 'text-cyan-500'">
                            {{ listType === 'trained' ? 'With Training' : 'No Training' }}
                        </span>
                        Employees
                    </DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground"> Based on current filter selection </DialogDescription>
                </DialogHeader>

                <div class="relative shrink-0">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="listSearch" class="h-10 rounded-xl pl-9 text-sm" placeholder="Search by name, position, office..." />
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
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(emp, i) in filteredEmployees" :key="emp.EMPCODE + i" class="border-b last:border-b-0 hover:bg-muted/40">
                                <td class="px-3 py-2.5 text-muted-foreground">{{ i + 1 }}</td>
                                <td class="px-3 py-2.5 font-bold uppercase">{{ fullName(emp) }}</td>
                                <td class="px-3 py-2.5">{{ emp.POSITION }}</td>
                                <td class="px-3 py-2.5 text-xs text-muted-foreground">{{ emp.office_division }}</td>
                                <td class="px-3 py-2.5 text-center text-xs text-muted-foreground">{{ emp.SG }}</td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-block whitespace-nowrap rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-600 dark:bg-blue-950/60 dark:text-blue-300"
                                    >
                                        {{ emp.plantilla_status }}
                                    </span>
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
                    <p class="text-sm text-muted-foreground">Showing {{ filteredEmployees.length.toLocaleString() }} employees</p>
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
    </div>
</template>

<style scoped>
/* TDI Institutional Micro-Texture — same fine grid + radial-fade technique used
   in the homepage "Digital Resources" section, tinted emerald for the Learning
   Network motif (Employees → Learning → Competency → Development). */
.tdi-texture-learning {
    background-image:
        linear-gradient(rgba(52, 211, 153, 0.9) 1px, transparent 1px), linear-gradient(90deg, rgba(52, 211, 153, 0.9) 1px, transparent 1px);
    background-size: 26px 26px;
    -webkit-mask-image: radial-gradient(ellipse 220px 220px at 100% 0%, black 0%, transparent 75%);
    mask-image: radial-gradient(ellipse 220px 220px at 100% 0%, black 0%, transparent 75%);
}
</style>
