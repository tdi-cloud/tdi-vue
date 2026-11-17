<script setup lang="ts">
import type { FormDashboardData } from '@/lib/publicForms';
import type { ApexOptions } from 'apexcharts';
import { computed, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { people, useChartTheme } from './charts';
import DashboardCard from './DashboardCard.vue';
import DashboardTable from './DashboardTable.vue';

/**
 * Responses over time. "Per day" and "Running total" are two views of one
 * chart (one scale at a time), never two scales on one chart.
 */
const props = defineProps<{ timeline: FormDashboardData['timeline'] }>();

type View = 'daily' | 'total';
const view = ref<View>('daily');

const { baseOptions, primary, theme } = useChartTheme();

const busiestDay = computed(() =>
    props.timeline.reduce<(typeof props.timeline)[number] | null>((best, day) => (!best || day.count > best.count ? day : best), null),
);
const average = computed(() => {
    if (!props.timeline.length) return 0;
    return props.timeline[props.timeline.length - 1].total / props.timeline.length;
});

function longDate(day: string): string {
    return new Date(`${day}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

const series = computed(() => [
    {
        name: view.value === 'daily' ? 'Responses that day' : 'Responses so far',
        data: props.timeline.map((day) => ({ x: new Date(`${day.date}T00:00:00`).getTime(), y: view.value === 'daily' ? day.count : day.total })),
    },
]);

const options = computed<ApexOptions>(() => ({
    ...baseOptions.value,
    chart: { ...baseOptions.value.chart, type: 'area' },
    colors: [primary.value],
    stroke: { curve: 'monotoneCubic', width: 2, lineCap: 'round' },
    fill: { type: 'solid', opacity: 0.12 },
    dataLabels: { enabled: false },
    markers: {
        size: props.timeline.length === 1 ? 5 : 0,
        strokeColors: theme.value.surface,
        strokeWidth: 2,
        hover: { size: 5 },
    },
    xaxis: {
        type: 'datetime',
        labels: { datetimeUTC: false, style: { fontSize: '10px' } },
        axisBorder: { show: true, color: theme.value.grid },
        axisTicks: { show: false },
        crosshairs: { show: true, stroke: { color: theme.value.muted, width: 1, dashArray: 0 } },
        tooltip: { enabled: false },
    },
    yaxis: {
        min: 0,
        forceNiceScale: true,
        tickAmount: 4,
        labels: { formatter: (value: number) => Math.round(value).toLocaleString(), style: { fontSize: '10px' } },
    },
    grid: { ...baseOptions.value.grid, yaxis: { lines: { show: true } }, padding: { left: 6, right: 10 } },
    tooltip: {
        ...baseOptions.value.tooltip,
        x: { format: 'MMM d, yyyy' },
        y: {
            formatter: (value: number, opts?: { dataPointIndex: number }) => {
                const day = props.timeline[opts?.dataPointIndex ?? -1];
                if (!day) return people(value);
                return view.value === 'daily'
                    ? `${people(day.count)} · ${day.total.toLocaleString()} so far`
                    : `${day.total.toLocaleString()} so far · +${day.count.toLocaleString()} that day`;
            },
        },
    },
}));

const tableRows = computed(() =>
    props.timeline.map((day) => ({ date: longDate(day.date), count: day.count.toLocaleString(), total: day.total.toLocaleString() })),
);
</script>

<template>
    <DashboardCard title="Responses over time" subtitle="How many people answered each day, and the running total.">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <dl class="flex flex-wrap gap-x-6 gap-y-1 text-xs">
                <div v-if="busiestDay && busiestDay.count > 0">
                    <dt class="text-muted-foreground">Busiest day</dt>
                    <dd class="font-semibold text-foreground">{{ longDate(busiestDay.date) }} · {{ people(busiestDay.count) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Average per day</dt>
                    <dd class="font-semibold tabular-nums text-foreground">{{ average.toLocaleString(undefined, { maximumFractionDigits: 1 }) }}</dd>
                </div>
            </dl>
            <div class="pf-no-print flex rounded-lg border bg-background p-0.5 text-[11px] font-semibold" role="radiogroup" aria-label="Chart shows">
                <button
                    v-for="option in [
                        { value: 'daily', label: 'Per day' },
                        { value: 'total', label: 'Running total' },
                    ] as const"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="view === option.value"
                    class="rounded-md px-2.5 py-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="view === option.value ? 'bg-[#0b2a5b] text-white dark:bg-blue-600' : 'text-muted-foreground hover:text-foreground'"
                    @click="view = option.value"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <template #chart>
            <div role="img" :aria-label="`Responses per day, ${timeline.length} days. The table view lists every day.`">
                <VueApexCharts type="area" height="240" :options="options" :series="series" />
            </div>
        </template>
        <template #table>
            <div class="max-h-72 overflow-y-auto print:max-h-none print:overflow-visible">
                <DashboardTable
                    caption="Responses per day"
                    :columns="[
                        { key: 'date', label: 'Day' },
                        { key: 'count', label: 'Responses that day', numeric: true },
                        { key: 'total', label: 'Running total', numeric: true },
                    ]"
                    :rows="tableRows"
                />
            </div>
        </template>
    </DashboardCard>
</template>
