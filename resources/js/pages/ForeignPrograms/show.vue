<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Plus, Search, Pencil, Trash2, ArrowLeft, Users, Calendar,
    Building2, Mail, Phone, UserCircle2, X,
    CheckCircle2, ClipboardList, MapPin, Award,
    UserRound, Settings2,
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import ForeignAgencyModal from '@/components/ForeignAgencyModal.vue';

interface Participant {
    id: number;
    name: string;
    sex: 'male' | 'female' | 'other';
    position: string;
    agency: string;
    contact_no: string | null;
    email: string | null;
    status: string;
}

interface ForeignProgram {
    id: number;
    program_title: string;
    program_start: string;
    program_end: string;
    slots: number;
    modality: string;
    organizing_sponsor: string;
    status: string;
    submission_date: string | null;
    embassy_deadline: string | null;
    interview_date: string | null;
    category: string | null;
    description: string | null;
    participants: Participant[];
}

const props = defineProps<{ program: ForeignProgram }>();

const showModal      = ref(false);
const showAgencyModal = ref(false);
const editingId      = ref<number | null>(null);
const searchQuery    = ref('');
const agencies       = ref<string[]>([]);

const form = useForm({
    name:       '',
    sex:        '' as '' | 'male' | 'female' | 'other',
    position:   '',
    agency:     '',
    contact_no: '',
    email:      '',
    status:     '' as string,
});

// ─── Fetch agencies ───────────────────────────────────────────────────────────

async function fetchAgencies() {
    const res  = await fetch(route('foreign-agencies.index'), {
        headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    agencies.value = data.map((a: { id: number; name: string }) => a.name);
}

onMounted(fetchAgencies);

// ─── Computed ─────────────────────────────────────────────────────────────────

const filteredParticipants = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return props.program.participants;
    return props.program.participants.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.agency.toLowerCase().includes(q) ||
        p.position.toLowerCase().includes(q)
    );
});

const maleCount     = computed(() => props.program.participants.filter(p => p.sex === 'male').length);
const femaleCount   = computed(() => props.program.participants.filter(p => p.sex === 'female').length);
const acceptedCount = computed(() => props.program.participants.filter(p => p.status === 'accepted').length);
const slotsUsed     = computed(() => props.program.participants.length);
const slotsPercent  = computed(() => Math.min(100, Math.round((slotsUsed.value / props.program.slots) * 100)));

// ─── Modal open/close ─────────────────────────────────────────────────────────

const openAdd = () => {
    editingId.value = null;
    form.reset();
    form.sex    = '';
    form.status = '';
    showModal.value = true;
};

const openEdit = (p: Participant) => {
    editingId.value  = p.id;
    form.name        = p.name;
    form.sex         = p.sex;
    form.position    = p.position;
    form.agency      = p.agency;
    form.contact_no  = p.contact_no ?? '';
    form.email       = p.email ?? '';
    form.status      = p.status;
    showModal.value  = true;
};

