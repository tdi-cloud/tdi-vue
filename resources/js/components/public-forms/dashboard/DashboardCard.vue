<script setup lang="ts">
import { useLiteMode } from '@/composables/useLiteMode';
import { BarChart3, Table2 } from 'lucide-vue-next';
import { ref, useId, useSlots, watch, type Slots } from 'vue';

/**
 * A dashboard card: title, optional chart with a "View as table" switch
 * (the accessible fallback, and what prints), and free content around it.
 * In lite mode every card starts on its table, so no chart is drawn until asked for.
 */
withDefaults(
    defineProps<{
        title: string;
        eyebrow?: string;
        subtitle?: string;
        accent?: 'navy' | 'gold';
        /** The chart is plain HTML that prints well, so it prints instead of its table. */
        printChart?: boolean;
    }>(),
    { accent: 'navy', printChart: false },
);

const slots: Slots = useSlots();
const { isLiteMode } = useLiteMode();
const showTable = ref(isLiteMode.value);
const tableId = useId();

watch(isLiteMode, (lite) => {
    showTable.value = lite;
});
</script>

<template>
    <section
        class="tdi-card pf-card relative flex min-w-0 flex-col gap-3 overflow-hidden rounded-2xl border p-4 sm:p-5"
        :class="accent === 'gold' ? 'tdi-accent-gold tdi-accent-to-amber' : 'tdi-accent-navy tdi-accent-to-royal'"
    >
        <header class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p v-if="eyebrow" class="mb-0.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{{ eyebrow }}</p>
                <h3 class="break-words text-sm font-bold leading-snug text-foreground">{{ title }}</h3>
                <p v-if="subtitle" class="mt-0.5 text-xs text-muted-foreground">{{ subtitle }}</p>
            </div>
            <button
                v-if="slots.table && slots.chart"
                type="button"
                class="pf-no-print inline-flex h-7 shrink-0 items-center gap-1 rounded-lg border bg-background px-2 text-[11px] font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :aria-pressed="showTable"
                :aria-controls="tableId"
                @click="showTable = !showTable"
            >
                <component :is="showTable ? BarChart3 : Table2" class="h-3.5 w-3.5" aria-hidden="true" />
                {{ showTable ? 'View as chart' : 'View as table' }}
            </button>
        </header>

        <slot />

        <div v-if="slots.chart && !showTable" class="pf-chart min-w-0" :class="{ 'print:hidden': !printChart }">
            <slot name="chart" />
        </div>
        <!-- An interactive chart prints as its table; a plain HTML chart prints as it is. -->
        <div
            v-if="slots.table"
            :id="tableId"
            class="pf-table min-w-0"
            :class="slots.chart && !showTable ? (printChart ? 'hidden' : 'hidden print:block') : ''"
        >
            <slot name="table" />
        </div>

        <slot name="footer" />
    </section>
</template>
