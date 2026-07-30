<script setup lang="ts">
import { useConfirmDialogState } from '@/composables/useConfirm';
import { AlertTriangle } from 'lucide-vue-next';

const { state, respond } = useConfirmDialogState();
</script>

<template>
    <Teleport to="body">
        <div
            v-if="state.open"
            class="pointer-events-auto fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4"
            @click.self="respond(false)"
        >
            <div class="bg-background text-foreground rounded-2xl shadow-2xl w-full max-w-sm p-6">
                <div class="flex items-start gap-3">
                    <div
                        class="flex items-center justify-center h-10 w-10 rounded-full shrink-0"
                        :class="state.variant === 'danger'
                            ? 'bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-400'
                            : 'bg-blue-100 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400'"
                    >
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 pt-1">
                        <h3 class="text-sm font-bold">{{ state.title }}</h3>
                        <p class="text-sm text-muted-foreground mt-1 whitespace-pre-line">{{ state.message }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 mt-5">
                    <button
                        type="button"
                        class="rounded-lg border px-3.5 py-2 text-sm font-semibold hover:bg-muted transition-colors"
                        @click="respond(false)"
                    >
                        {{ state.cancelText }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3.5 py-2 text-sm font-bold text-white transition-colors"
                        :class="state.variant === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                        @click="respond(true)"
                    >
                        {{ state.confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