const submit = () => {
    if (editingId.value) {
        form.put(route('foreign-participants.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    } else {
        form.post(route('foreign-participants.store', props.program.id), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
};

const remove = (p: Participant) => {
    if (!confirm('Remove this participant?')) return;
    router.delete(route('foreign-participants.destroy', p.id), { preserveScroll: true });
};

// ─── Agency modal ─────────────────────────────────────────────────────────────

function onAgencySelected(name: string) {
    form.agency = name;
}

// ─── Lookups ──────────────────────────────────────────────────────────────────

const sexLabel: Record<string, string> = { male: 'Male', female: 'Female', other: 'Other' };

const statusLabels: Record<string, string> = {
    endorsed:       'Endorsed',
    waiting_result: 'Waiting Result',
    not_endorsed:   'Not Endorsed',
    accepted:       'Accepted',
    regret:         'Regret',
    cancelled:      'Cancelled',
};

const statusColors: Record<string, string> = {
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

const formatDate = (date?: string | null) => {
    if (!date) return '—';
    const d = date.includes('T') ? new Date(date) : new Date(date + 'T00:00:00');
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<template>
    <Head :title="program.program_title" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4">

            <!-- Back -->
            <button
                class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground w-fit transition-colors"
                @click="router.visit(route('foreign-programs.index'))"
            >
                <ArrowLeft class="h-4 w-4" /> Back to Foreign Programs
            </button>

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
                            <Building2 class="h-3.5 w-3.5 shrink-0" /> {{ program.organizing_sponsor }}
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
                        <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Slots</p>
                        <p class="text-3xl font-extrabold">{{ slotsUsed }}</p>
                        <p class="text-xs text-white/70">of {{ program.slots }}</p>
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
                        <p class="text-xs text-muted-foreground">Total</p>
                        <p class="text-xl font-bold">{{ program.participants.length }}</p>
                    </div>
                </div>
                <div class="rounded-xl border bg-background p-4 flex items-center gap-3 shadow-sm">
                    <div class="h-10 w-10 rounded-xl bg-sky-100 dark:bg-sky-950/50 flex items-center justify-center shrink-0">
                        <UserRound class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Male</p>
                        <p class="text-xl font-bold">{{ maleCount }}</p>
                    </div>
                </div>
                <div class="rounded-xl border bg-background p-4 flex items-center gap-3 shadow-sm">
                    <div class="h-10 w-10 rounded-xl bg-pink-100 dark:bg-pink-950/50 flex items-center justify-center shrink-0">
                        <UserRound class="h-5 w-5 text-pink-500 dark:text-pink-400" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Female</p>
                        <p class="text-xl font-bold">{{ femaleCount }}</p>
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
            </div>

            <!-- Participants header -->
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Users class="h-5 w-5 text-blue-600" />
                    <h2 class="text-lg font-bold">Participants</h2>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        {{ program.participants.length }}
                    </span>
                </div>
                <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white shadow-sm" @click="openAdd">
                    <Plus class="h-4 w-4 mr-1" /> Add Participant
                </Button>
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
                            <th class="text-left font-semibold px-4 py-3 w-10">#</th>
                            <th class="text-left font-semibold px-4 py-3">Name</th>
                            <th class="text-left font-semibold px-4 py-3">Sex</th>
                            <th class="text-left font-semibold px-4 py-3">Position</th>
                            <th class="text-left font-semibold px-4 py-3">Agency</th>
                            <th class="text-left font-semibold px-4 py-3">Contact</th>
                            <th class="text-left font-semibold px-4 py-3">Email</th>
                            <th class="text-left font-semibold px-4 py-3">Status</th>
                            <th class="text-center font-semibold px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="(p, index) in filteredParticipants"
                            :key="p.id"
                            class="hover:bg-blue-50/40 dark:hover:bg-blue-950/20 transition-colors group"
                        >
                            <td class="px-4 py-3 text-muted-foreground text-xs">{{ index + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-7 w-7 rounded-full flex items-center justify-center shrink-0"
                                        :class="p.sex === 'female' ? 'bg-pink-100 dark:bg-pink-950/50' : 'bg-sky-100 dark:bg-sky-950/50'"
                                    >
                                        <UserRound
                                            class="h-4 w-4"
                                            :class="p.sex === 'female' ? 'text-pink-500' : 'text-sky-600'"
                                        />
                                    </div>
                                    <span class="font-semibold">{{ p.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ sexLabel[p.sex] }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ p.position }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 text-muted-foreground">
                                    <Building2 class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                                    <span>{{ p.agency }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span v-if="p.contact_no" class="flex items-center gap-1">
                                    <Phone class="h-3 w-3 shrink-0" /> {{ p.contact_no }}
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3">
                                <a v-if="p.email" :href="`mailto:${p.email}`" class="flex items-center gap-1 text-blue-600 hover:underline">
                                    <Mail class="h-3 w-3 shrink-0" /> {{ p.email }}
                                </a>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full" :class="statusColors[p.status]">
                                    {{ statusLabels[p.status] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        class="p-1.5 rounded-lg text-muted-foreground hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-colors"
                                        title="Edit"
                                        @click="openEdit(p)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        class="p-1.5 rounded-lg text-muted-foreground hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors"
                                        title="Remove"
                                        @click="remove(p)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="filteredParticipants.length === 0">
                            <td colspan="9" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3 text-muted-foreground">
                                    <Users class="h-12 w-12 opacity-20" />
                                    <p class="text-sm font-semibold">No participants found.</p>
                                    <p class="text-xs">Add a participant or adjust your search.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- ===== Agency Modal ===== -->
        <ForeignAgencyModal
            v-if="showAgencyModal"
            @close="showAgencyModal = false"
            @select="onAgencySelected"
            @updated="fetchAgencies"
        />

        <!-- ===== Add/Edit Participant Modal ===== -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showModal = false"
        >
            <div class="bg-background rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                <!-- Modal Header -->
                <div class="flex items-center gap-3 px-6 py-4 border-b bg-background">
                    <div
                        class="flex items-center justify-center h-9 w-9 rounded-xl shadow"
                        :class="editingId ? 'bg-amber-500' : 'bg-blue-600'"
                    >
                        <Pencil v-if="editingId" class="h-4 w-4 text-white" />
                        <UserCircle2 v-else class="h-4 w-4 text-white" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold leading-none">{{ editingId ? 'Edit Participant' : 'Add Participant' }}</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ editingId ? 'Update participant details' : 'Fill in the participant information' }}</p>
                    </div>
                    <button class="ml-auto text-muted-foreground hover:text-foreground transition-colors" @click="showModal = false">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-5">

                    <!-- Name -->
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold flex items-center gap-1.5">
                            <UserCircle2 class="h-3.5 w-3.5 text-muted-foreground" /> Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Full name"
                            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <span v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</span>
                    </div>

                    <!-- Sex + Status -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Users class="h-3.5 w-3.5 text-muted-foreground" /> Sex <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.sex" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-background">
                                <option value="" disabled>-- Select --</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            <span v-if="form.errors.sex" class="text-xs text-red-500">{{ form.errors.sex }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <CheckCircle2 class="h-3.5 w-3.5 text-muted-foreground" /> Status <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.status" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-background">
                                <option value="" disabled>-- Select Status --</option>
                                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <span v-if="form.errors.status" class="text-xs text-red-500">{{ form.errors.status }}</span>
                        </div>
                    </div>

                    <!-- Position + Agency -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Award class="h-3.5 w-3.5 text-muted-foreground" /> Position <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.position"
                                type="text"
                                placeholder="e.g. Director III"
                                class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <span v-if="form.errors.position" class="text-xs text-red-500">{{ form.errors.position }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Building2 class="h-3.5 w-3.5 text-muted-foreground" /> Agency <span class="text-red-500">*</span>
                            </label>
                            <div class="flex gap-2">
                                <select
                                    v-model="form.agency"
                                    class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-background"
                                >
                                    <option value="" disabled>-- Select Agency --</option>
                                    <option v-for="a in agencies" :key="a" :value="a">{{ a }}</option>
                                </select>
                                <button
                                    type="button"
                                    class="shrink-0 px-2.5 rounded-lg border text-muted-foreground hover:text-blue-600 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/30 transition-colors"
                                    title="Manage agencies"
                                    @click="showAgencyModal = true"
                                >
                                    <Settings2 class="h-4 w-4" />
                                </button>
                            </div>
                            <span v-if="form.errors.agency" class="text-xs text-red-500">{{ form.errors.agency }}</span>
                        </div>
                    </div>

                    <!-- Contact + Email -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Phone class="h-3.5 w-3.5 text-muted-foreground" /> Contact No.
                            </label>
                            <input
                                v-model="form.contact_no"
                                type="text"
                                placeholder="e.g. 09XX XXX XXXX"
                                class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Mail class="h-3.5 w-3.5 text-muted-foreground" /> Email
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="email@example.com"
                                class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-2 px-6 py-4 border-t bg-background">
                    <Button variant="outline" @click="showModal = false">Cancel</Button>
                    <Button
                        :class="editingId ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-blue-600 hover:bg-blue-700 dark:text-white'"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        <Plus v-if="!editingId && !form.processing" class="h-4 w-4 mr-1" />
                        <Pencil v-if="editingId && !form.processing" class="h-4 w-4 mr-1" />
                        {{ form.processing ? 'Saving...' : (editingId ? 'Update Participant' : 'Add Participant') }}
                    </Button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>