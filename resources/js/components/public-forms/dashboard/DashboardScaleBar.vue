<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { DashboardOptionRow, DashboardScale } from '@/lib/publicForms';
import { computed, onMounted, ref } from 'vue';
import { formatPercent, inkOn, people, useChartTheme } from './charts';

/**
 * A rating scale at a glance: the average score as a large figure, and one
 * bar split from the lowest score (gold, left) to the highest (blue, right)
 * with a gray middle. Choices without a score (e.g. "Not applicable") are
 * listed under it, outside the scale.
 */
const props = defineProps<{
    options: DashboardOptionRow[];
    scale: DashboardScale;
    label: string;
}>();

const { scaleColors, motionLevel } = useChartTheme();

const scored = computed(() => [...props.options].filter((option) => option.score !== null).sort((a, b) => (a.score ?? 0) - (b.score ?? 0)));
const unscored = computed(() => props.options.filter((option) => option.score === null && option.count > 0));
const scoredTotal = computed(() => scored.value.reduce((sum, option) => sum + option.count, 0));
const colors = computed(() => scaleColors(scored.value.length));

const segments = computed(() =>
    scored.value.map((option, index) => {
        const share = scoredTotal.value ? (option.count / scoredTotal.value) * 100 : 0;
        return { ...option, share, color: colors.value[index], ink: inkOn(colors.value[index]) };
    }),
);

const summary = computed(
    () => `${props.label}: ${segments.value.map((segment) => `${segment.label} ${formatPercent(Math.round(segment.share * 10) / 10)}`).join(', ')}`,
);

const grown = ref(motionLevel.value === 'still');
onMounted(() => requestAnimationFrame(() => (grown.value = true)));
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end gap-x-4 gap-y-1">
            <p class="flex items-baseline gap-1.5">
                <span class="text-5xl font-extrabold tabular-nums leading-none tracking-tight text-[#0b2a5b] dark:text-blue-100">
                    {{ scale.average !== null ? scale.average.toFixed(1) : '—' }}
                </span>
                <span class="text-sm font-semibold text-muted-foreground">out of {{ scale.max }}</span>
            </p>
            <div class="pb-1 text-xs">
                <p class="text-muted-foreground">Average score{{ scale.min !== 1 ? ` (scale of ${scale.min} to ${scale.max})` : '' }}</p>
                <p v-if="scale.reading" class="font-semibold text-foreground">
                    Closest to
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-950 dark:bg-amber-400/20 dark:text-amber-100">{{
                        scale.reading
                    }}</span>
                </p>
            </div>
        </div>

        <TooltipProvider :delay-duration="150">
            <div class="flex h-7 w-full gap-[2px] overflow-hidden rounded" role="img" :aria-label="summary">
                <Tooltip v-for="segment in segments" :key="segment.value">
                    <TooltipTrigger as-child>
                        <span
                            v-show="segment.count > 0"
                            tabindex="0"
                            class="flex h-full items-center overflow-hidden rounded-[4px] px-1.5 text-[11px] font-bold outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :class="motionLevel === 'still' ? '' : 'transition-[width] duration-700 ease-out'"
                            :style="{ width: `${grown ? segment.share : 0}%`, backgroundColor: segment.color, color: segment.ink }"
                        >
                            <span v-if="segment.share >= 9" class="tabular-nums">{{ Math.round(segment.share) }}%</span>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-sm font-bold tabular-nums">{{ people(segment.count) }} · {{ formatPercent(segment.percent) }}</p>
                        <p class="text-xs opacity-80">{{ segment.score }} — {{ segment.label }}</p>
                    </TooltipContent>
                </Tooltip>
            </div>
        </TooltipProvider>

        <ul class="grid gap-x-4 gap-y-1.5 text-xs sm:grid-cols-2" :aria-label="`${label} legend`">
            <li v-for="segment in [...segments].reverse()" :key="segment.value" class="flex items-start gap-2">
                <span class="mt-0.5 h-3 w-3 shrink-0 rounded-[3px]" :style="{ backgroundColor: segment.color }" aria-hidden="true" />
                <span class="min-w-0 flex-1 break-words leading-snug"
                    ><span class="font-semibold tabular-nums">{{ segment.score }}</span> — {{ segment.label }}</span
                >
                <span class="shrink-0 tabular-nums">
                    <span class="font-semibold">{{ formatPercent(segment.percent) }}</span>
                    <span class="text-muted-foreground"> · {{ segment.count.toLocaleString() }}</span>
                </span>
            </li>
        </ul>

        <p v-if="unscored.length" class="text-xs text-muted-foreground">
            Not on the scale:
            <template v-for="(option, index) in unscored" :key="option.value">
                <span class="font-semibold text-foreground">{{ option.label }}</span> ({{ people(option.count) }}){{
                    index < unscored.length - 1 ? ', ' : '.'
                }}
            </template>
        </p>
    </div>
</template>
