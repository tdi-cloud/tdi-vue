<script setup lang="ts">
import type { ApexOptions } from 'apexcharts';
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { formatPercent, people, useChartTheme } from './charts';

/**
 * A donut for a split into a few parts, with the total in the middle and a
 * legend that names every part with its count and share.
 */
const props = withDefaults(
    defineProps<{
        rows: { label: string; count: number; percent: number; is_other?: boolean }[];
        /** Under the number in the middle, e.g. "People who answered". */
        centerLabel?: string;
        label: string;
    }>(),
    { centerLabel: 'People who answered' },
);

const { baseOptions, seriesColors, theme } = useChartTheme();

const colors = computed(() => seriesColors(props.rows));
const total = computed(() => props.rows.reduce((sum, row) => sum + row.count, 0));
const series = computed(() => props.rows.map((row) => row.count));
const summary = computed(() => `${props.label}: ${props.rows.map((row) => `${row.label} ${formatPercent(row.percent)}`).join(', ')}`);

const options = computed<ApexOptions>(() => ({
    ...baseOptions.value,
    chart: { ...baseOptions.value.chart, type: 'donut' },
    labels: props.rows.map((row) => row.label),
    colors: colors.value,
    // The 2px gap between slices is the card surface.
    stroke: { width: 2, colors: [theme.value.surface] },
    dataLabels: { enabled: false },
    states: { hover: { filter: { type: 'darken' } }, active: { filter: { type: 'none' } } },
    plotOptions: {
        pie: {
            expandOnClick: false,
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    name: { show: true, fontSize: '11px', color: theme.value.muted },
                    value: {
                        show: true,
                        fontSize: '22px',
                        fontWeight: 700,
                        color: theme.value.strong,
                        formatter: (value: string) => Number(value).toLocaleString(),
                    },
                    total: {
                        show: true,
                        showAlways: true,
                        label: props.centerLabel,
                        fontSize: '11px',
                        color: theme.value.muted,
                        formatter: () => total.value.toLocaleString(),
                    },
                },
            },
        },
    },
    tooltip: {
        ...baseOptions.value.tooltip,
        fillSeriesColor: false,
        y: {
            formatter: (value: number, opts?: { seriesIndex: number }) =>
                `${people(value)} · ${formatPercent(props.rows[opts?.seriesIndex ?? -1]?.percent ?? 0)}`,
        },
    },
}));
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-center">
        <div class="w-44 shrink-0" role="img" :aria-label="summary">
            <VueApexCharts v-if="total > 0" type="donut" height="176" :options="options" :series="series" />
            <div
                v-else
                class="mx-auto my-3 flex h-36 w-36 items-center justify-center rounded-full border-[14px] border-muted text-center text-[11px] text-muted-foreground"
            >
                No answers yet
            </div>
        </div>
        <ul class="flex w-full min-w-0 flex-col gap-1.5" :aria-label="`${label} legend`">
            <li v-for="(row, index) in rows" :key="row.label" class="flex items-start gap-2 text-xs">
                <span class="mt-0.5 h-3 w-3 shrink-0 rounded-[3px]" :style="{ backgroundColor: colors[index] }" aria-hidden="true" />
                <span class="min-w-0 flex-1 break-words leading-snug text-foreground">{{ row.label }}</span>
                <span class="shrink-0 text-right tabular-nums">
                    <span class="font-semibold text-foreground">{{ formatPercent(row.percent) }}</span>
                    <span class="text-muted-foreground"> · {{ row.count.toLocaleString() }}</span>
                </span>
            </li>
        </ul>
    </div>
</template>
