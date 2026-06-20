<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Users, LoaderCircle, CheckCircle2, XCircle, RotateCcw } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';

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
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        empcodesText.value = '';
        bulkResult.value = null;
    }
});

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
        }
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
                    <span class="flex gap-2 items-center text-xl font-extrabold">
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
                    class="text-sm border-blue-400 focus-visible:ring-blue-400"
                    placeholder="Paste Employee IDs Here... i.e. 2026-1234"
                />
                <p class="text-xs text-muted-foreground">
                    Separate codes by newline · comma · semicolon · or space
                </p>

                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" size="sm" @click="close">Cancel</Button>
                    <Button
                        class="bg-blue-600 hover:bg-blue-700 dark:text-white"
                        size="sm"
                        :disabled="!empcodesText.trim() || processing"
                        @click="submit"
                    >
                        <LoaderCircle v-if="processing" class="h-3.5 w-3.5 animate-spin mr-1" />
                        Submit
                    </Button>
                </div>
            </div>

            <!-- ============ RESULTS ============ -->
            <div v-else class="flex flex-col gap-3">

                <div class="flex items-center gap-2">
                    <Badge class="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border-0 font-bold">
                        <CheckCircle2 class="h-3.5 w-3.5 mr-1" /> {{ bulkResult?.success ?? 0 }} added
                    </Badge>
                    <Badge v-if="(bulkResult?.failed ?? 0) > 0" class="bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 border-0 font-bold">
                        <XCircle class="h-3.5 w-3.5 mr-1" /> {{ bulkResult?.failed ?? 0 }} failed
                    </Badge>
                </div>

                <div class="max-h-72 overflow-y-auto rounded-xl border divide-y">

                    <div
                        v-for="item in successItems"
                        :key="`s-${item.empcode}`"
                        class="flex items-center justify-between px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="text-xs font-bold leading-4 truncate">{{ item.name ?? item.empcode }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ item.empcode }}</p>
                        </div>
                        <Badge class="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border-0 text-[10px] font-bold">
                            <CheckCircle2 class="h-3 w-3 mr-1" /> Added
                        </Badge>
                    </div>

                    <div
                        v-for="item in failedItems"
                        :key="`f-${item.empcode}`"
                        class="flex items-center justify-between px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="text-xs font-bold leading-4 truncate">{{ item.name ?? item.empcode }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ item.empcode }}</p>
                            <p class="text-[11px] text-red-500">{{ item.reason }}</p>
                        </div>
                        <Badge class="bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 border-0 text-[10px] font-bold shrink-0 ml-2">
                            <XCircle class="h-3 w-3 mr-1" /> Failed
                        </Badge>
                    </div>

                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <Button variant="outline" size="sm" @click="reset">
                        <RotateCcw class="h-3.5 w-3.5 mr-1" /> Add More
                    </Button>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" @click="close">
                        Done
                    </Button>
                </div>
            </div>

        </DialogContent>
    </Dialog>
</template>