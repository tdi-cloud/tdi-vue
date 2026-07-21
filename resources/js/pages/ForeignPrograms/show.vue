<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    ArrowLeft, Users, Calendar, Building2, Mail, Phone,
    UserCircle2, X, CheckCircle2, ClipboardList, MapPin,
    UserRound, Search, FileText, Eye, Loader2, ChevronDown,
    Trash2, RefreshCw, Plus, Upload, Sparkles, Briefcase,
    IdCard, ShieldCheck, FileCheck2, PlaneTakeoff, ClipboardCheck,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';

// ── Interfaces ────────────────────────────────────────────────────────────────

interface Submission {
    id: number;
    file_path: string;
    foreign_nominee_requirement_id: number;
    requirement: { question: string };
}

interface NomineeRequirement {
    id: number;
    question: string;
    description: string | null;
    link: string | null;
    file_required: boolean;
}

interface Nominee {
    id: number;
    firstname: string;
    middle_name: string | null;
    surname: string;
    sex: 'male' | 'female' | 'other';
    age: number;
    position: string;
    agency: string;
    contact_number: string | null;
    email: string | null;
    status: string;
    accomplished_form_path: string | null;
    submissions: Submission[];
    sponsor_config: { id: number; requirements: NomineeRequirement[] } | null;
}

interface ForeignProgram {
    id: number;
    program_title: string;
    program_start: string;
    program_end: string;
    slots: number;
    modality: string;
    organizing_sponsor: string;
    sponsor: { full_name: string | null } | null;
    status: string;
    submission_date: string | null;
    embassy_deadline: string | null;
    interview_date: string | null;
    category: string | null;
    description: string | null;
    nominees: Nominee[];
}

interface SponsorRequirement {
    id: number;
    question: string;
    description: string | null;
    link: string | null;
    file_required: boolean;
    sort_order: number;
}

interface SponsorConfig {
    id: number;
    organizing_sponsor: string;
    form_title: string;
    requirements: SponsorRequirement[];
}

const props = defineProps<{ program: ForeignProgram; sponsorConfigs: SponsorConfig[] }>();

// ── State ─────────────────────────────────────────────────────────────────────

const searchQuery     = ref('');
const updatingId      = ref<number | null>(null);
const viewNominee     = ref<Nominee | null>(null);

// ── Computed ──────────────────────────────────────────────────────────────────

const filtered = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return props.program.nominees;
    return props.program.nominees.filter(n =>
        fullName(n).toLowerCase().includes(q) ||
        n.agency.toLowerCase().includes(q) ||
        n.position.toLowerCase().includes(q)
    );
});

const maleCount        = computed(() => props.program.nominees.filter(n => n.sex === 'male').length);
const femaleCount      = computed(() => props.program.nominees.filter(n => n.sex === 'female').length);
const acceptedCount    = computed(() => props.program.nominees.filter(n => n.status === 'accepted').length);
const forInterviewCount = computed(() => props.program.nominees.filter(n => n.status === 'for_interview').length);
const slotsUsed        = computed(() => props.program.nominees.length);
const slotsPercent     = computed(() => Math.min(100, Math.round((slotsUsed.value / props.program.slots) * 100)));

