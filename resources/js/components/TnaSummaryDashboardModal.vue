<script setup lang="ts">
import axios from 'axios';
import { Gauge, TrendingUp, Users2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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

// ── Chart colors per theme ──────────────────────────────────────────────────
// ApexCharts draws its own text (legends, axis labels, center values) in a dark
// grey by default, which disappears on the dark background. Follow the `dark`
// class on <html> (set from the system or user preference) and recolor the charts.
const isDark = ref(document.documentElement.classList.contains('dark'));
let themeObserver: MutationObserver | null = null;

const chartTheme = computed(() =>
    isDark.value
        ? { text: '#cbd5e1', strongText: '#f1f5f9', muted: '#94a3b8', grid: '#334155', surface: '#0a0a0a', tooltip: 'dark' as const }
        : { text: '#475569', strongText: '#1e293b', muted: '#6b7280', grid: '#f1f5f9', surface: '#ffffff', tooltip: 'light' as const },
);

// Theme-aware copy of the donut options: text, slice borders and center labels follow light/dark mode.
const donutChartOptions = computed(() => {
    const t = chartTheme.value;
    const base = donutOptions.value;
    return {
        ...base,
        chart: { ...base.chart, foreColor: t.text },
        legend: { ...base.legend, labels: { colors: t.text } },
        stroke: { ...base.stroke, colors: [t.surface] },
        tooltip: { theme: t.tooltip },
        plotOptions: {
            ...base.plotOptions,
            pie: {
                ...base.plotOptions.pie,
                donut: {
                    ...base.plotOptions.pie.donut,
                    labels: {
                        ...base.plotOptions.pie.donut.labels,
                        name: { color: t.text },
                        value: { ...base.plotOptions.pie.donut.labels.value, color: t.strongText },
                        total: { ...base.plotOptions.pie.donut.labels.total, color: t.muted },
                    },
                },
            },
        },
    };
});

// Theme-aware copy of the bar options: axis labels, data labels and grid lines follow light/dark mode.
const barChartOptions = computed(() => {
    const t = chartTheme.value;
    const base = barOptions.value;
    return {
        ...base,
        chart: { ...base.chart, foreColor: t.text },
        xaxis: { ...base.xaxis, labels: { style: { colors: t.text } } },
        yaxis: { labels: { style: { colors: t.text } } },
        grid: { ...base.grid, borderColor: t.grid },
        tooltip: { theme: t.tooltip },
    };
});

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

onMounted(() => {
    fetchDashboard();
    themeObserver = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
onBeforeUnmount(() => {
    themeObserver?.disconnect();
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
                <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-background shadow-2xl">
                    <!-- Header -->
                    <div
                        class="sticky top-0 z-10 flex items-center gap-3 rounded-t-2xl border-b bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 px-6 py-4 text-white"
                    >
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 shadow backdrop-blur">
                            <Gauge class="h-4 w-4 text-white" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold leading-none">TNA Results Dashboard</h2>
                            <p class="mt-0.5 text-xs text-white/75">Org-wide competency &amp; training-need overview</p>
                        </div>
                        <button class="ml-auto text-white/80 transition-colors hover:text-white" @click="emit('close')">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="flex flex-col gap-6 p-6">
                        <p v-if="errorMsg" class="text-center text-xs text-red-600">{{ errorMsg }}</p>

                        <!-- Stat tile -->
                        <div
                            class="anim-in flex items-center gap-3 rounded-xl border bg-gradient-to-br from-indigo-50 to-indigo-100 p-4 dark:from-indigo-950/40 dark:to-indigo-900/20"
                            style="animation-delay: 0ms"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 shadow-sm">
                                <Users2 class="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Employees with Finalized TNA Results</p>
                                <p class="text-2xl font-bold">{{ totalEmployees }}</p>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <div class="anim-in rounded-xl border p-4" style="animation-delay: 80ms">
                                <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <span class="h-2 w-2 rounded-full bg-red-500"></span> Competency Band Distribution
                                </p>
                                <VueApexCharts type="donut" height="300" :options="donutChartOptions" :series="donutSeries" />
                            </div>
                            <div class="anim-in rounded-xl border p-4" style="animation-delay: 160ms">
                                <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <TrendingUp class="h-3.5 w-3.5 text-indigo-500" /> Top 5 Training Priorities (Org-wide)
                                </p>
                                <VueApexCharts
                                    type="bar"
                                    height="300"
                                    :options="barChartOptions"
                                    :series="[{ name: 'Employees', data: barSeries }]"
                                />
                            </div>
                        </div>

                        <p v-if="loading" class="text-center text-xs text-muted-foreground">Loading...</p>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
@keyframes fadeSlideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.anim-in {
    opacity: 0;
    animation: fadeSlideIn 0.45s ease-out forwards;
}
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.2s ease;
}
.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}
.pop-enter-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.pop-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}
.pop-enter-from {
    opacity: 0;
    transform: scale(0.94) translateY(8px);
}
.pop-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
</style>
