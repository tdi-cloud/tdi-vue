<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
    DropdownMenuLabel,
} from '@/components/ui/dropdown-menu';
import {
    Plus,
    LoaderCircle,
    Save,
    Layers,
    CalendarDays,
    Clock,
    MapPin,
    Users,
    Pencil,
    Trash2,
    Eye,
    FileBadge2,
    ChevronDown,
    ScrollText,
    ClipboardList,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import BatchParticipants from '@/pages/programs/BatchParticipants.vue';
import ParticipantSearch from '@/pages/programs/ParticipantSearch.vue';
import DeclarationModal from '@/pages/programs/DeclarationModal.vue';
import AttendanceModal from '@/pages/programs/AttendanceModal.vue';

// ─── Declaration ─────────────────────────────────────────────────────────────

const showDeclaration  = ref(false);
const declaringBatchId = ref<number | null>(null);

const declaringBatch = computed(() =>
    props.batches.find((b) => b.id === declaringBatchId.value) ?? null
);

const openDeclaration = (batch: any) => {
    declaringBatchId.value = batch.id;
    showDeclaration.value  = true;
};

// ─── Attendance ───────────────────────────────────────────────────────────────

const showAttendance    = ref(false);
const attendanceBatchId = ref<number | null>(null);

const attendanceBatch = computed(() =>
    props.batches.find((b) => b.id === attendanceBatchId.value) ?? null
);

const openAttendance = (batch: any) => {
    attendanceBatchId.value = batch.id;
    showAttendance.value    = true;
};

// ─── Props ───────────────────────────────────────────────────────────────────

const props = defineProps<{
    program: any;
    batches: any[];
}>();

// ─── Batch modal ─────────────────────────────────────────────────────────────

const showModal    = ref(false);
const editingBatch = ref<any | null>(null);

// ─── View participants ────────────────────────────────────────────────────────

const showParticipants = ref(false);
const viewingBatchId   = ref<number | null>(null);

const viewingBatch = computed(() =>
    props.batches.find((b) => b.id === viewingBatchId.value) ?? null
);

const openParticipants = (batch: any) => {
    viewingBatchId.value   = batch.id;
    showParticipants.value = true;
};

// ─── Form ─────────────────────────────────────────────────────────────────────

const form = useForm({
    program_code: props.program?.program_code ?? '',
    batch:        '',
    status:       'Upcoming',
    modality:     props.program?.modality ?? '',
    venue:        '',
    date_start:   '',
    date_end:     '',
    time_start:   '08:00',
    time_end:     '17:00',
    days:         '',
    hours:        '',
});

const computeDays = () => {
    if (form.date_start && form.date_end) {
        const start = new Date(form.date_start);
        const end   = new Date(form.date_end);
        const diff  = Math.floor((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;
        form.days   = diff > 0 ? String(diff) : '';
    }
};

const computeHours = () => {
    if (form.time_start && form.time_end && form.days) {
        const [sh, sm] = form.time_start.split(':').map(Number);
        const [eh, em] = form.time_end.split(':').map(Number);
        let perDay = (eh * 60 + em - (sh * 60 + sm)) / 60;
        if (perDay > 5) perDay -= 1;
        if (perDay > 0) {
            form.hours = String(perDay * Number(form.days));
        }
    }
};

watch(() => [form.date_start, form.date_end], computeDays);
watch(() => [form.time_start, form.time_end, form.days], computeHours);

const openCreate = () => {
    editingBatch.value = null;
    form.reset();
    form.clearErrors();
    form.program_code = props.program?.program_code ?? '';
    form.modality     = props.program?.modality ?? '';
    form.batch        = `Batch ${props.batches.length + 1}`;
    showModal.value   = true;
};

const openEdit = (batch: any) => {
    editingBatch.value = batch;
    form.clearErrors();
    form.program_code  = batch.program_code;
    form.batch         = batch.batch;
    form.status        = batch.status;
    form.modality      = batch.modality;
    form.venue         = batch.venue ?? '';
    form.date_start    = batch.date_start;
    form.date_end      = batch.date_end;
    form.time_start    = batch.time_start;
    form.time_end      = batch.time_end;
    form.days          = batch.days;
    form.hours         = batch.hours;
    showModal.value    = true;
};

const submit = () => {
    if (editingBatch.value) {
        form.put(route('batches.update', editingBatch.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value    = false;
                editingBatch.value = null;
                form.reset();
            },
        });
    } else {
        form.post(route('batches.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const destroy = (batch: any) => {
    if (!confirm(`Delete ${batch.batch}? This will also remove its participants.`)) return;
    router.delete(route('batches.destroy', batch.id), { preserveScroll: true });
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

const statusColor = (status: string) => {
    switch (status) {
        case 'Upcoming':  return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300';
        case 'Ongoing':   return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
        case 'Completed': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'Cancelled': return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
        default:          return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    }
};

const formatDate = (d: string) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatTime = (t: string) => {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    const ampm   = h >= 12 ? 'PM' : 'AM';
    const hr     = h % 12 === 0 ? 12 : h % 12;
    return `${hr}:${String(m).padStart(2, '0')} ${ampm}`;
};
</script>

<template>
    <div class="flex flex-col gap-3 mt-5">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold flex items-center gap-1.5">
                    <Layers class="h-4 w-4 text-blue-600" /> Batches
                </h2>
                <p class="text-xs font-semibold text-slate-400">
                    {{ batches.length }} batch(es) under {{ program?.program_code }}
                </p>
            </div>
            <Button size="sm" @click="openCreate" class="bg-blue-600 font-extrabold rounded-lg hover:bg-blue-500 dark:text-white">
                <Plus class="h-4 w-4" /> Add Batch
            </Button>
        </div>

        <!-- Participant finder -->
        <ParticipantSearch
            v-if="batches.length"
            :batches="batches"
            @locate="openParticipants"
        />

        <!-- Empty state -->
        <div v-if="!batches.length" class="flex flex-col items-center justify-center rounded-2xl border border-dashed py-14 px-6 text-center gap-3">
            <svg viewBox="0 0 200 160" class="h-36 w-auto" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="100" cy="142" rx="70" ry="8" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                <rect x="45" y="30" width="110" height="95" rx="10" fill="currentColor" class="text-blue-100 dark:text-blue-900/40" />
                <rect x="45" y="30" width="110" height="28" rx="10" fill="currentColor" class="text-blue-300 dark:text-blue-700/60" />
                <rect x="60" y="20" width="8" height="20" rx="4" fill="currentColor" class="text-blue-400 dark:text-blue-600" />
                <rect x="132" y="20" width="8" height="20" rx="4" fill="currentColor" class="text-blue-400 dark:text-blue-600" />
                <rect x="60" y="72" width="18" height="18" rx="4" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <rect x="85" y="72" width="18" height="18" rx="4" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <rect x="110" y="72" width="18" height="18" rx="4" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <rect x="60" y="97" width="18" height="18" rx="4" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <rect x="85" y="97" width="18" height="18" rx="4" fill="currentColor" class="text-amber-200 dark:text-amber-800/60" />
                <circle cx="94" cy="106" r="4" stroke="currentColor" stroke-width="2" fill="none" class="text-amber-500" />
                <path d="M94 103 v3 l2 2" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" class="text-amber-500" />
            </svg>
            <p class="text-sm font-bold text-slate-500">No batches yet</p>
            <p class="text-xs text-slate-400 max-w-xs">Add a batch to start scheduling sessions, enrolling participants, and tracking requirements for this program.</p>
        </div>

        <!-- Batch cards -->
        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-2">
            <div
                v-for="batch in batches"
                :key="batch.id"
                class="rounded-xl border bg-card p-4 shadow-sm transition hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold leading-5">{{ batch.batch }}</h3>
                        <p class="text-[11px] font-semibold text-slate-400">{{ batch.modality }}</p>
                    </div>
                    <Badge :class="statusColor(batch.status)" class="text-[10px] font-bold border-0">
                        {{ batch.status }}
                    </Badge>
                </div>

                <div class="mt-3 space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <p class="flex items-center gap-1.5">
                        <CalendarDays class="h-3.5 w-3.5 shrink-0" />
                        {{ formatDate(batch.date_start) }} – {{ formatDate(batch.date_end) }}
                        <span class="text-slate-400">({{ batch.days }} day/s)</span>
                    </p>
                    <p class="flex items-center gap-1.5">
                        <Clock class="h-3.5 w-3.5 shrink-0" />
                        {{ formatTime(batch.time_start) }} – {{ formatTime(batch.time_end) }}
                        <span class="text-slate-400">({{ batch.hours }} hr/s)</span>
                    </p>
                    <p v-if="batch.venue" class="flex items-center gap-1.5">
                        <MapPin class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{ batch.venue }}</span>
                    </p>
                    <p class="flex items-center gap-1.5">
                        <Users class="h-3.5 w-3.5 shrink-0" />
                        {{ batch.participants?.length ?? 0 }} participant(s)
                    </p>
                </div>

                <!-- Action buttons -->
                <div class="mt-3 flex justify-end items-center gap-1 border-t pt-2 px-2">

                    <!-- View Participants -->
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 px-2 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950/30"
                        @click="openParticipants(batch)"
                    >
                        <Eye class="h-3 w-3 mr-1" /> View Participants
                    </Button>

                    <!-- Generate dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
                            >
                                <FileBadge2 class="h-3 w-3 mr-1" />
                                Generate
                                <ChevronDown class="h-3 w-3 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-44">
                            <DropdownMenuLabel class="text-[10px] uppercase tracking-wide text-muted-foreground">
                                Generate PDF
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="text-xs cursor-pointer gap-2"
                                @click="openDeclaration(batch)"
                            >
                                <ScrollText class="h-3.5 w-3.5 text-emerald-600" />
                                Declaration
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="text-xs cursor-pointer gap-2"
                                @click="openAttendance(batch)"
                            >
                                <ClipboardList class="h-3.5 w-3.5 text-violet-600" />
                                Attendance
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Edit -->
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 px-2 text-xs hover:bg-muted"
                        @click="openEdit(batch)"
                    >
                        <Pencil class="h-3 w-3 mr-1" /> Edit
                    </Button>

                    <!-- Delete -->
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 px-2 text-xs text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                        @click="destroy(batch)"
                    >
                        <Trash2 class="h-3 w-3 mr-1" /> Delete
                    </Button>

                </div>
            </div>
        </div>

        <!-- Modals -->
        <BatchParticipants
            :open="showParticipants"
            :batch="viewingBatch"
            :program="program"
            @update:open="showParticipants = $event"
        />

        <DeclarationModal
            :open="showDeclaration"
            :batch="declaringBatch"
            @update:open="showDeclaration = $event"
        />

        <AttendanceModal
            :open="showAttendance"
            :batch="attendanceBatch"
            :program="program"
            @update:open="showAttendance = $event"
        />

        <!-- Add/Edit Batch Modal -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="max-w-xl flex flex-col max-h-[90vh] overflow-hidden !rounded-2xl">

                <DialogHeader class="shrink-0">
                    <DialogTitle>
                        <span class="flex gap-2 items-center">
                            <Layers /> {{ editingBatch ? 'Edit Batch' : 'Add New Batch' }}
                        </span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        {{ editingBatch
                            ? 'Update the schedule details of this batch.'
                            : `Schedule a new batch for ${program?.program_code}.` }}
                    </DialogDescription>
                </DialogHeader>

                <div class="overflow-y-auto flex-1 px-1">
                    <form id="batch-form" @submit.prevent="submit" class="grid grid-cols-2 gap-4 py-2">

                        <div class="grid gap-1">
                            <Label class="text-xs">Batch Name <span class="text-red-500">*</span></Label>
                            <Input class="text-xs h-8" v-model="form.batch" placeholder="e.g. Batch 1" />
                            <p class="text-xs text-red-500">{{ form.errors.batch }}</p>
                        </div>

                        <div class="grid gap-1">
                            <Label class="text-xs">Status <span class="text-red-500">*</span></Label>
                            <Select v-model="form.status">
                                <SelectTrigger class="text-xs h-8">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="Active">Active</SelectItem>
                                    <SelectItem class="text-xs" value="Completed">Completed</SelectItem>
                                    <SelectItem class="text-xs" value="Upcoming">Upcoming</SelectItem>
                                    <SelectItem class="text-xs" value="Rescheduled">Rescheduled</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-red-500">{{ form.errors.status }}</p>
                        </div>

                        <div class="grid gap-1">
                            <Label class="text-xs">Modality <span class="text-red-500">*</span></Label>
                            <Select v-model="form.modality">
                                <SelectTrigger class="text-xs h-8">
                                    <SelectValue placeholder="Select modality" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="In-person">In-person</SelectItem>
                                    <SelectItem class="text-xs" value="Online/Virtual">Online/Virtual</SelectItem>
                                    <SelectItem class="text-xs" value="Hybrid">Hybrid</SelectItem>
                                    <SelectItem class="text-xs" value="Self-Paced">Self-Paced</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-red-500">{{ form.errors.modality }}</p>
                        </div>

                        <div class="grid gap-1">
                            <Label class="text-xs">Venue</Label>
                            <Input class="text-xs h-8" v-model="form.venue" placeholder="e.g. TDI Training Hall / Zoom" />
                            <p class="text-xs text-red-500">{{ form.errors.venue }}</p>
                        </div>

                        <div class="grid gap-1">
                            <Label class="text-xs">Date Start <span class="text-red-500">*</span></Label>
                            <Input type="date" class="text-xs h-8" v-model="form.date_start" />
                            <p class="text-xs text-red-500">{{ form.errors.date_start }}</p>
                        </div>

                        <div class="grid gap-1">
                            <Label class="text-xs">Date End <span class="text-red-500">*</span></Label>
                            <Input type="date" class="text-xs h-8" v-model="form.date_end" />
                            <p class="text-xs text-red-500">{{ form.errors.date_end }}</p>
                        </div>

                        <div class="grid gap-1">
                            <Label class="text-xs">Time Start <span class="text-red-500">*</span></Label>
                            <Input type="time" class="text-xs h-8" v-model="form.time_start" />
                            <p class="text-xs text-red-500">{{ form.errors.time_start }}</p>
                        </div>

                        <div class="grid gap-1">
                            <Label class="text-xs">Time End <span class="text-red-500">*</span></Label>
                            <Input type="time" class="text-xs h-8" v-model="form.time_end" />
                            <p class="text-xs text-red-500">{{ form.errors.time_end }}</p>
                        </div>

                        <div class="grid gap-1">
                            <Label class="text-xs">No. of Days <span class="text-red-500">*</span></Label>
                            <Input class="text-xs h-8" v-model="form.days" placeholder="Auto-computed" />
                            <p class="text-xs text-red-500">{{ form.errors.days }}</p>
                        </div>

                        <div class="grid gap-1">
                            <Label class="text-xs">Total Hours <span class="text-red-500">*</span></Label>
                            <Input class="text-xs h-8" v-model="form.hours" placeholder="Auto-computed" />
                            <p class="text-xs text-red-500">{{ form.errors.hours }}</p>
                        </div>

                    </form>
                </div>

                <div class="shrink-0 flex justify-end gap-2 pt-3 border-t mt-2">
                    <Button type="button" variant="outline" size="sm" @click="showModal = false">Cancel</Button>
                    <Button type="submit" class="bg-blue-600 hover:bg-blue-700" form="batch-form" size="sm" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-3 w-3 animate-spin mr-1" />
                        <Save /> {{ editingBatch ? 'Update Batch' : 'Save Batch' }}
                    </Button>
                </div>

            </DialogContent>
        </Dialog>

    </div>
</template>