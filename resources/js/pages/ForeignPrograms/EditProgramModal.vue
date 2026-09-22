<script setup lang="ts">
import OrganizingSponsorModal from '@/components/OrganizingSponsorModal.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { PROGRAM_STATUS_OPTIONS, toDateInput } from '@/lib/foreignPrograms';
import { useForm } from '@inertiajs/vue3';
import {
    AlignLeft,
    Banknote,
    Building,
    Building2,
    Calendar,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    FileText,
    Globe,
    Hash,
    MapPin,
    Pencil,
    Tag,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ForeignProgram {
    id: number;
    program_title: string;
    program_start: string;
    program_end: string;
    slots: number;
    modality: 'in-person' | 'online' | 'hybrid';
    organizing_sponsor: string;
    status: string;
    submission_date?: string | null;
    embassy_deadline?: string | null;
    interview_date?: string | null;
    invited_agencies?: string | null;
    attached_agency?: string | null;
    category?: string | null;
    description?: string | null;
    online_start?: string | null;
    online_end?: string | null;
    program_cost?: string | null;
    fund_source?: string | null;
}

const props = defineProps<{
    open: boolean;
    program: ForeignProgram | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const editForm = useForm({
    program_title: '',
    description: '',
    program_start: '',
    program_end: '',
    slots: 1,
    modality: 'in-person' as 'in-person' | 'online' | 'hybrid',
    online_start: '',
    online_end: '',
    program_cost: '',
    fund_source: '',
    category: 'Foreign',
    organizing_sponsor: '',
    status: 'for_dissemination',
    submission_date: '',
    embassy_deadline: '',
    interview_date: '',
    invited_agencies: '',
    attached_agency: '',
});

const showEditOnlineDates = computed(() => editForm.modality === 'online' || editForm.modality === 'hybrid');

watch(
    () => editForm.modality,
    (val) => {
        if (val === 'in-person') {
            editForm.online_start = '';
            editForm.online_end = '';
        }
    },
);

// I-populate ang form tuwing binubuksan ang modal para sa isang bagong program.
watch(
    () => [props.open, props.program],
    ([open]) => {
        if (open && props.program) {
            const p = props.program;
            editForm.program_title = p.program_title;
            editForm.description = p.description ?? '';
            editForm.program_start = toDateInput(p.program_start);
            editForm.program_end = toDateInput(p.program_end);
            editForm.slots = p.slots;
            editForm.modality = p.modality;
            editForm.online_start = toDateInput(p.online_start);
            editForm.online_end = toDateInput(p.online_end);
            editForm.program_cost = p.program_cost ?? '';
            editForm.fund_source = p.fund_source ?? '';
            editForm.category = p.category ?? 'Foreign';
            editForm.organizing_sponsor = p.organizing_sponsor;
            editForm.status = p.status;
            editForm.submission_date = toDateInput(p.submission_date);
            editForm.embassy_deadline = toDateInput(p.embassy_deadline);
            editForm.interview_date = toDateInput(p.interview_date);
            editForm.invited_agencies = p.invited_agencies ?? '';
            editForm.attached_agency = p.attached_agency ?? '';
            editForm.clearErrors();
        }
    },
    { immediate: true },
);

const close = () => {
    emit('update:open', false);
};

const submit = () => {
    if (!props.program) return;
    editForm.put(route('foreign-programs.update', props.program.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    });
};

// ── Organizing sponsor list + "manage sponsors" modal ──────────────────────────
const showSponsorModal = ref(false);
const sponsors = ref<string[]>([]);

const fetchSponsors = async () => {
    const res = await fetch(route('organizing-sponsors.index'), {
        headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    sponsors.value = data.map((s: { id: number; name: string }) => s.name);
};

fetchSponsors();

const onSponsorSelected = (name: string) => {
    editForm.organizing_sponsor = name;
};
</script>

<template>
    <OrganizingSponsorModal v-if="showSponsorModal" @close="showSponsorModal = false" @select="onSponsorSelected" @updated="fetchSponsors" />

    <Dialog :open="open" @update:open="(v) => !v && close()">
        <DialogContent class="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden !rounded-2xl p-0">
            <DialogHeader class="shrink-0 flex-row items-center gap-3 space-y-0 border-b px-6 py-4 text-left">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500 shadow">
                    <Pencil class="h-4 w-4 text-white" />
                </div>
                <div>
                    <DialogTitle class="text-base font-bold leading-none">Edit Program</DialogTitle>
                    <DialogDescription class="mt-0.5 text-xs">Update the details of this program</DialogDescription>
                </div>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto px-6 py-6">
                <div class="flex flex-col gap-6">
                    <!-- Basic Info -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <FileText class="h-3.5 w-3.5" /> <span>Basic Information</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1 md:col-span-2">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <AlignLeft class="h-3.5 w-3.5 text-muted-foreground" /> Program Title <span class="text-red-500">*</span>
                                </Label>
                                <Input v-model="editForm.program_title" type="text" />
                                <span v-if="editForm.errors.program_title" class="text-xs text-red-500">{{ editForm.errors.program_title }}</span>
                            </div>
                            <div class="flex flex-col gap-1 md:col-span-2">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Description
                                </Label>
                                <Textarea v-model="editForm.description" rows="3" placeholder="Optional" />
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <CalendarDays class="h-3.5 w-3.5" /> <span>Schedule</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program Start <span class="text-red-500">*</span>
                                </Label>
                                <Input v-model="editForm.program_start" type="date" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program End <span class="text-red-500">*</span>
                                </Label>
                                <Input v-model="editForm.program_end" type="date" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Hash class="h-3.5 w-3.5 text-muted-foreground" /> Slots <span class="text-red-500">*</span>
                                </Label>
                                <Input v-model="editForm.slots" type="number" min="1" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Globe class="h-3.5 w-3.5 text-muted-foreground" /> Modality <span class="text-red-500">*</span>
                                </Label>
                                <Select v-model="editForm.modality">
                                    <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="in-person">In-person</SelectItem>
                                        <SelectItem value="online">Online</SelectItem>
                                        <SelectItem value="hybrid">Hybrid</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <template v-if="showEditOnlineDates">
                            <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 dark:border-purple-900 dark:bg-purple-950/30">
                                <p class="mb-3 text-xs font-extrabold uppercase tracking-wide text-purple-600 dark:text-purple-400">
                                    Online Schedule
                                </p>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-1">
                                        <Label class="text-xs font-semibold">Online Start</Label>
                                        <Input v-model="editForm.online_start" type="date" class="bg-white dark:bg-background" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <Label class="text-xs font-semibold">Online End</Label>
                                        <Input v-model="editForm.online_end" type="date" class="bg-white dark:bg-background" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Classification & Funding -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <Banknote class="h-3.5 w-3.5" /> <span>Classification &amp; Funding</span>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Tag class="h-3.5 w-3.5 text-muted-foreground" /> Category <span class="text-red-500">*</span>
                                    </Label>
                                    <Select v-model="editForm.category">
                                        <SelectTrigger class="w-full bg-white dark:bg-background"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Foreign">Foreign</SelectItem>
                                            <SelectItem value="Bilateral">Bilateral</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Banknote class="h-3.5 w-3.5 text-muted-foreground" /> Program Cost
                                    </Label>
                                    <Input v-model="editForm.program_cost" type="text" class="bg-white dark:bg-background" placeholder="e.g. 50,000" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Fund Source
                                    </Label>
                                    <Select v-model="editForm.fund_source">
                                        <SelectTrigger class="w-full bg-white dark:bg-background"><SelectValue placeholder="— Select —" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="SDP">SDP</SelectItem>
                                            <SelectItem value="Other Office">Other Office</SelectItem>
                                            <SelectItem value="Sponsoring Organization">Sponsoring Organization</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Organizer & Status -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <Building2 class="h-3.5 w-3.5" /> <span>Organizer &amp; Status</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Building2 class="h-3.5 w-3.5 text-muted-foreground" /> Organizing Sponsor <span class="text-red-500">*</span>
                                </Label>
                                <div class="flex gap-2">
                                    <Select v-model="editForm.organizing_sponsor">
                                        <SelectTrigger class="w-full flex-1"><SelectValue placeholder="— Select sponsor —" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="s in sponsors" :key="s" :value="s">{{ s }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <button
                                        type="button"
                                        class="whitespace-nowrap rounded-lg border px-3 py-2 text-xs font-semibold text-amber-600 transition-colors hover:bg-amber-50"
                                        @click="showSponsorModal = true"
                                    >
                                        + Manage
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <CheckCircle2 class="h-3.5 w-3.5 text-muted-foreground" /> Status <span class="text-red-500">*</span>
                                </Label>
                                <Select v-model="editForm.status">
                                    <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="opt in PROGRAM_STATUS_OPTIONS" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    <!-- Key Dates -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <CalendarClock class="h-3.5 w-3.5" /> <span>Key Dates</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Submission Date
                                </Label>
                                <Input v-model="editForm.submission_date" type="date" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Users class="h-3.5 w-3.5 text-muted-foreground" /> Interview Date
                                </Label>
                                <Input v-model="editForm.interview_date" type="date" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <MapPin class="h-3.5 w-3.5 text-muted-foreground" /> Embassy Deadline
                                </Label>
                                <Input v-model="editForm.embassy_deadline" type="date" />
                            </div>
                        </div>
                    </div>

                    <!-- Agencies -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <Building class="h-3.5 w-3.5" /> <span>Invited Agencies</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                <Building class="h-3.5 w-3.5 text-muted-foreground" /> Agencies
                            </Label>
                            <Textarea v-model="editForm.invited_agencies" rows="2" placeholder="Comma-separated, e.g. DILG, DBM, CSC" />
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="shrink-0 border-t px-6 py-4">
                <Button variant="outline" @click="close">Cancel</Button>
                <Button class="bg-amber-500 text-white hover:bg-amber-600" :disabled="editForm.processing" @click="submit">
                    <Pencil v-if="!editForm.processing" class="mr-1 h-4 w-4" />
                    {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
