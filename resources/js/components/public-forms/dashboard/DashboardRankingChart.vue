<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { DashboardOptionRow } from '@/lib/publicForms';
import { computed, onMounted, ref } from 'vue';
import { people, useChartTheme } from './charts';

/**
 * A ranking question: the top three choices with #1/#2/#3 medals, then one
 * stacked bar per choice showing how many people put it at each rank
 * (rank 1 strongest). Choices come highest score first.
 */
const props = defineProps<{
    options: DashboardOptionRow[];
    rank: number;
    label: string;
}>();

const { rankColors, motionLevel } = useChartTheme();

const colors = computed(() => rankColors(props.rank));
const topScore = computed(() => Math.max(1, ...props.options.map((option) => option.weighted_score)));
const mostRanked = computed(() => Math.max(1, ...props.options.map((option) => option.count)));
const podium = computed(() => props.options.filter((option) => option.weighted_score > 0).slice(0, 3));

const MEDALS = [
    'bg-gradient-to-br from-amber-300 to-amber-500 text-amber-950 ring-amber-600/30',
    'bg-gradient-to-br from-slate-200 to-slate-400 text-slate-900 ring-slate-500/30',
    'bg-gradient-to-br from-orange-300 to-orange-600 text-orange-950 ring-orange-700/30',
];

function rankName(position: number): string {
    return `Rank ${position + 1}`;
}

/** The ranks this choice was put at, in order (empty ranks draw nothing). */
function segmentsOf(option: DashboardOptionRow): { position: number; count: number }[] {
    return option.rank_counts.map((count, position) => ({ position, count })).filter((segment) => segment.count > 0);
}

const grown = ref(motionLevel.value === 'still');
onMounted(() => requestAnimationFrame(() => (grown.value = true)));
</script>

<template>
    <div class="flex flex-col gap-5">
        <div v-if="podium.length">
            <h4 class="mb-2 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Top priorities</h4>
            <ol class="flex flex-col gap-2">
                <li
                    v-for="(option, index) in podium"
                    :key="option.value"
                    class="flex items-center gap-3 rounded-xl border bg-background/60 px-3 py-2.5"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-extrabold shadow-sm ring-2"
                        :class="MEDALS[index]"
                        :aria-label="`Number ${index + 1}`"
                    >
                        #{{ index + 1 }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="break-words text-sm font-bold leading-snug text-foreground">{{ option.label }}</p>
                        <p class="text-[11px] text-muted-foreground">
                            Ranked #1 by {{ people(option.first_count) }} · picked by {{ people(option.count) }} · score
                            <span class="font-semibold tabular-nums text-foreground">{{ option.weighted_score.toLocaleString() }}</span>
                        </p>
                        <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                            <span
                                class="block h-full rounded-full bg-[#0b2a5b] dark:bg-blue-400"
                                :class="motionLevel === 'still' ? '' : 'transition-[width] duration-700 ease-out'"
                                :style="{ width: `${grown ? (option.weighted_score / topScore) * 100 : 0}%` }"
                            />
                        </span>
                    </div>
                </li>
            </ol>
        </div>

        <div>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">How each choice was ranked</h4>
                <ul class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground" :aria-label="`${label} legend`">
                    <li v-for="(color, position) in colors" :key="position" class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-[3px]" :style="{ backgroundColor: color }" aria-hidden="true" />{{ rankName(position) }}
                    </li>
                </ul>
            </div>

            <TooltipProvider :delay-duration="150">
                <ul class="flex flex-col gap-2" :aria-label="label">
                    <li v-for="option in options" :key="option.value">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <div
                                    tabindex="0"
                                    class="grid gap-1 rounded-lg px-1 py-0.5 outline-none transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring sm:grid-cols-[minmax(7rem,36%)_minmax(0,1fr)] sm:items-center sm:gap-3"
                                >
                                    <span class="break-words text-xs leading-snug text-foreground">{{ option.label }}</span>
                                    <span class="flex items-center gap-2">
                                        <span class="flex h-4 min-w-0 flex-1 gap-[2px]">
                                            <span
                                                v-for="segment in segmentsOf(option)"
                                                :key="segment.position"
                                                class="h-full last:rounded-r-[4px]"
                                                :class="motionLevel === 'still' ? '' : 'transition-[width] duration-700 ease-out'"
                                                :style="{
                                                    width: `${grown ? (segment.count / mostRanked) * 100 : 0}%`,
                                                    backgroundColor: colors[segment.position],
                                                }"
                                            />
                                        </span>
                                        <span class="w-[4.5rem] shrink-0 text-right text-xs tabular-nums">
                                            <span class="font-semibold text-foreground">{{ option.first_count.toLocaleString() }}</span>
                                            <span class="text-muted-foreground"> × #1</span>
                                        </span>
                                    </span>
                                </div>
                            </TooltipTrigger>
                            <TooltipContent side="top" class="max-w-xs">
                                <p class="text-sm font-bold">{{ option.label }}</p>
                                <p v-for="(count, position) in option.rank_counts" :key="position" class="text-xs tabular-nums">
                                    {{ rankName(position) }}: {{ people(count) }}
                                </p>
                                <p class="text-xs tabular-nums opacity-80">Score {{ option.weighted_score.toLocaleString() }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </li>
                </ul>
            </TooltipProvider>
            <p class="mt-2 text-[11px] text-muted-foreground">
                Score: rank 1 earns {{ rank }} {{ rank === 1 ? 'point' : 'points'
                }}<template v-if="rank > 1">, down to 1 point for rank {{ rank }}</template
                >.
            </p>
        </div>
    </div>
</template>
