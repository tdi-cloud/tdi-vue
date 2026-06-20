<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue';
import axios from 'axios';
import {
    X, BarChart3, Users2, Filter, CheckCircle2,
    Building2, CalendarDays, TrendingUp,
} from 'lucide-vue-next';
import VueApexCharts from 'vue3-apexcharts';

const emit = defineEmits(['close']);

const statusOptions: Record<string, string> = {
    endorsed: 'Endorsed',
    waiting_result: 'Waiting Result',
    not_endorsed: 'Not Endorsed',
    accepted: 'Accepted',
    regret: 'Regret',
    cancelled: 'Cancelled',
};

const statusGaugeColors: Record<string, [string, string]> = {
    endorsed:       ['#a78bfa', '#7c3aed'],
    waiting_result: ['#22d3ee', '#0891b2'],
    not_endorsed:   ['#f87171', '#dc2626'],
    accepted:       ['#38bdf8', '#34d399'],
    regret:         ['#fbbf24', '#d97706'],
    cancelled:      ['#9ca3af', '#4b5563'],
};

const filterStatus = ref('accepted');
const filterAgency = ref('TESDA');
const filterYear   = ref('');

const loading = ref(false);
const errorMsg = ref('');
// const agencyOptions = ref<string[]>([]);
const yearOptions   = ref<number[]>([]);

const selectedStatusCount = ref(0);
const totalParticipants   = ref(0);

const gaugePercent = computed(() =>
    totalParticipants.value > 0
        ? Math.round((selectedStatusCount.value / totalParticipants.value) * 100)
        : 0
);

const gaugeSeries = computed(() => [gaugePercent.value]);
const gaugeOptions = computed(() => {
    const [from, to] = statusGaugeColors[filterStatus.value] ?? ['#38bdf8', '#34d399'];
    return {
        chart: {
            type: 'radialBar',
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 700,
                animateGradually: { enabled: true, delay: 100 },
                dynamicAnimation: { enabled: true, speed: 400 },
            },
        },
        plotOptions: {
            radialBar: {
                hollow: { size: '62%' },
                track: { background: '#eef2f7', strokeWidth: '100%' },
                dataLabels: {
                    name: {
                        fontSize: '11px',
                        fontWeight: 700,
                        color: '#6b7280',
                        offsetY: 22,
                        formatter: () => statusOptions[filterStatus.value]?.toUpperCase() ?? '',
                    },
                    value: {
                        fontSize: '30px',
                        fontWeight: 800,
                        offsetY: -8,
                        formatter: (val: number) => val + '%',
                    },
                },
            },
        },
        stroke: { lineCap: 'round' },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: 'horizontal',
                shadeIntensity: 0.5,
                gradientToColors: [to],
                inverseColors: false,
                stops: [0, 100],
            },
        },
        colors: [from],
        labels: [statusOptions[filterStatus.value]],
    };
});

const donutSeries = ref<number[]>([]);
const donutOptions = ref<any>({
    chart: {
        type: 'donut',
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 600,
            animateGradually: { enabled: true, delay: 120 },
            dynamicAnimation: { enabled: true, speed: 350 },
        },
        dropShadow: { enabled: true, top: 2, left: 0, blur: 6, opacity: 0.12 },
    },
    labels: [],
    legend: { position: 'bottom', fontSize: '12px', markers: { offsetX: -2 } },
    colors: ['#8b5cf6', '#06b6d4', '#ef4444', '#10b981', '#f59e0b', '#9ca3af'],
    stroke: { width: 3, colors: ['#ffffff'] },
    dataLabels: { enabled: true, formatter: (val: number) => val.toFixed(0) + '%' },
    plotOptions: {
        pie: {
            expandOnClick: true,
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    total: { show: true, label: 'Total', fontSize: '12px', fontWeight: 700 },
                    value: { fontSize: '22px', fontWeight: 800 },
                },
            },
        },
    },
});

