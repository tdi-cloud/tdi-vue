<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/vue3';
import { CheckCircle2, LoaderCircle, RotateCcw, Users, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    batch: any | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const empcodesText = ref('');
const processing = ref(false);

interface BulkResultItem {
    empcode: string;
    name: string | null;
    status: 'success' | 'failed';
    reason: string | null;
}

interface BulkResult {
    results: BulkResultItem[];
    success: number;
    failed: number;
}

const bulkResult = ref<BulkResult | null>(null);

const showResults = computed(() => !!bulkResult.value);

const successItems = computed(() => bulkResult.value?.results.filter((r) => r.status === 'success') ?? []);
const failedItems = computed(() => bulkResult.value?.results.filter((r) => r.status === 'failed') ?? []);

/* Reset tuwing magbubukas ng modal */
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            empcodesText.value = '';
            bulkResult.value = null;
        }
    },
);

const submit = () => {
    if (!empcodesText.value.trim() || !props.batch) return;

    processing.value = true;
    router.post(
        route('participants.bulk-store'),
        {
            batch_id: props.batch.id,
            empcodes: empcodesText.value,
        },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as any)?.flash;
                if (flash?.bulkResult) {
                    bulkResult.value = flash.bulkResult;
                }
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const reset = () => {
    empcodesText.value = '';
    bulkResult.value = null;
};

const close = () => {
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-lg !rounded-2xl">
            <DialogHeader>
                <DialogTitle>
                    <span class="flex items-center gap-2 text-xl font-extrabold">
                        <Users class="h-6 w-6 text-blue-600" /> Bulk Add Participants
                    </span>
                </DialogTitle>
                <DialogDescription class="text-sm">
                    — Paste employee codes below to enroll them to
                    <span class="font-semibold">{{ batch?.batch }}</span>
                </DialogDescription>
            </DialogHeader>

            <!-- ============ INPUT FORM ============ -->
            <div v-if="!showResults" class="flex flex-col gap-2">
                <label class="text-sm font-bold">Employee Codes</label>
                <Textarea
                    v-model="empcodesText"
                    rows="6"
                    class="border-blue-400 text-sm focus-visible:ring-blue-400"
                    placeholder="Paste Employee IDs Here... i.e. 2026-1234"
                />
                <p class="text-xs text-muted-foreground">Separate codes by newline · comma · semicolon · or space</p>

                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" size="sm" @click="close">Cancel</Button>
                    <Button
                        class="bg-blue-600 hover:bg-blue-700 dark:text-white"
                        size="sm"
                        :disabled="!empcodesText.trim() || processing"
                        @click="submit"
                    >
                        <LoaderCircle v-if="processing" class="mr-1 h-3.5 w-3.5 animate-spin" />
                        Submit
                    </Button>
                </div>
            </div>

            <!-- ============ RESULTS ============ -->
            <div v-else class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <Badge class="border-0 bg-emerald-100 font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                        <CheckCircle2 class="mr-1 h-3.5 w-3.5" /> {{ bulkResult?.success ?? 0 }} added
                    </Badge>
                    <Badge
                        v-if="(bulkResult?.failed ?? 0) > 0"
                        class="border-0 bg-red-100 font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                    >
                        <XCircle class="mr-1 h-3.5 w-3.5" /> {{ bulkResult?.failed ?? 0 }} failed
                    </Badge>
                </div>

                <div class="max-h-72 divide-y overflow-y-auto rounded-xl border">
                    <div v-for="item in successItems" :key="`s-${item.empcode}`" class="flex items-center justify-between px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold leading-4">{{ item.name ?? item.empcode }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ item.empcode }}</p>
                        </div>
                        <Badge class="border-0 bg-emerald-100 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <CheckCircle2 class="mr-1 h-3 w-3" /> Added
                        </Badge>
                    </div>

                    <div v-for="item in failedItems" :key="`f-${item.empcode}`" class="flex items-center justify-between px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold leading-4">{{ item.name ?? item.empcode }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ item.empcode }}</p>
                            <p class="text-[11px] text-red-500">{{ item.reason }}</p>
                        </div>
                        <Badge class="ml-2 shrink-0 border-0 bg-red-100 text-[10px] font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300">
                            <XCircle class="mr-1 h-3 w-3" /> Failed
                        </Badge>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <Button variant="outline" size="sm" @click="reset"> <RotateCcw class="mr-1 h-3.5 w-3.5" /> Add More </Button>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" @click="close"> Done </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
