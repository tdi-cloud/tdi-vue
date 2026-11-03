<script setup lang="ts">
import { registerInlineProgress, useNetworkStatus } from '@/composables/useNetworkStatus';
import { computed, onBeforeUnmount } from 'vue';

/**
 * Real upload progress bar. Uses the given percentage (e.g. form.progress?.percentage),
 * or falls back to the app-wide progress of the current Inertia/axios upload.
 */
const props = defineProps<{
    percentage?: number | null;
    label?: string;
}>();

const { uploadPercentage } = useNetworkStatus();

const value = computed(() => props.percentage ?? uploadPercentage.value);

// While this bar is visible, the banner's global upload indicator stays hidden.
onBeforeUnmount(registerInlineProgress());
</script>

<template>
    <div class="flex w-full items-center gap-2" role="status" aria-live="polite">
        <div
            class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="value ?? undefined"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="label ?? 'Upload progress'"
        >
            <!-- Before the first progress event arrives, show a thin pulsing bar instead of a fake percentage. -->
            <div v-if="value === null" class="h-full w-full animate-pulse bg-blue-600/40 dark:bg-blue-400/40" />
            <div v-else class="h-full bg-blue-600 transition-[width] duration-200 dark:bg-blue-400" :style="{ width: `${value}%` }" />
        </div>
        <span class="shrink-0 text-xs tabular-nums text-muted-foreground">
            {{ value === null ? 'Starting upload…' : value >= 100 ? 'Processing…' : `Uploading… ${value}%` }}
        </span>
    </div>
</template>