const barReceived = ref<number[]>([]);
const barDisseminated = ref<number[]>([]);
const barOptions = ref<any>({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 600,
            animateGradually: { enabled: true, delay: 120 },
            dynamicAnimation: { enabled: true, speed: 350 },
        },
    },
    plotOptions: { bar: { columnWidth: '50%', borderRadius: 6, borderRadiusApplication: 'end' } },
    dataLabels: { enabled: false },
    xaxis: { categories: [], labels: { style: { fontSize: '11px' } } },
    fill: {
        type: 'gradient',
        gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.3, opacityFrom: 1, opacityTo: 0.85, stops: [0, 100] },
    },
    colors: ['#3b82f6', '#10b981'],
    legend: { position: 'top' },
    grid: { borderColor: '#f1f5f9' },
});

// keeps track of the in-flight request so we can cancel it ourselves
let activeController: AbortController | null = null;

const fetchDashboard = async () => {
    if (activeController) activeController.abort();
    const controller = new AbortController();
    activeController = controller;

    loading.value = true;
    errorMsg.value = '';

    try {
        const { data } = await axios.get(route('foreign-programs.dashboard-data'), {
            params: {
                status: filterStatus.value || undefined,
                agency: filterAgency.value || undefined,
                year: filterYear.value || undefined,
            },
            signal: controller.signal,
        });

        // agencyOptions.value = data.agencies ?? [];
        yearOptions.value   = data.years ?? [];

        selectedStatusCount.value = data.participants.selectedStatusCount;
        totalParticipants.value   = data.participants.totalParticipants;

        donutOptions.value = { ...donutOptions.value, labels: data.participants.statusLabels };
        donutSeries.value  = data.participants.statusSeries;

        barOptions.value      = { ...barOptions.value, xaxis: { ...barOptions.value.xaxis, categories: data.programs.sponsors } };
        barReceived.value     = data.programs.received;
        barDisseminated.value = data.programs.disseminated;
    } catch (err: any) {
        if (axios.isCancel(err) || err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') {
            return;
        }
        console.error('Failed to load dashboard data:', err?.response?.data ?? err);
        errorMsg.value = `Failed to load dashboard data${err?.response?.status ? ` (${err.response.status})` : ''}.`;
    } finally {
        if (activeController === controller) {
            loading.value = false;
            activeController = null;
        }
    }
};

let debounce: ReturnType<typeof setTimeout>;
watch([filterStatus, filterAgency, filterYear], () => {
    clearTimeout(debounce);
    debounce = setTimeout(fetchDashboard, 250);
});

onMounted(fetchDashboard);

onBeforeUnmount(() => {
    clearTimeout(debounce);
    if (activeController) {
        activeController.abort();
        activeController = null;
    }
});
</script>

