<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(
    defineProps<{
        count?: number;
        // Adds a chart-sized block under the tiles (compliance cards).
        withChart?: boolean;
        label?: string;
    }>(),
    { count: 4, withChart: false, label: 'Loading…' },
);
</script>

<template>
    <div role="status" aria-live="polite" class="flex flex-col gap-3">
        <span class="sr-only">{{ label }}</span>
        <div class="grid gap-3" :class="count >= 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-3'" aria-hidden="true">
            <div v-for="n in count" :key="n" class="flex flex-col gap-2 rounded-xl border p-3">
                <Skeleton class="h-3 w-2/3" />
                <Skeleton class="h-6 w-1/2" />
            </div>
        </div>
        <Skeleton v-if="withChart" class="h-48 w-full rounded-xl" aria-hidden="true" />
    </div>
</template>
