<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/vue3';
import { AlarmClockMinus, CalendarClock, CheckCircle2, LoaderCircle, Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Lets admins correct a submission's date (e.g. handed in on time but encoded
 * late), which also clears a wrong "Late" indication.
 */
const props = defineProps<{
    submission: { id: number; submitted_at: string | null; requirement?: { due_date?: string | null } | null } | null;
    participantName?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const value = ref('');
const error = ref('');
const processing = ref(false);

// <input type="datetime-local"> works in the browser's local time without an offset.
const toLocalInput = (iso: string | null): string => {
    const d = iso ? new Date(iso) : new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const maxValue = computed(() => toLocalInput(null));

watch(open, (isOpen) => {
    if (isOpen) {
        value.value = toLocalInput(props.submission?.submitted_at ?? null);
        error.value = '';
    }
});

// Same rule as the "Late" badge: late when submitted after the end of the due date.
const willBeLate = computed(() => {
    const due = props.submission?.requirement?.due_date;
    if (!value.value || !due) return null;
    const dueEnd = new Date(due + 'T00:00:00');
    dueEnd.setHours(23, 59, 59, 999);
    return new Date(value.value) > dueEnd;
});

const save = () => {
    if (!props.submission || !value.value) return;
    processing.value = true;
    error.value = '';
    router.patch(
        route('submissions.submitted-at.update', props.submission.id),
        // Send with the timezone offset so the server stores the exact moment picked.
        { submitted_at: new Date(value.value).toISOString() },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onError: (errors) => {
                error.value = errors.submitted_at ?? 'Failed to update the submission date.';
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="!max-w-sm !rounded-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2"><CalendarClock class="h-5 w-5 text-blue-600" /> Edit submission date</DialogTitle>
                <DialogDescription class="text-xs">
                    <template v-if="participantName">{{ participantName }} · </template>Correct the date this requirement was actually submitted.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="save">
                <div class="grid gap-1.5">
                    <Label for="submitted-at" class="text-xs font-semibold">Date &amp; time submitted</Label>
                    <Input id="submitted-at" v-model="value" type="datetime-local" :max="maxValue" class="h-9 text-sm" required />
                    <InputError :message="error" />
                </div>

                <p v-if="willBeLate === true" class="flex items-center gap-1.5 text-xs font-semibold text-red-600 dark:text-red-400">
                    <AlarmClockMinus class="h-3.5 w-3.5" /> Still after the due date — it will remain marked Late.
                </p>
                <p v-else-if="willBeLate === false" class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 class="h-3.5 w-3.5" /> On time — the Late indication will be removed.
                </p>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="open = false">Cancel</Button>
                    <Button type="submit" class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="processing || !value">
                        <LoaderCircle v-if="processing" class="mr-1 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-1 h-4 w-4" />
                        Save
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
