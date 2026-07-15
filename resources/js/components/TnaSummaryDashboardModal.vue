<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import axios from 'axios';
import { X, Gauge, Users2, TrendingUp } from 'lucide-vue-next';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps<{
    filters: {
        search?: string;
        region?: string;
        office?: string;
    };
}>();

const emit = defineEmits(['close']);

const loading = ref(false);
const errorMsg = ref('');
const totalEmployees = ref(0);

const donutOptions = ref<any>({
    chart: {
        type: 'donut',
        animations: {
            enabled: true, easing: 'easeinout', speed: 600,
            animateGradually: { enabled: true, delay: 120 },
            dynamicAnimation: { enabled: true, speed: 350 },
        },
        dropShadow: { enabled: true, top: 2, left: 0, blur: 6, opacity: 0.12 },
    },
    labels: [],
    legend: { position: 'bottom', fontSize: '12px', markers: { offsetX: -2 } },
    colors: ['#ef4444', '#f59e0b', '#eab308', '#3b82f6', '#10b981'],
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
const donutSeries = ref<number[]>([]);

const barOptions = ref<any>({
    chart: {
        type: 'bar', toolbar: { show: false },
        animations: {
            enabled: true, easing: 'easeinout', speed: 600,
            animateGradually: { enabled: true, delay: 120 },
            dynamicAnimation: { enabled: true, speed: 350 },
        },
    },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '55%' } },
    dataLabels: { enabled: true },
    xaxis: { categories: [] },
    fill: {
        type: 'gradient',
        gradient: { shade: 'light', type: 'horizontal', shadeIntensity: 0.3, opacityFrom: 1, opacityTo: 0.85, stops: [0, 100] },
    },
    colors: ['#6366f1'],
    grid: { borderColor: '#f1f5f9' },
});
const barSeries = ref<number[]>([]);

let activeController: AbortController | null = null;

const fetchDashboard = async () => {
    if (activeController) activeController.abort();
    const controller = new AbortController();
    activeController = controller;
    loading.value = true;
    errorMsg.value = '';

    try {
        const { data } = await axios.get(route('tna-summary.dashboard-data'), {
            params: {
                search: props.filters.search || undefined,
                region: props.filters.region || undefined,
                office: props.filters.office || undefined,
            },
            signal: controller.signal,
        });

        totalEmployees.value = data.total_employees;
        donutOptions.value = { ...donutOptions.value, labels: data.band_labels };
        donutSeries.value = data.band_series;
        barOptions.value = { ...barOptions.value, xaxis: { ...barOptions.value.xaxis, categories: data.top_units } };
        barSeries.value = data.top_units_series;
    } catch (err: any) {
        if (axios.isCancel(err) || err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return;
        console.error('TNA dashboard fetch failed:', err?.response?.data ?? err);
        errorMsg.value = `Failed to load dashboard data${err?.response?.status ? ` (${err.response.status})` : ''}.`;
    } finally {
        if (activeController === controller) {
            loading.value = false;
            activeController = null;
        }
    }
};

watch(() => props.filters, fetchDashboard, { deep: true });

onMounted(fetchDashboard);
onBeforeUnmount(() => {
    if (activeController) { activeController.abort(); activeController = null; }
});
</script>

<template>
    <Transition name="backdrop" appear>
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
            <Transition name="pop" appear>
                <div class="bg-background rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">

                    <!-- Header -->
                    <div class="sticky top-0 z-10 bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 border-b px-6 py-4 rounded-t-2xl flex items-center gap-3 text-white">
                        <div class="flex items-center justify-center h-9 w-9 rounded-xl bg-white/20 backdrop-blur shadow">
                            <Gauge class="h-4 w-4 text-white" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold leading-none">TNA Results Dashboard</h2>
                            <p class="text-xs text-white/75 mt-0.5">Org-wide competency &amp; training-need overview</p>
                        </div>
                        <button class="ml-auto text-white/80 hover:text-white transition-colors" @click="emit('close')">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-6 flex flex-col gap-6">

                        <p v-if="errorMsg" class="text-xs text-red-600 text-center">{{ errorMsg }}</p>

                        <!-- Stat tile -->
                        <div class="anim-in rounded-xl border bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-950/40 dark:to-indigo-900/20 p-4 flex items-center gap-3" style="animation-delay:0ms">
                            <div class="h-10 w-10 rounded-xl bg-indigo-600 flex items-center justify-center shadow-sm">
                                <Users2 class="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Employees with Finalized TNA Results</p>
                                <p class="text-2xl font-bold">{{ totalEmployees }}</p>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="anim-in rounded-xl border p-4" style="animation-delay:80ms">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-red-500"></span> Competency Band Distribution
                                </p>
                                <VueApexCharts type="donut" height="300" :options="donutOptions" :series="donutSeries" />
                            </div>
                            <div class="anim-in rounded-xl border p-4" style="animation-delay:160ms">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2 flex items-center gap-1.5">
                                    <TrendingUp class="h-3.5 w-3.5 text-indigo-500" /> Top 5 Training Priorities (Org-wide)
                                </p>
                                <VueApexCharts type="bar" height="300" :options="barOptions" :series="[{ name: 'Employees', data: barSeries }]" />
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
.backdrop-leave-to     { opacity: 0; }
.pop-enter-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
.pop-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.pop-enter-from   { opacity: 0; transform: scale(0.94) translateY(8px); }
.pop-leave-to     { opacity: 0; transform: scale(0.97); }
</style>
