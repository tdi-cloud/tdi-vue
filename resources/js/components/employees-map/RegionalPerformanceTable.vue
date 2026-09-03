<script setup lang="ts">
import { BarChart3 } from 'lucide-vue-next';

interface RegionRow {
    region: string;
    total: number;
    participation_pct: number;
    completion_pct: number;
    avg_hours: number;
    pending: number;
    overdue: number;
    rank: number;
    status: string;
}

const props = defineProps<{
    rows: RegionRow[];
}>();

const emit = defineEmits<{
    viewRegion: [region: string];
}>();

const statusStyle = (status: string) => {
    if (status === 'Excellent') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400';
    if (status === 'Good') return 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400';
    if (status === 'Needs Monitoring') return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400';
    return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400';
};
</script>

<template>
    <div class="rounded-2xl border bg-white/90 dark:bg-background/90 backdrop-blur shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b flex items-center gap-2">
            <BarChart3 class="h-4 w-4 text-blue-600" />
            <h3 class="text-sm font-bold">Regional Performance</h3>
        </div>

        <div v-if="props.rows.length === 0" class="py-10 text-center text-xs text-muted-foreground">
            No regional data available yet.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b text-muted-foreground text-[10px] uppercase tracking-wide">
                        <th class="text-left font-semibold px-4 py-2">Rank</th>
                        <th class="text-left font-semibold px-2 py-2">Region</th>
                        <th class="text-right font-semibold px-2 py-2">Employees</th>
                        <th class="text-right font-semibold px-2 py-2">Participation</th>
                        <th class="text-right font-semibold px-2 py-2">Completion</th>
                        <th class="text-right font-semibold px-2 py-2">Avg Hours</th>
                        <th class="text-right font-semibold px-2 py-2">Pending</th>
                        <th class="text-right font-semibold px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in props.rows"
                        :key="row.region"
                        class="border-b last:border-0 hover:bg-muted/40 cursor-pointer transition-colors"
                        @click="emit('viewRegion', row.region)"
                    >
                        <td class="px-4 py-2 font-bold text-muted-foreground">#{{ row.rank }}</td>
                        <td class="px-2 py-2 font-bold">{{ row.region }}</td>
                        <td class="px-2 py-2 text-right">{{ row.total }}</td>
                        <td class="px-2 py-2 text-right">{{ row.participation_pct }}%</td>
                        <td class="px-2 py-2 text-right font-semibold">{{ row.completion_pct }}%</td>
                        <td class="px-2 py-2 text-right">{{ row.avg_hours }}</td>
                        <td class="px-2 py-2 text-right">
                            <span :class="row.pending > 0 ? 'text-amber-600 font-semibold' : ''">{{ row.pending }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold" :class="statusStyle(row.status)">
                                {{ row.status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
