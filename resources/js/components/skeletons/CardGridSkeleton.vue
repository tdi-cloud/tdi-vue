<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(
    defineProps<{
        count?: number;
        label?: string;
    }>(),
    { count: 6, label: 'Loading…' },
);
</script>

<template>
    <div role="status" aria-live="polite">
        <span class="sr-only">{{ label }}</span>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-hidden="true">
            <div v-for="n in count" :key="n" class="flex flex-col gap-3 rounded-xl border p-4">
                <Skeleton class="h-28 w-full rounded-lg" />
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-3 w-1/2" />
            </div>
        </div>
    </div>
</template>
