<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Search, UserSearch, ExternalLink } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{
    batches: any[];
}>();

const emit = defineEmits<{
    (e: 'locate', batch: any): void;
}>();

const query = ref('');

/**
 * Pinagsasama-sama ang lahat ng participants mula sa lahat ng batches,
 * kasama ang reference sa batch na kinabibilangan nila.
 */
const allParticipants = computed(() =>
    props.batches.flatMap((batch) =>
        (batch.participants ?? []).map((p: any) => ({
            participant: p,
            batch,
        }))
    )
);

const totalCount = computed(() => allParticipants.value.length);

/**
 * Client-side search by name o empcode.
 */
const results = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (q.length < 2) return [];

    return allParticipants.value.filter(({ participant }) => {
        const name = (participant.employee?.name ?? '').toLowerCase();
        const empcode = (participant.empcode ?? '').toLowerCase();
        return name.includes(q) || empcode.includes(q);
    });
});

const statusColor = (status: string) => {
    switch (status) {
        case 'Upcoming':  return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300';
        case 'Ongoing':   return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
        case 'Completed': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'Cancelled': return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
        default:          return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    }
};

const locate = (batch: any) => {
    query.value = '';
    emit('locate', batch);
};
</script>

<template>
    <div class="rounded-xl border p-3">

        <!-- Search input -->
        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <Search class="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                <Input
                    v-model="query"
                    class="text-xs h-8 pl-7"
                    :placeholder="`Find a participant across ${batches.length} batch(es)... (${totalCount} total)`"
                />
            </div>
        </div>

        <!-- Results -->
        <div v-if="query.trim().length >= 2" class="mt-2">

            <!-- Walang nahanap -->
            <div
                v-if="!results.length"
                class="rounded-lg border border-dashed px-3 py-4 text-center"
            >
                <UserSearch class="mx-auto h-5 w-5 text-slate-300" />
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    No participant matching "{{ query }}" in any batch.
                </p>
            </div>

            <!-- May results -->
            <div v-else class="rounded-lg border divide-y max-h-64 overflow-y-auto">
                <div
                    v-for="({ participant, batch }, i) in results"
                    :key="`${participant.id}-${i}`"
                    class="flex items-center justify-between gap-2 px-3 py-2"
                >
                    <div class="min-w-0">
                        <p class="text-xs font-bold leading-4 truncate">
                            {{ participant.employee?.name ?? participant.empcode }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">{{ participant.empcode }}</p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <div class="text-right">
                            <p class="text-[11px] font-bold leading-4">{{ batch.batch }}</p>
                            <Badge :class="statusColor(batch.status)" class="text-[9px] font-bold border-0">
                                {{ batch.status }}
                            </Badge>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 px-2 text-xs text-blue-600 hover:text-blue-700"
                            @click="locate(batch)"
                        >
                            <ExternalLink class="h-3 w-3" /> Open
                        </Button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>