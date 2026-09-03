<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';

interface AttentionRow {
    region: string;
    total: number;
    completion_pct: number;
    pending: number;
    overdue: number;
}

const props = defineProps<{
    rows: AttentionRow[];
}>();

const emit = defineEmits<{
    viewRegion: [region: string];
}>();
</script>

<template>
    <div class="rounded-2xl border bg-white/90 dark:bg-background/90 backdrop-blur shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b flex items-center gap-2">
            <AlertTriangle class="h-4 w-4 text-rose-600" />
            <h3 class="text-sm font-bold">L&amp;D Attention Required</h3>
        </div>

        <div v-if="props.rows.length === 0" class="py-10 text-center text-xs text-muted-foreground px-4">
            No regions currently need attention — no overdue requirements and no low completion rates.
        </div>

        <div v-else class="p-3 flex flex-col gap-2">
            <button
                v-for="row in props.rows"
                :key="row.region"
                type="button"
                class="w-full text-left rounded-xl border border-rose-200 dark:border-rose-900 bg-rose-50/60 dark:bg-rose-950/20 px-3 py-2.5 hover:bg-rose-100/70 dark:hover:bg-rose-950/40 transition-colors"
                @click="emit('viewRegion', row.region)"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-bold text-sm">{{ row.region }}</span>
                    <span class="text-[10px] font-semibold text-muted-foreground">{{ row.total }} employees</span>
                </div>
                <div class="flex items-center gap-3 mt-1 text-[11px]">
                    <span class="text-rose-700 dark:text-rose-400 font-semibold">{{ row.completion_pct }}% completion</span>
                    <span v-if="row.overdue > 0" class="text-red-700 dark:text-red-400 font-semibold">{{ row.overdue }} overdue</span>
                    <span v-else-if="row.pending > 0" class="text-amber-700 dark:text-amber-400 font-semibold">{{ row.pending }} pending</span>
                </div>
            </button>
        </div>
    </div>
</template>
