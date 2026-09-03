<script setup lang="ts">
import { PieChart } from 'lucide-vue-next';
import { computed } from 'vue';

interface CoverageRow {
    type: string;
    total: number;
}

const props = defineProps<{
    rows: CoverageRow[];
}>();

const maxTotal = computed(() => Math.max(1, ...props.rows.map((r) => r.total)));
</script>

<template>
    <div class="rounded-2xl border bg-white/90 dark:bg-background/90 backdrop-blur shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b flex items-center gap-2">
            <PieChart class="h-4 w-4 text-violet-600" />
            <h3 class="text-sm font-bold">Training Coverage by Category</h3>
        </div>

        <div v-if="props.rows.length === 0" class="py-10 text-center text-xs text-muted-foreground px-4">
            No training coverage data available yet.
        </div>

        <div v-else class="p-4 flex flex-col gap-3">
            <div v-for="row in props.rows" :key="row.type">
                <div class="flex items-center justify-between text-xs mb-1">
                    <span class="font-semibold truncate">{{ row.type }}</span>
                    <span class="font-bold text-muted-foreground">{{ row.total }}</span>
                </div>
                <div class="h-2.5 rounded-full bg-muted overflow-hidden">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-violet-500 to-indigo-500 transition-all"
                        :style="{ width: `${(row.total / maxTotal) * 100}%` }"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
