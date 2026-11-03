<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(
    defineProps<{
        rows?: number;
        columns?: number;
        label?: string;
    }>(),
    { rows: 6, columns: 4, label: 'Loading…' },
);
</script>

<template>
    <div role="status" aria-live="polite" class="w-full">
        <span class="sr-only">{{ label }}</span>
        <div aria-hidden="true" class="divide-y">
            <div v-for="row in rows" :key="row" class="flex items-center gap-4 px-3 py-3">
                <!-- First column wider (usually a name), the rest shorter. -->
                <Skeleton v-for="col in columns" :key="col" class="h-4" :class="col === 1 ? 'flex-[2]' : 'flex-1'" />
            </div>
        </div>
    </div>
</template>
