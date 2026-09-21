<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { ExternalLink } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    open: boolean;
    fileUrl: string | null;
    title?: string;
    description?: string;
    /** Extra classes para sa DialogContent, kung sakaling kailangan pang
     *  itaas ang default na z-index nito. */
    contentClass?: HTMLAttributes['class'];
}>();

defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <!-- z-[80]: dapat laging nasa ibabaw ito, kahit binuksan mula sa loob
             ng isa pang open na modal/dialog (hal. z-[60] custom modals). -->
        <DialogContent
            :class="cn('z-[80] flex h-[85vh] w-full max-w-4xl flex-col gap-0 overflow-hidden !rounded-2xl p-0', props.contentClass)"
        >
            <DialogHeader class="shrink-0 border-b px-4 py-3 pr-12 text-left">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <DialogTitle class="truncate text-sm">{{ title ?? 'Document Preview' }}</DialogTitle>
                        <DialogDescription v-if="description" class="truncate text-xs text-muted-foreground">
                            {{ description }}
                        </DialogDescription>
                    </div>
                    <a
                        v-if="fileUrl"
                        :href="fileUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-blue-600 hover:underline"
                    >
                        <ExternalLink class="h-3.5 w-3.5" /> Open in new tab
                    </a>
                </div>
            </DialogHeader>

            <iframe v-if="fileUrl" :src="fileUrl" class="h-full w-full flex-1 border-0" allow="autoplay"></iframe>
        </DialogContent>
    </Dialog>
</template>
