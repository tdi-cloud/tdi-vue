<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { AlertTriangle, RotateCw } from 'lucide-vue-next';

/**
 * Inline "this section failed to load" message with a Retry button. The
 * app-wide toast (lib/http.ts) already explains why; this keeps the section
 * itself from looking empty or broken.
 */
withDefaults(
    defineProps<{
        message?: string;
        retrying?: boolean;
    }>(),
    { message: "This section couldn't be loaded.", retrying: false },
);

const emit = defineEmits<{ (e: 'retry'): void }>();
</script>

<template>
    <div
        role="alert"
        class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-red-200 bg-red-50/50 px-4 py-8 text-center dark:border-red-500/30 dark:bg-red-950/20"
    >
        <AlertTriangle class="h-6 w-6 text-red-500" aria-hidden="true" />
        <p class="text-sm font-medium text-foreground">{{ message }}</p>
        <Button variant="outline" size="sm" :disabled="retrying" @click="emit('retry')">
            <RotateCw class="h-4 w-4" :class="{ 'animate-spin': retrying }" /> {{ retrying ? 'Retrying…' : 'Retry' }}
        </Button>
    </div>
</template>