const sponsorDisplay = computed(() => {
    const fullName = props.program.sponsor?.full_name;
    return fullName ? `${fullName} (${props.program.organizing_sponsor})` : props.program.organizing_sponsor;
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function fullName(n: Nominee) {
    return [n.firstname, n.middle_name, n.surname].filter(Boolean).join(' ');
}

function displayName(n: Nominee) {
    const mi = n.middle_name ? ` ${n.middle_name}` : '';
    return `${n.surname.toUpperCase()}, ${n.firstname}${mi}`;
}

const formatDate = (date?: string | null) => {
    if (!date) return '—';
    const d = date.includes('T') ? new Date(date) : new Date(date + 'T00:00:00');
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

function fileUrl(path: string) {
    return `/storage/${path}`;
}

// ── Status update ─────────────────────────────────────────────────────────────

async function updateStatus(nominee: Nominee, newStatus: string) {
    updatingId.value = nominee.id;
    try {
        await axios.patch(route('foreign-nominees.status', nominee.id), { status: newStatus });
        nominee.status = newStatus;
    } finally {
        updatingId.value = null;
    }
}

function confirmDelete(nominee: Nominee) {
    if (!confirm(`Remove nominee "${fullName(nominee)}"?`)) return;
    router.delete(route('foreign-nominees.destroy', nominee.id), { preserveScroll: true });
}

// ── Missing requirement documents (upload for the first time) ───────────────────

const missingRequirements = computed(() => {
    if (!viewNominee.value?.sponsor_config) return [];
    const submittedIds = new Set(viewNominee.value.submissions.map(s => s.foreign_nominee_requirement_id));
    return viewNominee.value.sponsor_config.requirements.filter(r => !submittedIds.has(r.id));
});

const uploadingRequirementId = ref<number | null>(null);

async function handleUploadMissingRequirement(reqId: number, event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';
    if (!file || !viewNominee.value) return;

    const nominee = viewNominee.value;
    uploadingRequirementId.value = reqId;
    const data = new FormData();
    data.append('file', file);

    try {
        const res = await axios.post(route('foreign-nominee-submissions.store', [nominee.id, reqId]), data);
        nominee.submissions.push(res.data);
    } finally {
        uploadingRequirementId.value = null;
    }
}

// ── Replace submitted document ────────────────────────────────────────────────

const replaceInput  = ref<HTMLInputElement | null>(null);
const replacingId    = ref<number | null>(null);
const replaceTarget  = ref<Submission | null>(null);

function triggerReplace(sub: Submission) {
    replaceTarget.value = sub;
    replaceInput.value?.click();
}

async function handleReplaceFile(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';
    const sub = replaceTarget.value;
    if (!file || !sub) return;

    replacingId.value = sub.id;
    const data = new FormData();
    data.append('file', file);

    try {
        const res = await axios.post(route('foreign-nominee-submissions.replace', sub.id), data);
        sub.file_path = res.data.file_path;
    } finally {
        replacingId.value = null;
        replaceTarget.value = null;
    }
}

// ── Replace accomplished application form ──────────────────────────────────────

const replaceAccomplishedFormInput = ref<HTMLInputElement | null>(null);
const replacingAccomplishedForm    = ref(false);

function triggerReplaceAccomplishedForm() {
    replaceAccomplishedFormInput.value?.click();
}

async function handleReplaceAccomplishedForm(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';
    if (!file || !viewNominee.value) return;

    const nominee = viewNominee.value;
    replacingAccomplishedForm.value = true;
    const data = new FormData();
    data.append('file', file);

    try {
        const res = await axios.post(route('foreign-nominees.accomplished-form.replace', nominee.id), data);
        nominee.accomplished_form_path = res.data.accomplished_form_path;
    } finally {
        replacingAccomplishedForm.value = false;
    }
}

// ── Add Participant (admin) ─────────────────────────────────────────────────────

const showAddModal = ref(false);
const addProcessing = ref(false);
const addErrors = ref<Record<string, string>>({});

const addForm = ref({
    foreign_sponsor_config_id: '' as string | number,
    firstname:       '',
    middle_name:     '',
    surname:         '',
    sex:             '',
    age:             '' as string | number,
    position:        '',
    agency:          '',
    contact_number:  '',
    email:           '',
    status:          'for_interview',
});

const addAccomplishedFile      = ref<File | null>(null);
const addAccomplishedFileName  = ref('');
const addRequirementFiles      = ref<Record<number, File | null>>({});
const addRequirementFileNames  = ref<Record<number, string>>({});

const selectedAddConfig = computed(() =>
    props.sponsorConfigs.find(c => c.id === Number(addForm.value.foreign_sponsor_config_id)) ?? null
);

const addInitials = computed(() => {
    const f = addForm.value.firstname?.trim()?.[0] ?? '';
    const s = addForm.value.surname?.trim()?.[0] ?? '';
    return (f + s).toUpperCase() || '?';
});

const addAvatarClasses = computed(() => {
    if (addForm.value.sex === 'female') return 'bg-gradient-to-br from-pink-500 to-rose-500';
    if (addForm.value.sex === 'other') return 'bg-gradient-to-br from-violet-500 to-indigo-500';
    return 'bg-gradient-to-br from-sky-500 to-blue-600';
});

function removeAddRequirementFile(reqId: number) {
    addRequirementFiles.value[reqId] = null;
    delete addRequirementFileNames.value[reqId];
}

function removeAddAccomplishedFile() {
    addAccomplishedFile.value = null;
    addAccomplishedFileName.value = '';
}

function openAddModal() {
    addForm.value = {
        foreign_sponsor_config_id: props.sponsorConfigs.length === 1 ? props.sponsorConfigs[0].id : '',
        firstname: '', middle_name: '', surname: '', sex: '', age: '',
        position: '', agency: '', contact_number: '', email: '', status: 'for_interview',
    };
    addAccomplishedFile.value = null;
    addAccomplishedFileName.value = '';
    addRequirementFiles.value = {};
    addRequirementFileNames.value = {};
    addErrors.value = {};
    showAddModal.value = true;
}

function closeAddModal() {
    showAddModal.value = false;
}

function handleAddRequirementFile(reqId: number, event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (file) {
        addRequirementFiles.value[reqId]     = file;
        addRequirementFileNames.value[reqId] = file.name;
    }
}

function handleAddAccomplishedFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (file) {
        addAccomplishedFile.value     = file;
        addAccomplishedFileName.value = file.name;
    }
}

function submitAddParticipant() {
    const data = new FormData();

    if (addForm.value.foreign_sponsor_config_id) {
        data.append('foreign_sponsor_config_id', String(addForm.value.foreign_sponsor_config_id));
    }
    data.append('firstname', addForm.value.firstname);
    data.append('middle_name', addForm.value.middle_name ?? '');
    data.append('surname', addForm.value.surname);
    data.append('sex', addForm.value.sex);
    data.append('age', String(addForm.value.age));
    data.append('position', addForm.value.position);
    data.append('agency', addForm.value.agency);
    data.append('contact_number', addForm.value.contact_number ?? '');
    data.append('email', addForm.value.email ?? '');
    data.append('status', addForm.value.status);

    if (addAccomplishedFile.value) {
        data.append('accomplished_form', addAccomplishedFile.value);
    }

    for (const [reqId, file] of Object.entries(addRequirementFiles.value)) {
        if (file) {
            data.append(`requirement_${reqId}`, file);
        }
    }

    addProcessing.value = true;
    router.post(route('foreign-nominees.store', props.program.id), data, {
        preserveScroll: true,
        onSuccess: () => { showAddModal.value = false; },
        onError: (errors) => { addErrors.value = errors as Record<string, string>; },
        onFinish: () => { addProcessing.value = false; },
    });
}

// ── Lookups ───────────────────────────────────────────────────────────────────

const statusLabels: Record<string, string> = {
    for_interview:  'For Interview',
    endorsed:       'Endorsed',
    waiting_result: 'Waiting Result',
    not_endorsed:   'Not Endorsed',
    accepted:       'Accepted',
    regret:         'Regret',
    cancelled:      'Cancelled',
};

const statusColors: Record<string, string> = {
    for_interview:  'bg-blue-100 text-blue-700',
    endorsed:       'bg-violet-100 text-violet-700',
    waiting_result: 'bg-cyan-100 text-cyan-700',
    not_endorsed:   'bg-red-100 text-red-700',
    accepted:       'bg-emerald-100 text-emerald-700',
    regret:         'bg-amber-100 text-amber-700',
    cancelled:      'bg-gray-200 text-gray-600',
};

const programStatusLabels: Record<string, string> = {
    for_dissemination:    'For Dissemination',
    waiting_for_nominees: 'Waiting for Nominees',
    for_interview:        'For Interview',
    for_endorsement:      'For Endorsement',
    no_nominee:           'No Nominee',
    waiting_for_result:   'Waiting for Result',
    ongoing:              'Ongoing',
    concluded:            'Concluded',
};

const modalityLabels: Record<string, string> = {
    'in-person': 'In-person',
    'online':    'Online',
    'hybrid':    'Hybrid',
};
</script>

<template>
    <Head :title="program.program_title" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4">

            <!-- Back + Actions -->
            <div class="flex items-center justify-between gap-3">
                <button
                    class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground w-fit transition-colors"
                    @click="router.visit(route('foreign-programs.index'))"
                >
                    <ArrowLeft class="h-4 w-4" /> Back to Foreign Programs
                </button>

                <Link :href="route('foreign-programs.assessment', program.id)">
                    <Button
                        size="sm"
                        class="gap-1.5 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white shadow-md shadow-indigo-600/20"
                    >
                        <ClipboardCheck class="h-4 w-4" /> Nominee Assessment
                    </Button>
                </Link>
            </div>

            <!-- Hero Banner -->
            <div class="relative rounded-2xl bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-700 p-6 text-white shadow-xl overflow-hidden">
                <div class="absolute inset-0 overflow-hidden pointer-events-none">
                    <div class="absolute -top-8 -right-8 h-48 w-48 rounded-full bg-white/5" />
                    <div class="absolute -bottom-12 -right-4 h-64 w-64 rounded-full bg-white/5" />
                    <div class="absolute top-4 right-32 h-24 w-24 rounded-full bg-white/5" />
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex flex-col gap-2 max-w-2xl">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/20 uppercase tracking-wide">
                                {{ program.category ?? 'Foreign' }}
                            </span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white/15 capitalize">
                                {{ modalityLabels[program.modality] ?? program.modality }}
                            </span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white/25">
                                {{ programStatusLabels[program.status] }}
                            </span>
                        </div>

                        <h1 class="text-xl md:text-2xl font-bold leading-tight">{{ program.program_title }}</h1>

                        <p v-if="program.description" class="text-sm text-white/75 leading-relaxed">
                            {{ program.description }}
                        </p>

                        <p class="text-sm text-white/80 flex items-center gap-1.5">
                            <Building2 class="h-3.5 w-3.5 shrink-0" /> {{ sponsorDisplay }}
                        </p>

                        <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-white/80 mt-1">
                            <span class="flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5" />
                                {{ formatDate(program.program_start) }} – {{ formatDate(program.program_end) }}
                            </span>
                            <span v-if="program.submission_date" class="flex items-center gap-1.5">
                                <ClipboardList class="h-3.5 w-3.5" />
                                Submission: {{ formatDate(program.submission_date) }}
                            </span>
                            <span v-if="program.embassy_deadline" class="flex items-center gap-1.5">
                                <MapPin class="h-3.5 w-3.5" />
                                Embassy: {{ formatDate(program.embassy_deadline) }}
                            </span>
                            <span v-if="program.interview_date" class="flex items-center gap-1.5">
                                <UserCircle2 class="h-3.5 w-3.5" />
                                Interview: {{ formatDate(program.interview_date) }}
                            </span>
                        </div>
                    </div>

                    <!-- Slots progress -->
                    <div class="shrink-0 hidden md:flex flex-col items-center gap-2 bg-white/10 rounded-2xl px-5 py-4 text-center min-w-[120px]">
                        <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Nominees</p>
                        <p class="text-3xl font-extrabold">{{ slotsUsed }}</p>
                        <p class="text-xs text-white/70">of {{ program.slots }} slots</p>
                        <div class="w-full h-1.5 rounded-full bg-white/20 mt-1">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="slotsPercent >= 100 ? 'bg-red-400' : 'bg-emerald-400'"
                                :style="{ width: slotsPercent + '%' }"
                            />
                        </div>
                        <p class="text-[10px] text-white/60">{{ slotsPercent }}% filled</p>
                    </div>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="rounded-xl border bg-background p-4 flex items-center gap-3 shadow-sm">
                    <div class="h-10 w-10 rounded-xl bg-blue-100 dark:bg-blue-950/50 flex items-center justify-center shrink-0">
                        <Users class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Total Nominees</p>
                        <p class="text-xl font-bold">{{ program.nominees.length }}</p>
                    </div>
                </div>
                <div class="rounded-xl border bg-background p-4 flex items-center gap-3 shadow-sm">
                    <div class="h-10 w-10 rounded-xl bg-blue-100 dark:bg-blue-950/50 flex items-center justify-center shrink-0">
                        <UserCircle2 class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">For Interview</p>
                        <p class="text-xl font-bold">{{ forInterviewCount }}</p>
                    </div>
                </div>
                <div class="rounded-xl border bg-background p-4 flex items-center gap-3 shadow-sm">
                    <div class="h-10 w-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/50 flex items-center justify-center shrink-0">
                        <CheckCircle2 class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Accepted</p>
                        <p class="text-xl font-bold">{{ acceptedCount }}</p>
                    </div>
                </div>
                <div class="rounded-xl border bg-background p-4 flex items-center gap-3 shadow-sm">
                    <div class="h-10 w-10 rounded-xl bg-sky-100 dark:bg-sky-950/50 flex items-center justify-center shrink-0">
                        <UserRound class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Male / Female</p>
                        <p class="text-xl font-bold">{{ maleCount }} / {{ femaleCount }}</p>
                    </div>
                </div>
            </div>

            <!-- Nominees header -->
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Users class="h-5 w-5 text-blue-600" />
                    <h2 class="text-lg font-bold">Nominees</h2>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        {{ program.nominees.length }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <p class="text-xs text-muted-foreground hidden sm:block">
                        Nominees are submitted through the public nomination form, or added directly below.
                    </p>
                    <Button
                        size="sm"
                        class="gap-1.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white shadow-md shadow-blue-600/20"
                        @click="openAddModal"
                    >
                        <Plus class="h-4 w-4" /> Add Participant
                    </Button>
                </div>
            </div>

            <!-- Search -->
            <div class="relative max-w-md">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search name, agency, position..."
                    class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-background"
                />
            </div>

            <!-- Table -->
            <div class="rounded-2xl border overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground tracking-wide">
                        <tr>
                            <th class="text-left font-semibold px-4 py-3 w-8">#</th>
                            <th class="text-left font-semibold px-4 py-3">Name</th>
                            <th class="text-left font-semibold px-4 py-3">Age</th>
                            <th class="text-left font-semibold px-4 py-3">Position</th>
                            <th class="text-left font-semibold px-4 py-3">Agency</th>
                            <th class="text-left font-semibold px-4 py-3">Contact</th>
                            <th class="text-left font-semibold px-4 py-3">Docs</th>
                            <th class="text-left font-semibold px-4 py-3 min-w-[160px]">Status</th>
                            <th class="text-center font-semibold px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="(n, idx) in filtered"
                            :key="n.id"
                            class="hover:bg-blue-50/40 dark:hover:bg-blue-950/20 transition-colors"
                        >
                            <td class="px-4 py-3 text-muted-foreground text-xs">{{ idx + 1 }}</td>

                            <!-- Name -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-7 w-7 rounded-full flex items-center justify-center shrink-0"
                                        :class="n.sex === 'female' ? 'bg-pink-100 dark:bg-pink-950/50' : 'bg-sky-100 dark:bg-sky-950/50'"
                                    >
                                        <UserRound class="h-4 w-4" :class="n.sex === 'female' ? 'text-pink-500' : 'text-sky-600'" />
                                    </div>
                                    <div>
                                        <p class="font-semibold text-xs leading-tight">{{ displayName(n) }}</p>
                                        <p class="text-[10px] text-muted-foreground capitalize">{{ n.sex }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-muted-foreground text-xs">{{ n.age }}</td>
                            <td class="px-4 py-3 text-muted-foreground text-xs">{{ n.position }}</td>

                            <!-- Agency -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 text-muted-foreground text-xs">
                                    <Building2 class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                                    {{ n.agency }}
                                </div>
                            </td>

                            <!-- Contact -->
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                <div v-if="n.contact_number" class="flex items-center gap-1">
                                    <Phone class="h-3 w-3" /> {{ n.contact_number }}
                                </div>
                                <div v-if="n.email" class="flex items-center gap-1">
                                    <Mail class="h-3 w-3" />
                                    <a :href="`mailto:${n.email}`" class="text-blue-600 hover:underline">{{ n.email }}</a>
                                </div>
                                <span v-if="!n.contact_number && !n.email">—</span>
                            </td>

                            <!-- Docs -->
                            <td class="px-4 py-3">
                                <button
                                    class="flex items-center gap-1 text-xs text-blue-600 hover:underline font-semibold"
                                    @click="viewNominee = n"
                                >
                                    <FileText class="h-3.5 w-3.5" />
                                    {{ n.submissions.length }} file(s)
                                </button>
                            </td>

                            <!-- Status — inline select -->
                            <td class="px-4 py-3">
                                <div class="relative flex items-center gap-1">
                                    <select
                                        :value="n.status"
                                        :disabled="updatingId === n.id"
                                        class="text-[11px] font-semibold rounded-full px-2.5 py-1 pr-6 border-0 outline-none cursor-pointer appearance-none"
                                        :class="statusColors[n.status]"
                                        @change="updateStatus(n, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                    <Loader2 v-if="updatingId === n.id" class="h-3.5 w-3.5 animate-spin text-muted-foreground shrink-0" />
                                    <ChevronDown v-else class="h-3 w-3 text-current opacity-50 pointer-events-none absolute right-1.5" />
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        class="p-1.5 rounded-lg text-muted-foreground hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                        title="View submissions"
                                        @click="viewNominee = n"
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        class="p-1.5 rounded-lg text-muted-foreground hover:text-red-600 hover:bg-red-50 transition-colors"
                                        title="Remove nominee"
                                        @click="confirmDelete(n)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="filtered.length === 0">
                            <td colspan="9" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3 text-muted-foreground">
                                    <Users class="h-12 w-12 opacity-20" />
                                    <p class="text-sm font-semibold">No nominees found.</p>
                                    <p class="text-xs">Nominees are submitted through the public nomination form.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- ===== Nominee Submissions Modal ===== -->
        <Teleport to="body">
            <div
                v-if="viewNominee"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="viewNominee = null"
            >
                <div class="bg-background rounded-2xl shadow-xl w-full max-w-lg flex flex-col max-h-[85vh] overflow-hidden">

                    <!-- Header -->
                    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <FileText class="h-4 w-4 text-blue-600" />
                            </div>
                            <div>
                                <p class="font-bold text-sm">{{ displayName(viewNominee) }}</p>
                                <p class="text-xs text-muted-foreground">{{ viewNominee.agency }} · {{ viewNominee.position }}</p>
                            </div>
                        </div>
                        <button class="text-muted-foreground hover:text-foreground p-1 rounded-lg" @click="viewNominee = null">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="overflow-y-auto flex-1 p-5 space-y-4">

                        <!-- Profile summary -->
                        <div class="rounded-xl bg-muted/30 border px-4 py-3 grid grid-cols-2 gap-2 text-xs">
                            <div><span class="text-muted-foreground">Sex:</span> <span class="font-semibold capitalize">{{ viewNominee.sex }}</span></div>
                            <div><span class="text-muted-foreground">Age:</span> <span class="font-semibold">{{ viewNominee.age }}</span></div>
                            <div v-if="viewNominee.contact_number"><span class="text-muted-foreground">Contact:</span> <span class="font-semibold">{{ viewNominee.contact_number }}</span></div>
                            <div v-if="viewNominee.email"><span class="text-muted-foreground">Email:</span> <span class="font-semibold">{{ viewNominee.email }}</span></div>
                        </div>

                        <!-- Submissions -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground mb-2">Submitted Documents</p>
                            <div v-if="viewNominee.submissions.length > 0" class="space-y-2">
                                <div
                                    v-for="sub in viewNominee.submissions"
                                    :key="sub.id"
                                    class="flex items-center gap-2 rounded-xl border px-4 py-3 text-sm hover:bg-muted/40 transition-colors"
                                >
                                    <FileText class="h-4 w-4 text-blue-500 shrink-0" />
                                    <a :href="fileUrl(sub.file_path)" target="_blank" class="flex-1 min-w-0">
                                        <p class="font-semibold text-xs truncate">{{ sub.requirement.question }}</p>
                                        <p class="text-[10px] text-muted-foreground truncate">{{ sub.file_path.split('/').pop() }}</p>
                                    </a>
                                    <button
                                        type="button"
                                        :disabled="replacingId === sub.id"
                                        class="shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 hover:text-amber-700 border border-amber-200 rounded-lg px-2 py-1 hover:bg-amber-50 transition-colors disabled:opacity-50"
                                        @click="triggerReplace(sub)"
                                    >
                                        <Loader2 v-if="replacingId === sub.id" class="h-3 w-3 animate-spin" />
                                        <RefreshCw v-else class="h-3 w-3" />
                                        Replace
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-muted-foreground italic">No documents submitted yet.</p>
                            <input ref="replaceInput" type="file" class="hidden" @change="handleReplaceFile" />
                        </div>

                        <!-- Missing requirement documents — upload for the first time -->
                        <div v-if="missingRequirements.length > 0">
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-600 mb-2">
                                Not Yet Submitted <span class="font-normal normal-case text-muted-foreground">— upload once available</span>
                            </p>
                            <div class="space-y-2">
                                <div
                                    v-for="req in missingRequirements"
                                    :key="req.id"
                                    class="flex items-center gap-2 rounded-xl border border-dashed border-blue-300 bg-blue-50/60 dark:bg-blue-950/30 px-4 py-3 text-sm"
                                >
                                    <FileText class="h-4 w-4 text-blue-400 shrink-0" />
                                    <p class="flex-1 min-w-0 font-semibold text-xs truncate text-foreground">{{ req.question }}</p>
                                    <label
                                        :for="`upload-req-${req.id}`"
                                        class="shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold text-blue-700 dark:text-blue-300 border border-blue-300 rounded-lg px-2 py-1 hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors cursor-pointer disabled:opacity-50"
                                    >
                                        <Loader2 v-if="uploadingRequirementId === req.id" class="h-3 w-3 animate-spin" />
                                        <Upload v-else class="h-3 w-3" />
                                        Upload
                                    </label>
                                    <input
                                        :id="`upload-req-${req.id}`"
                                        type="file"
                                        class="hidden"
                                        :disabled="uploadingRequirementId === req.id"
                                        @change="handleUploadMissingRequirement(req.id, $event)"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Accomplished form -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground mb-2">Accomplished Application Form</p>
                            <div v-if="viewNominee.accomplished_form_path" class="flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm hover:bg-blue-100 transition-colors">
                                <FileText class="h-4 w-4 text-blue-600 shrink-0" />
                                <a :href="fileUrl(viewNominee.accomplished_form_path)" target="_blank" class="flex-1">
                                    <span class="text-blue-700 font-semibold text-xs">View Accomplished Form (PDF)</span>
                                </a>
                                <button
                                    type="button"
                                    :disabled="replacingAccomplishedForm"
                                    class="shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 hover:text-amber-700 border border-amber-200 rounded-lg px-2 py-1 hover:bg-amber-50 transition-colors disabled:opacity-50"
                                    @click="triggerReplaceAccomplishedForm"
                                >
                                    <Loader2 v-if="replacingAccomplishedForm" class="h-3 w-3 animate-spin" />
                                    <RefreshCw v-else class="h-3 w-3" />
                                    Replace
                                </button>
                            </div>
                            <label
                                v-else
                                for="upload-accomplished-form"
                                class="flex items-center justify-center gap-2 cursor-pointer rounded-xl border-2 border-dashed border-blue-300 bg-blue-50/60 dark:bg-blue-950/30 px-4 py-3 text-xs font-semibold text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors"
                            >
                                <Loader2 v-if="replacingAccomplishedForm" class="h-3.5 w-3.5 animate-spin" />
                                <Upload v-else class="h-3.5 w-3.5" />
                                {{ replacingAccomplishedForm ? 'Uploading…' : 'Not yet submitted — upload PDF' }}
                            </label>
                            <input
                                id="upload-accomplished-form"
                                ref="replaceAccomplishedFormInput"
                                type="file"
                                accept=".pdf"
                                class="hidden"
                                @change="handleReplaceAccomplishedForm"
                            />
                        </div>

                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ===== Add Participant Modal ===== -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showAddModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-blue-950/40 backdrop-blur-sm p-4"
                    @click.self="closeAddModal"
                >
                    <Transition
                        appear
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-2"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                    >
                        <div class="relative bg-background rounded-3xl shadow-2xl ring-1 ring-blue-900/10 w-full max-w-xl flex flex-col max-h-[92vh] overflow-hidden">

                            <!-- Gradient hero header -->
                            <div class="relative shrink-0 bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 px-6 pt-6 pb-8 text-white overflow-hidden">
                                <div class="absolute inset-0 pointer-events-none overflow-hidden">
                                    <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10" />
                                    <div class="absolute -bottom-16 -left-6 h-48 w-48 rounded-full bg-white/5" />
                                    <PlaneTakeoff class="absolute right-6 bottom-2 h-16 w-16 text-white/10 rotate-12" />
                                </div>

                                <button
                                    class="absolute right-4 top-4 text-white/80 hover:text-white hover:bg-white/10 p-1.5 rounded-full transition-colors"
                                    @click="closeAddModal"
                                >
                                    <X class="h-4 w-4" />
                                </button>

                                <div class="relative flex items-center gap-4">
                                    <div class="h-14 w-14 shrink-0 rounded-2xl flex items-center justify-center text-lg font-extrabold shadow-lg ring-2 ring-white/30 transition-colors"
                                        :class="addAvatarClasses">
                                        {{ addInitials }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-widest text-blue-100">
                                            <Sparkles class="h-3 w-3" /> New Participant
                                        </p>
                                        <h2 class="text-xl font-extrabold leading-tight truncate">
                                            {{ addForm.firstname || addForm.surname ? `${addForm.firstname} ${addForm.surname}`.trim() : 'Add Participant' }}
                                        </h2>
                                        <p class="text-xs text-blue-100/90 truncate">{{ program.program_title }}</p>
                                    </div>
                                </div>
                            </div>

                            <form id="add-participant-form" @submit.prevent="submitAddParticipant" class="overflow-y-auto flex-1 px-6 py-5 space-y-6 -mt-4">

                                <div class="flex items-start gap-2.5 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-100 dark:border-blue-900 px-4 py-3 text-xs text-blue-800 dark:text-blue-200 shadow-sm">
                                    <ShieldCheck class="h-4 w-4 shrink-0 mt-0.5 text-blue-500" />
                                    <span>Requirement documents are <strong>optional</strong> for admin-added entries — you can save this participant now and attach files anytime later.</span>
                                </div>

                                <!-- Personal Information -->
                                <section class="space-y-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-7 w-7 rounded-lg bg-blue-100 dark:bg-blue-950/50 flex items-center justify-center">
                                            <IdCard class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <h3 class="text-sm font-bold text-foreground">Personal Information</h3>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-muted-foreground mb-1">First Name *</label>
                                            <input v-model="addForm.firstname" type="text" required
                                                class="w-full rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition" />
                                            <p v-if="addErrors.firstname" class="mt-1 text-xs text-red-500">{{ addErrors.firstname }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-muted-foreground mb-1">Middle Name</label>
                                            <input v-model="addForm.middle_name" type="text"
                                                class="w-full rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-muted-foreground mb-1">Surname *</label>
                                            <input v-model="addForm.surname" type="text" required
                                                class="w-full rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition" />
                                            <p v-if="addErrors.surname" class="mt-1 text-xs text-red-500">{{ addErrors.surname }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-muted-foreground mb-1">Sex *</label>
                                            <div class="grid grid-cols-3 gap-1.5">
                                                <button
                                                    v-for="opt in ['male', 'female', 'other']"
                                                    :key="opt"
                                                    type="button"
                                                    class="rounded-xl border px-2 py-2 text-xs font-semibold capitalize transition-colors"
                                                    :class="addForm.sex === opt
                                                        ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                                        : 'bg-background hover:bg-blue-50 dark:hover:bg-blue-950/40 text-muted-foreground border-input'"
                                                    @click="addForm.sex = opt"
                                                >
                                                    {{ opt }}
                                                </button>
                                            </div>
                                            <p v-if="addErrors.sex" class="mt-1 text-xs text-red-500">{{ addErrors.sex }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-muted-foreground mb-1">Age</label>
                                            <input v-model="addForm.age" type="number" min="18" max="100"
                                                class="w-full rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition" />
                                            <p v-if="addErrors.age" class="mt-1 text-xs text-red-500">{{ addErrors.age }}</p>
                                        </div>
                                    </div>
                                </section>

                                <!-- Assignment -->
                                <section class="space-y-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-7 w-7 rounded-lg bg-blue-100 dark:bg-blue-950/50 flex items-center justify-center">
                                            <Briefcase class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <h3 class="text-sm font-bold text-foreground">Assignment & Contact</h3>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-muted-foreground mb-1">Position *</label>
                                            <input v-model="addForm.position" type="text" required
                                                class="w-full rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition" />
                                            <p v-if="addErrors.position" class="mt-1 text-xs text-red-500">{{ addErrors.position }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-muted-foreground mb-1">Agency *</label>
                                            <input v-model="addForm.agency" type="text" required
                                                class="w-full rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition" />
                                            <p v-if="addErrors.agency" class="mt-1 text-xs text-red-500">{{ addErrors.agency }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-muted-foreground mb-1">Contact Number</label>
                                            <input v-model="addForm.contact_number" type="tel"
                                                class="w-full rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-muted-foreground mb-1">Email</label>
                                            <input v-model="addForm.email" type="email"
                                                class="w-full rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition" />
                                            <p v-if="addErrors.email" class="mt-1 text-xs text-red-500">{{ addErrors.email }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-muted-foreground mb-1.5">Status</label>
                                        <div class="flex flex-wrap gap-1.5">
                                            <button
                                                v-for="(label, key) in statusLabels"
                                                :key="key"
                                                type="button"
                                                class="rounded-full px-3 py-1 text-[11px] font-semibold border transition-all"
                                                :class="addForm.status === key
                                                    ? [statusColors[key], 'border-transparent ring-2 ring-blue-500/40 shadow-sm']
                                                    : 'bg-background text-muted-foreground border-input hover:border-blue-300'"
                                                @click="addForm.status = key"
                                            >
                                                {{ label }}
                                            </button>
                                        </div>
                                    </div>
                                </section>

                                <!-- Sponsor requirement checklist (optional) -->
                                <section v-if="sponsorConfigs.length > 0" class="space-y-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-7 w-7 rounded-lg bg-blue-100 dark:bg-blue-950/50 flex items-center justify-center">
                                            <FileCheck2 class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <h3 class="text-sm font-bold text-foreground">Documents</h3>
                                        <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-300">
                                            Optional
                                        </span>
                                    </div>

                                    <div v-if="sponsorConfigs.length > 1">
                                        <label class="block text-xs font-semibold text-muted-foreground mb-1">Requirement Checklist</label>
                                        <select v-model="addForm.foreign_sponsor_config_id"
                                            class="w-full rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition">
                                            <option value="">— None —</option>
                                            <option v-for="c in sponsorConfigs" :key="c.id" :value="c.id">{{ c.form_title }}</option>
                                        </select>
                                    </div>

                                    <div v-if="selectedAddConfig && selectedAddConfig.requirements.length > 0" class="space-y-2">
                                        <div
                                            v-for="req in selectedAddConfig.requirements"
                                            :key="req.id"
                                            class="rounded-2xl border px-4 py-3 bg-gradient-to-br from-blue-50/80 to-transparent dark:from-blue-950/30 hover:border-blue-300 transition-colors"
                                        >
                                            <p class="text-xs font-semibold text-foreground">{{ req.question }}</p>

                                            <div v-if="addRequirementFileNames[req.id]" class="mt-2 flex items-center gap-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 px-3 py-1.5">
                                                <CheckCircle2 class="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                                                <span class="text-[11px] font-medium text-emerald-700 dark:text-emerald-300 truncate flex-1">{{ addRequirementFileNames[req.id] }}</span>
                                                <button type="button" class="text-emerald-600 hover:text-emerald-800 shrink-0" @click="removeAddRequirementFile(req.id)">
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </div>
                                            <label
                                                v-else
                                                :for="`add-req-file-${req.id}`"
                                                class="mt-2 inline-flex items-center gap-1.5 cursor-pointer rounded-lg border border-dashed border-blue-300 bg-blue-50 dark:bg-blue-950/40 px-3 py-1.5 text-[11px] font-semibold text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
                                            >
                                                <Upload class="h-3 w-3" />
                                                Choose File
                                            </label>
                                            <input
                                                :id="`add-req-file-${req.id}`"
                                                type="file"
                                                class="hidden"
                                                @change="handleAddRequirementFile(req.id, $event)"
                                            />
                                        </div>
                                    </div>
                                </section>

                                <!-- Accomplished form (optional) -->
                                <section class="space-y-2">
                                    <label class="block text-xs font-semibold text-muted-foreground">
                                        Accomplished Application Form <span class="font-normal">(optional)</span>
                                    </label>

                                    <div v-if="addAccomplishedFileName" class="flex items-center gap-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 px-4 py-2.5">
                                        <CheckCircle2 class="h-4 w-4 text-emerald-600 shrink-0" />
                                        <span class="text-xs font-medium text-emerald-700 dark:text-emerald-300 truncate flex-1">{{ addAccomplishedFileName }}</span>
                                        <button type="button" class="text-emerald-600 hover:text-emerald-800 shrink-0" @click="removeAddAccomplishedFile">
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <label
                                        v-else
                                        for="add-accomplished-form"
                                        class="flex items-center justify-center gap-2 cursor-pointer rounded-xl border-2 border-dashed border-blue-300 bg-blue-50/70 dark:bg-blue-950/30 px-5 py-4 text-sm font-semibold text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors"
                                    >
                                        <Upload class="h-4 w-4" />
                                        Upload PDF
                                    </label>
                                    <input id="add-accomplished-form" type="file" accept=".pdf" class="hidden" @change="handleAddAccomplishedFile" />
                                </section>
                            </form>

                            <!-- Footer -->
                            <div class="shrink-0 flex items-center justify-end gap-2 px-6 py-4 border-t bg-muted/20">
                                <Button type="button" variant="outline" @click="closeAddModal">Cancel</Button>
                                <Button
                                    type="submit"
                                    form="add-participant-form"
                                    :disabled="addProcessing"
                                    class="gap-1.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white shadow-md shadow-blue-600/20"
                                >
                                    <Loader2 v-if="addProcessing" class="h-4 w-4 animate-spin" />
                                    <Plus v-else class="h-4 w-4" />
                                    {{ addProcessing ? 'Adding…' : 'Add Participant' }}
                                </Button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

    </AppLayout>
</template>