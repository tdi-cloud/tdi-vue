<script setup lang="ts">
import { useLiteMode } from '@/composables/useLiteMode';
import { useNetworkStatus } from '@/composables/useNetworkStatus';
import { Gauge, Wifi, WifiOff, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const { isOnline, isSlow, justReconnected, uploadPercentage, hasInlineProgress } = useNetworkStatus();
const { isLiteMode, setLiteMode } = useLiteMode();

// The slow notice can be closed; it comes back the next time the connection turns slow.
const slowNoticeDismissed = ref(false);
watch(isSlow, (slow) => {
    if (!slow) slowNoticeDismissed.value = false;
});
</script>

<template>
    <!-- Always rendered so screen readers pick up changes in this live region. -->
    <div role="status" aria-live="polite" class="pointer-events-none fixed inset-x-0 top-2 z-[100] flex justify-center px-4">
        <div
            v-if="!isOnline"
            class="pointer-events-auto flex max-w-xl items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-900 shadow-lg dark:border-amber-500/40 dark:bg-amber-950 dark:text-amber-100"
        >
            <WifiOff class="h-4 w-4 shrink-0" aria-hidden="true" />
            <span>You're offline. Changes won't be saved until you reconnect.</span>
        </div>

        <div
            v-else-if="justReconnected"
            class="pointer-events-auto flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-900 shadow-lg dark:border-emerald-500/40 dark:bg-emerald-950 dark:text-emerald-100"
        >
            <Wifi class="h-4 w-4 shrink-0" aria-hidden="true" />
            <span>Back online.</span>
        </div>

        <div
            v-else-if="isSlow && !slowNoticeDismissed"
            class="pointer-events-auto flex w-full max-w-xl flex-col gap-2 rounded-xl border bg-background px-4 py-2.5 text-sm text-foreground shadow-lg"
        >
            <div class="flex items-start gap-2">
                <Gauge class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <p class="font-medium">Slow connection. This is taking longer than usual.</p>
                    <button
                        v-if="!isLiteMode"
                        type="button"
                        class="mt-0.5 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400"
                        @click="setLiteMode(true)"
                    >
                        Switch to lite mode (skip charts, maps and images)
                    </button>
                    <p v-else class="mt-0.5 text-xs text-muted-foreground">Lite mode is on.</p>
                </div>
                <button
                    type="button"
                    class="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                    aria-label="Dismiss slow connection notice"
                    @click="slowNoticeDismissed = true"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Real upload progress when we have it; no fake spinner. -->
            <div v-if="uploadPercentage !== null && !hasInlineProgress" class="flex items-center gap-2">
                <div
                    class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    :aria-valuenow="uploadPercentage"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Upload progress"
                >
                    <div class="h-full bg-blue-600 transition-[width] dark:bg-blue-400" :style="{ width: `${uploadPercentage}%` }" />
                </div>
                <span class="w-24 text-right text-xs tabular-nums text-muted-foreground">Uploading… {{ uploadPercentage }}%</span>
            </div>
        </div>

        <!-- Every file upload shows real progress; pages with their own inline bar hide this one. -->
        <div
            v-else-if="uploadPercentage !== null && !hasInlineProgress"
            class="pointer-events-auto flex w-full max-w-xs items-center gap-2 rounded-xl border bg-background px-4 py-2.5 text-sm shadow-lg"
        >
            <div
                class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                :aria-valuenow="uploadPercentage"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-label="Upload progress"
            >
                <div class="h-full bg-blue-600 transition-[width] dark:bg-blue-400" :style="{ width: `${uploadPercentage}%` }" />
            </div>
            <span class="shrink-0 text-xs tabular-nums text-muted-foreground">
                {{ uploadPercentage >= 100 ? 'Processing…' : `Uploading… ${uploadPercentage}%` }}
            </span>
        </div>
    </div>
</template>