<template>
    <Transition name="backdrop" appear>
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
            <Transition name="pop" appear>
                <div class="bg-background rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto">

                    <!-- Header -->
                    <div class="sticky top-0 z-10 bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 border-b px-6 py-4 rounded-t-2xl flex items-center gap-3 text-white">
                        <div class="flex items-center justify-center h-9 w-9 rounded-xl bg-white/20 backdrop-blur shadow">
                            <BarChart3 class="h-4 w-4 text-white" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold leading-none">Foreign Programs Dashboard</h2>
                            <p class="text-xs text-white/75 mt-0.5">Overview ng participants at programs</p>
                        </div>
                        <button @click="emit('close')" class="ml-auto text-white/80 hover:text-white transition-colors">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-6 flex flex-col gap-6">

                        <!-- Filters -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="anim-in rounded-xl border border-indigo-200 dark:border-indigo-900 bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-950/30 dark:to-background p-3 flex flex-col gap-1.5" style="animation-delay:0ms">
                                <label class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                    <span class="flex items-center justify-center h-5 w-5 rounded-md bg-indigo-600 shadow-sm">
                                        <CheckCircle2 class="h-3 w-3 text-white" />
                                    </span>
                                    Status
                                </label>
                                <select v-model="filterStatus" class="border border-indigo-200 dark:border-indigo-900 rounded-lg px-2 py-1.5 text-xs bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                    <option v-for="(label, key) in statusOptions" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>

                            <div class="anim-in rounded-xl border border-emerald-200 dark:border-emerald-900 bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-950/30 dark:to-background p-3 flex flex-col gap-1.5" style="animation-delay:80ms">
                                <label class="text-[10px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <span class="flex items-center justify-center h-5 w-5 rounded-md bg-emerald-600 shadow-sm">
                                        <Building2 class="h-3 w-3 text-white" />
                                    </span>
                                    Agency
                                </label>
                                <select v-model="filterAgency" class="border border-emerald-200 dark:border-emerald-900 rounded-lg px-2 py-1.5 text-xs bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-emerald-400">
                                    <option value="TESDA">TESDA</option>
                                    <option value="">All Other Offices</option>
                                </select>
                            </div>

                            <div class="anim-in rounded-xl border border-amber-200 dark:border-amber-900 bg-gradient-to-br from-amber-50 to-white dark:from-amber-950/30 dark:to-background p-3 flex flex-col gap-1.5" style="animation-delay:160ms">
                                <label class="text-[10px] font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                    <span class="flex items-center justify-center h-5 w-5 rounded-md bg-amber-500 shadow-sm">
                                        <CalendarDays class="h-3 w-3 text-white" />
                                    </span>
                                    Year
                                </label>
                                <select v-model="filterYear" class="border border-amber-200 dark:border-amber-900 rounded-lg px-2 py-1.5 text-xs bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-amber-400">
                                    <option value="">All Years</option>
                                    <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                        </div>

                        <p v-if="errorMsg" class="text-xs text-red-600 text-center">{{ errorMsg }}</p>

                        <!-- KPI: gauge + stat tiles -->
                        <div class="grid grid-cols-1 md:grid-cols-[1.1fr_1fr] gap-4">
                            <div class="anim-in rounded-2xl border p-4 flex flex-col items-center justify-center bg-gradient-to-br from-slate-50 to-white dark:from-slate-900/40 dark:to-background" style="animation-delay:200ms">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground self-start mb-1 flex items-center gap-1.5">
                                    <TrendingUp class="h-3.5 w-3.5 text-indigo-500" /> Status Rate
                                </p>
                                <VueApexCharts type="radialBar" height="220" :options="gaugeOptions" :series="gaugeSeries" />
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <div class="anim-in rounded-xl border bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-950/40 dark:to-indigo-900/20 p-4 flex items-center gap-3" style="animation-delay:260ms">
                                    <div class="h-10 w-10 rounded-xl bg-indigo-600 flex items-center justify-center shadow-sm">
                                        <Users2 class="h-5 w-5 text-white" />
                                    </div>
                                    <div>
                                        <p class="text-xs text-muted-foreground">{{ statusOptions[filterStatus] }} Participants</p>
                                        <p class="text-2xl font-bold">{{ selectedStatusCount }}</p>
                                    </div>
                                </div>
                                <div class="anim-in rounded-xl border bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-950/40 dark:to-blue-900/20 p-4 flex items-center gap-3" style="animation-delay:320ms">
                                    <div class="h-10 w-10 rounded-xl bg-blue-600 flex items-center justify-center shadow-sm">
                                        <Users2 class="h-5 w-5 text-white" />
                                    </div>
                                    <div>
                                        <p class="text-xs text-muted-foreground">Total Participants (filtered)</p>
                                        <p class="text-2xl font-bold">{{ totalParticipants }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="anim-in rounded-xl border p-4" style="animation-delay:380ms">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-violet-500"></span> Participants by Status
                                </p>
                                <VueApexCharts type="donut" height="300" :options="donutOptions" :series="donutSeries" />
                            </div>
                            <div class="anim-in rounded-xl border p-4" style="animation-delay:440ms">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-blue-500"></span> Programs per Organizing Sponsor
                                </p>
                                <VueApexCharts type="bar" height="300" :options="barOptions"
                                    :series="[{ name: 'Received', data: barReceived }, { name: 'Disseminated', data: barDisseminated }]" />
                            </div>
                        </div>

                        <p v-if="loading" class="text-xs text-muted-foreground text-center">Loading...</p>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.anim-in {
    opacity: 0;
    animation: fadeSlideIn 0.45s ease-out forwards;
}

.backdrop-enter-active,
.backdrop-leave-active { transition: opacity 0.2s ease; }
.backdrop-enter-from,
.backdrop-leave-to { opacity: 0; }

.pop-enter-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
.pop-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.pop-enter-from { opacity: 0; transform: scale(0.94) translateY(8px); }
.pop-leave-to   { opacity: 0; transform: scale(0.97); }
</style>