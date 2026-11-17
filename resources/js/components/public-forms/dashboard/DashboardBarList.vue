<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { computed, onMounted, ref } from 'vue';
import { formatPercent, people, useChartTheme } from './charts';

/**
 * Horizontal bars as a list: the name (wrapping, never cut), one bar per row
 * in the main color, and the figures in an aligned column. "Other",
 * "Others" and "Not stated" rows are gray.
 */
const props = withDefaults(
    defineProps<{
        rows: { label: string; count: number; percent: number; muted?: boolean }[];
        /** What the bar length shows: the count (longest bar = most) or the percentage (of 100%). */
        measure?: 'count' | 'percent';
        /** Shown in the tooltip after the percentage, e.g. "of all responses". */
        percentOf?: string;
        label: string;
    }>(),
    { measure: 'count', percentOf: 'of those who answered' },
);

const { primary, neutral, motionLevel } = useChartTheme();

const longest = computed(() => Math.max(1, ...props.rows.map((row) => row.count)));

function widthOf(row: { count: number; percent: number }): number {
    return props.measure === 'percent' ? Math.min(100, row.percent) : (row.count / longest.value) * 100;
}

function isMuted(row: { label: string; muted?: boolean }): boolean {
    return row.muted ?? ['Other', 'Others', 'Not stated'].includes(row.label);
}

// Bars grow in once, unless motion is reduced.
const grown = ref(motionLevel.value === 'still');
onMounted(() => requestAnimationFrame(() => (grown.value = true)));
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <ul class="flex flex-col gap-2" :aria-label="label">
            <li v-for="row in rows" :key="row.label">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <div
                            tabindex="0"
                            class="group grid gap-1 rounded-lg px-1 py-0.5 outline-none transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring sm:grid-cols-[minmax(7rem,36%)_minmax(0,1fr)] sm:items-center sm:gap-3"
                        >
                            <span class="break-words text-xs leading-snug text-foreground">{{ row.label }}</span>
                            <span class="flex items-center gap-2">
                                <span class="relative h-4 min-w-0 flex-1" :class="measure === 'percent' ? 'rounded-r bg-muted' : ''">
                                    <span
                                        class="absolute inset-y-0 left-0 rounded-r-[4px] group-hover:brightness-110"
                                        :class="motionLevel === 'still' ? '' : 'transition-[width] duration-700 ease-out'"
                                        :style="{
                                            width: `${grown ? widthOf(row) : 0}%`,
                                            minWidth: row.count > 0 ? '2px' : '0',
                                            backgroundColor: isMuted(row) ? neutral : primary,
                                        }"
                                    />
                                </span>
                                <span class="w-[5.5rem] shrink-0 text-right text-xs tabular-nums">
                                    <span class="font-semibold text-foreground">{{ formatPercent(row.percent) }}</span>
                                    <span class="text-muted-foreground"> · {{ row.count.toLocaleString() }}</span>
                                </span>
                            </span>
                        </div>
                    </TooltipTrigger>
                    <TooltipContent side="top" class="max-w-xs">
                        <p class="text-sm font-bold tabular-nums">{{ people(row.count) }} · {{ formatPercent(row.percent) }}</p>
                        <p class="text-xs opacity-80">{{ row.label }} ({{ formatPercent(row.percent) }} {{ percentOf }})</p>
                    </TooltipContent>
                </Tooltip>
            </li>
        </ul>
    </TooltipProvider>
</template>
