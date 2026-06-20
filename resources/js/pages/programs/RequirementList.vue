<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Plus, LoaderCircle, Save, ClipboardList, CalendarClock, Trash2, BadgeCheck, StickyNote } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Requirement {
    id: number;
    batch_id: number;
    title: string;
    name: string;
    due_date: string;
    is_required: boolean | number;
    note: string | null;
}

interface Batch {
    id: number;
    batch: string;
    date_end: string;
    requirements?: Requirement[];
}

interface Program {
    id: number;
    program_code: string;
    batches?: Batch[];
}

const props = defineProps<{
    program: Program;
}>();

/*
 * Master list ng requirement types — para sa Select choices at
 * due date PREVIEW lang ito. Ang totoong computation ay nasa
 * backend (Requirement model) para laging tama ang naka-save.
 */
const REQUIREMENT_TYPES = [
    { title: 'TREAP', name: 'Terminal Report', unit: 'days', value: 5 },
    { title: 'REAP', name: 'Terminal Report and Re-entry Action Plan', unit: 'days', value: 15 },
    { title: 'TDOR', name: 'Training Development Outcome Report', unit: 'months', value: 6 },
    { title: 'Feedback Report', name: 'Feedback Report', unit: 'days', value: 5 }, // ⚠️ palitan kung iba ang days
    { title: 'Benchmarking Report', name: 'Benchmarking Report', unit: 'days', value: 5 },
    { title: 'After Activity Report', name: 'After Activity Report', unit: 'days', value: 5 },
] as const;

type RequirementType = (typeof REQUIREMENT_TYPES)[number];

const showModal = ref(false);

const form = useForm({
    title: '',
    is_required: true as boolean,
    note: '',
});

const batches = computed(() => props.program.batches ?? []);

// Mga title na naka-add na sa kahit isang batch — hindi na lalabas sa choices
const existingTitles = computed(() => {
    const titles = new Set<string>();
    batches.value.forEach((b) => (b.requirements ?? []).forEach((r) => titles.add(r.title)));
    return titles;
});

const availableTypes = computed(() =>
    REQUIREMENT_TYPES.filter((t) => !existingTitles.value.has(t.title)),
);

const selectedType = computed<RequirementType | undefined>(() =>
    REQUIREMENT_TYPES.find((t) => t.title === form.title),
);

/*
 * ✅ BINAGO: hindi na kasama ang weekend sa pagbilang.
 * - days   → working days lang (Mon–Fri) ang bibilangin
 * - months → +N months, pero kapag tumapat sa Sat/Sun,
 *            ililipat sa susunod na Monday
 * Tugma ito sa backend (Requirement::dueDateFor) na gumagamit
 * ng addWeekdays() at nextWeekday() ng Carbon.
 */
const addBusinessDays = (start: Date, days: number): Date => {
    const d = new Date(start);
    let added = 0;
    while (added < days) {
        d.setDate(d.getDate() + 1);
        const day = d.getDay();
        if (day !== 0 && day !== 6) added++; // 0 = Sunday, 6 = Saturday
    }
    return d;
};

const computeDueDate = (dateEnd: string, type: RequirementType): Date => {
    const d = new Date(dateEnd);

    if (type.unit === 'months') {
        d.setMonth(d.getMonth() + type.value);
        // Kapag tumapat sa weekend, ilipat sa susunod na Monday
        while (d.getDay() === 0 || d.getDay() === 6) {
            d.setDate(d.getDate() + 1);
        }
        return d;
    }

    return addBusinessDays(d, type.value);
};

const formatDate = (date: string | Date) =>
    new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

// Preview ng magiging due date PER BATCH habang pumipili sa modal
const duePreview = computed(() => {
    if (!selectedType.value) return [];
    return batches.value.map((b) => ({
        batch: b.batch,
        dateEnd: formatDate(b.date_end),
        due: formatDate(computeDueDate(b.date_end, selectedType.value!)),
    }));
});

// ✅ BINAGO: nilinaw na working days / lilipat sa Monday kapag weekend
const ruleLabel = (type: RequirementType) =>
    type.unit === 'months'
        ? `${type.value} months after batch end (moved to Monday if weekend)`
        : `${type.value} working days after batch end (excluding weekends)`;

const openModal = () => {
    form.reset();
    form.clearErrors();
    form.is_required = true;
    showModal.value = true;
};

const submit = () => {
    form.post(route('programs.requirements.store', props.program.id), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            form.is_required = true;
        },
    });
};

const removeRequirement = (requirement: Requirement) => {
    if (confirm('Remove this requirement from the batch?')) {
        router.delete(route('programs.requirements.destroy', [props.program.id, requirement.id]), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <div class="flex flex-col gap-4">

        <!-- Header row -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-bold">Requirements</h1>
                <p class="text-xs text-muted-foreground">
                    Requirements that participants must submit after attending this program.
                    Due dates are auto-computed from each batch's end date (working days only).
                </p>
            </div>
            <Button
                size="sm"
                class="bg-blue-600 hover:bg-blue-700 dark:text-white"
                :disabled="!batches.length || !availableTypes.length"
                @click="openModal"
            >
                <Plus class="h-4 w-4 mr-1" /> Add Requirement
            </Button>
        </div>

        <!-- Walang batch pa -->
        <div
            v-if="!batches.length"
            class="flex flex-col items-center justify-center py-16 px-6 text-center rounded-2xl border gap-3"
        >
            <!-- Illustration: empty clipboard / no batches -->
            <svg viewBox="0 0 200 160" class="h-40 w-auto" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="100" cy="142" rx="70" ry="8" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                <rect x="60" y="25" width="80" height="110" rx="8" fill="currentColor" class="text-blue-100 dark:text-blue-900/40" />
                <rect x="80" y="18" width="40" height="16" rx="4" fill="currentColor" class="text-blue-300 dark:text-blue-700/60" />
                <rect x="74" y="50" width="52" height="6" rx="3" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <rect x="74" y="64" width="40" height="6" rx="3" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <rect x="74" y="78" width="48" height="6" rx="3" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <circle cx="148" cy="105" r="20" fill="currentColor" class="text-amber-100 dark:text-amber-900/40" />
                <path d="M140 105 h16 M148 97 v16" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" class="text-amber-500" />
            </svg>
            <p class="text-sm font-bold text-slate-500">No batches yet</p>
            <p class="text-xs text-slate-400 max-w-xs">Add a batch first in the Participants tab before adding requirements.</p>
        </div>

        <!-- Per-batch sections -->
        <template v-else>
            <div
                v-for="batch in batches"
                :key="batch.id"
                class="rounded-2xl border p-4 shadow-sm flex flex-col gap-3"
            >
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold flex items-center gap-1.5">
                        <ClipboardList class="h-4 w-4 text-blue-500" />
                        {{ batch.batch }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        Batch end: <span class="font-semibold">{{ formatDate(batch.date_end) }}</span>
                    </p>
                </div>

                <!-- Requirements ng batch na ito -->
                <template v-if="batch.requirements?.length">
                    <div
                        v-for="r in batch.requirements"
                        :key="r.id"
                        class="group flex items-start justify-between gap-3 rounded-xl border px-3 py-2.5"
                    >
                        <div class="flex flex-col gap-0.5">
                            <p class="text-sm font-bold leading-snug">
                                {{ r.title }}
                                <span class="font-normal text-muted-foreground">— {{ r.name }}</span>
                            </p>
                            <p class="text-xs text-muted-foreground flex items-center gap-1">
                                <CalendarClock class="h-3.5 w-3.5" />
                                Due on <span class="font-semibold text-foreground">{{ formatDate(r.due_date) }}</span>
                            </p>
                            <p v-if="r.note" class="text-xs text-muted-foreground flex items-start gap-1 mt-0.5">
                                <StickyNote class="h-3.5 w-3.5 shrink-0 mt-0.5" />
                                <span class="leading-snug">{{ r.note }}</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span
                                v-if="r.is_required"
                                class="inline-flex items-center gap-1 rounded-full bg-blue-600/10 text-blue-600 dark:text-blue-400 px-2 py-0.5 text-[11px] font-bold"
                            >
                                <BadgeCheck class="h-3 w-3" /> Required
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full bg-muted text-muted-foreground px-2 py-0.5 text-[11px] font-semibold"
                            >
                                Optional
                            </span>
                            <button
                                type="button"
                                class="text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-red-500 transition-all"
                                @click="removeRequirement(r)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Empty state per batch -->
                <div
                    v-else
                    class="flex flex-col sm:flex-row items-center gap-3 py-4 px-3 text-center sm:text-left rounded-xl border border-dashed"
                >
                    <!-- Illustration: small empty checklist -->
                    <svg viewBox="0 0 100 80" class="h-16 w-auto shrink-0" xmlns="http://www.w3.org/2000/svg">
                        <rect x="20" y="8" width="60" height="64" rx="6" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                        <rect x="32" y="2" width="36" height="12" rx="3" fill="currentColor" class="text-slate-300 dark:text-slate-700" />
                        <rect x="30" y="28" width="6" height="6" rx="2" stroke="currentColor" stroke-width="2" fill="none" class="text-slate-300 dark:text-slate-600" />
                        <rect x="42" y="29" width="28" height="4" rx="2" fill="currentColor" class="text-slate-300 dark:text-slate-700" />
                        <rect x="30" y="42" width="6" height="6" rx="2" stroke="currentColor" stroke-width="2" fill="none" class="text-slate-300 dark:text-slate-600" />
                        <rect x="42" y="43" width="20" height="4" rx="2" fill="currentColor" class="text-slate-300 dark:text-slate-700" />
                        <rect x="30" y="56" width="6" height="6" rx="2" stroke="currentColor" stroke-width="2" fill="none" class="text-slate-300 dark:text-slate-600" />
                        <rect x="42" y="57" width="24" height="4" rx="2" fill="currentColor" class="text-slate-300 dark:text-slate-700" />
                    </svg>
                    <p class="text-xs font-semibold text-muted-foreground">No requirements for this batch yet.</p>
                </div>
            </div>
        </template>

        <!-- Add Requirement Modal -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="max-w-lg !rounded-2xl">
                <DialogHeader>
                    <DialogTitle>
                        <span class="flex gap-2 items-center">
                            <ClipboardList class="h-5 w-5" /> Add Requirement
                        </span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        This requirement will be created for
                        <span class="font-semibold">all {{ batches.length }} batch(es)</span>
                        of this program. The due date is computed automatically from each batch's end date,
                        excluding weekends.
                    </DialogDescription>
                </DialogHeader>

                <!-- Kapag wala na ibang available types -->
                <div v-if="!availableTypes.length" class="flex flex-col items-center justify-center py-10 text-center gap-3">
                    <svg viewBox="0 0 100 80" class="h-20 w-auto" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="50" cy="38" r="28" fill="currentColor" class="text-emerald-100 dark:text-emerald-900/40" />
                        <path d="M38 38 l8 8 l16 -18" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-500" />
                    </svg>
                    <p class="text-sm font-bold text-slate-500">All requirement types added</p>
                    <p class="text-xs text-slate-400 max-w-xs">Every available requirement type has already been added to this program's batches.</p>
                </div>

                <form v-else @submit.prevent="submit" class="flex flex-col gap-4 pt-1">

                    <!-- Title -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Requirement <span class="text-red-500">*</span></Label>
                        <Select v-model="form.title">
                            <SelectTrigger class="text-xs h-8">
                                <SelectValue placeholder="Select requirement" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="t in availableTypes"
                                    :key="t.title"
                                    :value="t.title"
                                    class="text-xs"
                                >
                                    {{ t.title }} — {{ t.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="selectedType" class="text-[11px] text-muted-foreground">
                            Due date rule: {{ ruleLabel(selectedType) }}
                        </p>
                        <p class="text-xs text-red-500">{{ form.errors.title }}</p>
                    </div>

                    <!-- Auto due date preview per batch -->
                    <div v-if="duePreview.length" class="rounded-xl border bg-muted/40 p-3 flex flex-col gap-1.5">
                        <p class="text-[11px] font-extrabold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <CalendarClock class="h-3.5 w-3.5" /> Due Date Preview
                        </p>
                        <div
                            v-for="p in duePreview"
                            :key="p.batch"
                            class="flex items-center justify-between text-xs"
                        >
                            <span class="font-semibold">{{ p.batch }}</span>
                            <span class="text-muted-foreground">
                                ends {{ p.dateEnd }} →
                                <span class="font-bold text-foreground">due {{ p.due }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Required checkbox (default: checked) -->
                    <div class="flex items-center gap-2">
                        <Checkbox id="is_required" v-model:checked="form.is_required" />
                        <Label for="is_required" class="text-xs cursor-pointer">
                            This requirement is <span class="font-bold">required</span> for participants
                        </Label>
                    </div>

                    <!-- Note -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Note</Label>
                        <Textarea
                            class="text-xs min-h-[80px] resize-y"
                            v-model="form.note"
                            placeholder="Note for participants (e.g. where or how to submit this requirement)..."
                        />
                        <p class="text-xs text-red-500">{{ form.errors.note }}</p>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <Button type="button" variant="outline" size="sm" @click="showModal = false">Cancel</Button>
                        <Button
                            type="submit"
                            size="sm"
                            class="bg-blue-600 hover:bg-blue-700 dark:text-white"
                            :disabled="form.processing || !form.title"
                        >
                            <LoaderCircle v-if="form.processing" class="h-3 w-3 animate-spin mr-1" />
                            <Save class="h-4 w-4" /> Save Requirement
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

    </div>
</template>