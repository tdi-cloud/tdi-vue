<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Award,
    Search,
    ChevronLeft,
    ChevronRight,
    Upload,
    Trash2,
    CheckCircle2,
    Clock,
    XCircle,
    Eye,
    Filter,
    X,
    Plus,
    FileCheck,
} from 'lucide-vue-next';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Employee {
    name: string;
    EMPCODE: string;
    initials: string;
    avatar_color: string;
}

interface Certificate {
    id: number;
    type: string;
    status: string;
    certificate_number: string | null;
    issued_date: string | null;
    issued_by: string | null;
    hours: number;
    file_path: string | null;
    file_name: string | null;
    file_url: string | null;
    uploaded_by: string | null;
    remarks: string | null;
    revoked_at: string | null;
    revoked_reason: string | null;
}

interface Participant {
    id: number;
    empcode: string;
    attendance: string;
    hours: number;
    batch_id: number;
    employee: Employee | null;
    certificates: Certificate[];
}

interface Batch {
    id: number;
    batch: string;
    date_start: string;
    date_end: string;
    participants?: Participant[];
}

interface Program {
    id: number;
    program_code: string;
    title: string;
    batches?: Batch[];
}

const props = defineProps<{
    program: Program;
}>();

// ─── Constants ───────────────────────────────────────────────────────────────

const CERT_TYPES = ['Participation', 'Completion', 'Appearance', 'Appreciation', 'Recognition', 'Achievement'] as const;
const CERT_STATUSES = ['Pending', 'Issued', 'Revoked'] as const;
const PAGE_SIZE = 12;

const TYPE_COLORS: Record<string, { bg: string; text: string; border: string; dot: string }> = {
    Participation: { bg: 'bg-violet-50 dark:bg-violet-950/40', text: 'text-violet-700 dark:text-violet-300', border: 'border-violet-300 dark:border-violet-700', dot: 'bg-violet-500' },
    Completion:    { bg: 'bg-emerald-50 dark:bg-emerald-950/40', text: 'text-emerald-700 dark:text-emerald-300', border: 'border-emerald-300 dark:border-emerald-700', dot: 'bg-emerald-500' },
    Appearance:    { bg: 'bg-sky-50 dark:bg-sky-950/40', text: 'text-sky-700 dark:text-sky-300', border: 'border-sky-300 dark:border-sky-700', dot: 'bg-sky-500' },
    Appreciation:  { bg: 'bg-amber-50 dark:bg-amber-950/40', text: 'text-amber-700 dark:text-amber-300', border: 'border-amber-300 dark:border-amber-700', dot: 'bg-amber-500' },
    Recognition:   { bg: 'bg-rose-50 dark:bg-rose-950/40', text: 'text-rose-700 dark:text-rose-300', border: 'border-rose-300 dark:border-rose-700', dot: 'bg-rose-500' },
    Achievement:   { bg: 'bg-orange-50 dark:bg-orange-950/40', text: 'text-orange-700 dark:text-orange-300', border: 'border-orange-300 dark:border-orange-700', dot: 'bg-orange-500' },
};

// ─── State ───────────────────────────────────────────────────────────────────

const search        = ref('');
const batchFilter   = ref<number | ''>('');
const statusFilter  = ref<string>('');
const page          = ref(1);

const modalOpen        = ref(false);
const modalParticipant = ref<Participant | null>(null);
const modalBatch       = ref<Batch | null>(null);
const editingCert      = ref<Certificate | null>(null);

const form = ref({
    type:        'Participation' as string,
    status:      'Pending' as string,
    issued_date: '',
    issued_by:   '',
    remarks:     '',
    file:        null as File | null,
});

const processing = ref(false);
const fileInput  = ref<HTMLInputElement | null>(null);

// ─── Computed ────────────────────────────────────────────────────────────────

const allRows = computed(() => {
    const rows: Array<{ participant: Participant; batch: Batch }> = [];
    for (const batch of props.program.batches ?? []) {
        for (const p of batch.participants ?? []) {
            if (p.attendance === 'Absent') continue;
            rows.push({ participant: p, batch });
        }
    }
    return rows;
});

const filtered = computed(() => {
    let rows = allRows.value;

    if (batchFilter.value !== '') {
        rows = rows.filter(r => r.batch.id === batchFilter.value);
    }

    if (search.value.trim()) {
        const q = search.value.trim().toLowerCase();
        rows = rows.filter(r => {
            const name = r.participant.employee?.name?.toLowerCase() ?? '';
            const code = r.participant.empcode.toLowerCase();
            return name.includes(q) || code.includes(q);
        });
    }

    // Keep only participants who have at least one cert matching the status filter
    // (or all participants if no filter is active)
    if (statusFilter.value !== '') {
        rows = rows.filter(r =>
            (r.participant.certificates ?? []).some(c => c.status === statusFilter.value)
        );
    }

    return rows;
});

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)));
const paginated  = computed(() => {
    const s = (page.value - 1) * PAGE_SIZE;
    return filtered.value.slice(s, s + PAGE_SIZE);
});

const stats = computed(() => {
    let total = 0, issued = 0, pending = 0;
    for (const { participant } of allRows.value) {
        for (const c of participant.certificates ?? []) {
            total++;
            if (c.status === 'Issued')  issued++;
            if (c.status === 'Pending') pending++;
        }
    }
    return { participants: allRows.value.length, total, issued, pending };
});

// Kailangan ng file: walang bagong na-select na file AT walang existing file na naka-save.
const fileRequired = computed(() =>
    !form.value.file && !editingCert.value?.file_name
);

watch([search, batchFilter, statusFilter], () => { page.value = 1; });

// ─── Helpers ─────────────────────────────────────────────────────────────────

function certForType(p: Participant, type: string): Certificate | undefined {
    return (p.certificates ?? []).find(c => c.type === type);
}

function formatDate(d: string | null): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function statusStyle(status: string) {
    if (status === 'Issued')  return { icon: CheckCircle2, cls: 'text-emerald-600 dark:text-emerald-400' };
    if (status === 'Revoked') return { icon: XCircle,      cls: 'text-red-500 dark:text-red-400' };
    return { icon: Clock, cls: 'text-amber-500 dark:text-amber-400' };
}

// ─── Modal ───────────────────────────────────────────────────────────────────

function openModal(participant: Participant, batch: Batch, type: string) {
    const existing = certForType(participant, type);
    modalParticipant.value = participant;
    modalBatch.value       = batch;
    editingCert.value      = existing ?? null;
    form.value = {
        type,
        status:      existing?.status      ?? 'Pending',
        issued_date: existing?.issued_date  ? String(existing.issued_date).split('T')[0] : '',
        issued_by:   existing?.issued_by    ?? '',
        remarks:     existing?.remarks      ?? '',
        file:        null,
    };
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    modalParticipant.value = null;
    modalBatch.value = null;
    editingCert.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function handleFile(e: Event) {
    const t = e.target as HTMLInputElement;
    form.value.file = t.files?.[0] ?? null;
}

function saveCertificate() {
    if (!modalParticipant.value || !modalBatch.value) return;

    // Guard: huwag mag-submit kung wala pang file at walang existing file.
    if (fileRequired.value) return;

    processing.value = true;
    const data = new FormData();
    data.append('participant_id', String(modalParticipant.value.id));
    data.append('batch_id',       String(modalBatch.value.id));
    data.append('program_code',   props.program.program_code);
    data.append('type',           form.value.type);
    data.append('status',         form.value.status);
    if (form.value.issued_date) data.append('issued_date', form.value.issued_date);
    if (form.value.issued_by)   data.append('issued_by',   form.value.issued_by);
    if (form.value.remarks)     data.append('remarks',     form.value.remarks);
    if (form.value.file)        data.append('file',        form.value.file);
    router.post(route('certificates.store'), data, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish:  () => { processing.value = false; },
    });
}

function deleteCert(cert: Certificate) {
    if (!confirm(`Delete this ${cert.type} certificate? This cannot be undone.`)) return;
    router.delete(route('certificates.destroy', cert.id), { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-col gap-5 pb-6">

        <!-- ── Summary cards ─────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl border bg-gradient-to-br from-violet-50 to-violet-100/50 dark:from-violet-950/40 dark:to-violet-900/20 p-4 flex flex-col gap-1">
                <p class="text-xs font-semibold text-violet-600 dark:text-violet-400 uppercase tracking-wide">Participants</p>
                <p class="text-2xl font-extrabold text-violet-800 dark:text-violet-200">{{ stats.participants }}</p>
            </div>
            <div class="rounded-xl border bg-gradient-to-br from-sky-50 to-sky-100/50 dark:from-sky-950/40 dark:to-sky-900/20 p-4 flex flex-col gap-1">
                <p class="text-xs font-semibold text-sky-600 dark:text-sky-400 uppercase tracking-wide">Total Certs</p>
                <p class="text-2xl font-extrabold text-sky-800 dark:text-sky-200">{{ stats.total }}</p>
            </div>
            <div class="rounded-xl border bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-950/40 dark:to-emerald-900/20 p-4 flex flex-col gap-1">
                <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Issued</p>
                <p class="text-2xl font-extrabold text-emerald-800 dark:text-emerald-200">{{ stats.issued }}</p>
            </div>
            <div class="rounded-xl border bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-950/40 dark:to-amber-900/20 p-4 flex flex-col gap-1">
                <p class="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wide">Pending</p>
                <p class="text-2xl font-extrabold text-amber-800 dark:text-amber-200">{{ stats.pending }}</p>
            </div>
        </div>

        <!-- ── Toolbar ──────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative flex-1 min-w-52">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                <Input v-model="search" placeholder="Search by name or employee code…" class="pl-9" />
                <button v-if="search" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground" @click="search = ''">
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Batch filter -->
            <div class="relative">
                <Filter class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                <select
                    v-model="batchFilter"
                    class="h-9 pl-9 pr-8 rounded-md border bg-background text-sm shadow-sm appearance-none cursor-pointer focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="">All Batches</option>
                    <option v-for="b in program.batches" :key="b.id" :value="b.id">{{ b.batch }}</option>
                </select>
            </div>

            <!-- Status filter pills -->
            <div class="flex items-center gap-1.5">
                <button
                    class="h-8 px-3 rounded-full border text-xs font-semibold transition-all"
                    :class="statusFilter === ''
                        ? 'bg-foreground text-background border-foreground'
                        : 'hover:bg-muted border-border text-muted-foreground'"
                    @click="statusFilter = ''"
                >
                    All
                </button>
                <button
                    class="h-8 px-3 rounded-full border text-xs font-semibold transition-all flex items-center gap-1.5"
                    :class="statusFilter === 'Pending'
                        ? 'bg-amber-500 text-white border-amber-500'
                        : 'hover:bg-amber-50 dark:hover:bg-amber-950/30 border-border text-muted-foreground hover:text-amber-700 dark:hover:text-amber-400'"
                    @click="statusFilter = statusFilter === 'Pending' ? '' : 'Pending'"
                >
                    <Clock class="h-3 w-3" /> Pending
                </button>
                <button
                    class="h-8 px-3 rounded-full border text-xs font-semibold transition-all flex items-center gap-1.5"
                    :class="statusFilter === 'Issued'
                        ? 'bg-emerald-600 text-white border-emerald-600'
                        : 'hover:bg-emerald-50 dark:hover:bg-emerald-950/30 border-border text-muted-foreground hover:text-emerald-700 dark:hover:text-emerald-400'"
                    @click="statusFilter = statusFilter === 'Issued' ? '' : 'Issued'"
                >
                    <CheckCircle2 class="h-3 w-3" /> Issued
                </button>
                <button
                    class="h-8 px-3 rounded-full border text-xs font-semibold transition-all flex items-center gap-1.5"
                    :class="statusFilter === 'Revoked'
                        ? 'bg-red-600 text-white border-red-600'
                        : 'hover:bg-red-50 dark:hover:bg-red-950/30 border-border text-muted-foreground hover:text-red-600 dark:hover:text-red-400'"
                    @click="statusFilter = statusFilter === 'Revoked' ? '' : 'Revoked'"
                >
                    <XCircle class="h-3 w-3" /> Revoked
                </button>
            </div>

            <span class="ml-auto text-xs text-muted-foreground">
                {{ filtered.length }} participant{{ filtered.length !== 1 ? 's' : '' }}
            </span>
        </div>

        <!-- ── Participant cards grid ─────────────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div
                v-for="{ participant, batch } in paginated"
                :key="`${participant.id}-${batch.id}`"
                class="rounded-2xl border bg-card shadow-sm flex flex-col overflow-hidden"
            >
                <!-- Card header -->
                <div class="flex items-center gap-3 p-4 border-b bg-muted/30">
                    <div
                        class="h-10 w-10 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                        :class="participant.employee?.avatar_color ?? 'bg-slate-400'"
                    >
                        {{ participant.employee?.initials ?? '?' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-sm truncate leading-tight">
                            {{ participant.employee?.name ?? participant.empcode }}
                        </p>
                        <p class="text-xs text-muted-foreground">{{ participant.empcode }}</p>
                    </div>
                    <span
                        class="shrink-0 text-[11px] font-semibold px-2 py-0.5 rounded-full"
                        :class="participant.attendance === 'Complete'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300'
                            : 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300'"
                    >
                        {{ participant.attendance }}{{ participant.hours > 0 ? ` · ${participant.hours}h` : '' }}
                    </span>
                </div>

                <!-- Batch label -->
                <div class="px-4 pt-2 pb-1">
                    <p class="text-[11px] text-muted-foreground font-medium">
                        {{ batch.batch }} · {{ formatDate(batch.date_start) }} – {{ formatDate(batch.date_end) }}
                    </p>
                </div>

                <!-- Certificates list -->
                <div class="flex flex-col gap-2 px-4 pb-4 flex-1">

                    <!-- Existing certificates -->
                    <div
                        v-for="cert in (participant.certificates ?? [])"
                        :key="cert.id"
                        class="flex items-center gap-2.5 rounded-lg px-3 py-2 transition-opacity"
                        :class="[
                            TYPE_COLORS[cert.type]?.bg ?? 'bg-muted/40',
                            statusFilter !== '' && cert.status !== statusFilter ? 'opacity-30' : ''
                        ]"
                    >
                        <div class="h-2 w-2 rounded-full shrink-0" :class="TYPE_COLORS[cert.type]?.dot ?? 'bg-gray-400'"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold truncate" :class="TYPE_COLORS[cert.type]?.text">
                                {{ cert.type }}
                            </p>
                            <p v-if="cert.certificate_number" class="text-[10px] text-muted-foreground leading-tight">
                                {{ cert.certificate_number }}
                            </p>
                        </div>
                        <component
                            :is="statusStyle(cert.status).icon"
                            class="h-4 w-4 shrink-0"
                            :class="statusStyle(cert.status).cls"
                        />
                        <div class="flex items-center gap-0.5 shrink-0">
                            <a
                                v-if="cert.file_url"
                                :href="cert.file_url"
                                target="_blank"
                                class="p-1 rounded hover:bg-black/10 dark:hover:bg-white/10 text-muted-foreground hover:text-foreground transition-colors"
                                title="View PDF"
                            >
                                <Eye class="h-3.5 w-3.5" />
                            </a>
                            <button
                                class="p-1 rounded hover:bg-black/10 dark:hover:bg-white/10 text-muted-foreground hover:text-foreground transition-colors"
                                title="Edit"
                                @click="openModal(participant, batch, cert.type)"
                            >
                                <FileCheck class="h-3.5 w-3.5" />
                            </button>
                            <button
                                class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-muted-foreground hover:text-red-600 transition-colors"
                                title="Delete"
                                @click="deleteCert(cert)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Empty hint -->
                    <p
                        v-if="(participant.certificates ?? []).length === 0"
                        class="text-[11px] text-muted-foreground text-center py-1"
                    >
                        No certificates issued yet
                    </p>

                    <!-- Issue button -->
                    <button
                        class="flex items-center justify-center gap-1.5 rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground hover:border-primary hover:text-primary hover:bg-primary/5 transition-all mt-auto"
                        @click="openModal(participant, batch, 'Participation')"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Issue Certificate
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Empty state ───────────────────────────────────────────────── -->
        <div v-if="paginated.length === 0" class="flex flex-col items-center justify-center py-20 text-muted-foreground">
            <Award class="h-12 w-12 mb-3 opacity-20" />
            <p class="font-semibold text-sm">No participants found</p>
            <p class="text-xs mt-1">
                {{ search || batchFilter !== ''
                    ? 'Try adjusting your search or filter.'
                    : 'Add participants with Complete or Pending attendance to manage their certificates.' }}
            </p>
        </div>

        <!-- ── Pagination ────────────────────────────────────────────────── -->
        <div v-if="totalPages > 1" class="flex items-center justify-between">
            <p class="text-xs text-muted-foreground">Page {{ page }} of {{ totalPages }}</p>
            <div class="flex gap-1.5">
                <Button variant="outline" size="sm" :disabled="page <= 1" @click="page--">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <Button variant="outline" size="sm" :disabled="page >= totalPages" @click="page++">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- Modal                                                       -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div class="bg-background rounded-2xl shadow-xl w-full max-w-md flex flex-col max-h-[90vh] overflow-y-auto">

                <!-- Header -->
                <div class="flex items-start justify-between gap-4 p-5 border-b sticky top-0 bg-background z-10">
                    <div class="flex items-center gap-3">
                        <div
                            class="h-9 w-9 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                            :class="modalParticipant?.employee?.avatar_color ?? 'bg-slate-400'"
                        >
                            {{ modalParticipant?.employee?.initials ?? '?' }}
                        </div>
                        <div>
                            <p class="font-bold text-sm">{{ modalParticipant?.employee?.name ?? modalParticipant?.empcode }}</p>
                            <p class="text-xs text-muted-foreground">{{ modalBatch?.batch }}</p>
                        </div>
                    </div>
                    <button class="text-muted-foreground hover:text-foreground rounded-lg p-1 mt-0.5" @click="closeModal">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5 flex flex-col gap-4">

                    <!-- Type grid -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Certificate Type</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="t in CERT_TYPES"
                                :key="t"
                                class="rounded-lg border py-2 px-1 text-xs font-semibold transition-all text-center"
                                :class="form.type === t
                                    ? `${TYPE_COLORS[t]?.bg} ${TYPE_COLORS[t]?.text} ${TYPE_COLORS[t]?.border}`
                                    : 'hover:bg-muted border-border'"
                                @click="form.type = t"
                            >
                                <span class="flex items-center justify-center gap-1.5 flex-wrap">
                                    <span class="h-2 w-2 rounded-full shrink-0" :class="TYPE_COLORS[t]?.dot"></span>
                                    {{ t }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Status</label>
                        <div class="flex gap-2">
                            <button
                                v-for="s in CERT_STATUSES"
                                :key="s"
                                class="flex-1 rounded-lg border py-2 text-xs font-semibold transition-all"
                                :class="form.status === s
                                    ? s === 'Issued'  ? 'bg-emerald-600 text-white border-emerald-600'
                                    : s === 'Revoked' ? 'bg-red-600 text-white border-red-600'
                                    : 'bg-amber-500 text-white border-amber-500'
                                    : 'hover:bg-muted border-border'"
                                @click="form.status = s"
                            >
                                {{ s }}
                            </button>
                        </div>
                    </div>

                    <!-- Date + Issued by -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Date Issued</label>
                            <Input v-model="form.issued_date" type="date" class="h-9 text-sm" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Issued By</label>
                            <Input v-model="form.issued_by" placeholder="Signatory name" class="h-9 text-sm" />
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Remarks</label>
                        <textarea
                            v-model="form.remarks"
                            placeholder="Optional notes…"
                            rows="2"
                            class="px-3 py-2 rounded-md border bg-background text-sm shadow-sm resize-none focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>

                    <!-- File upload -->
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            Certificate PDF <span class="text-red-500">*</span>
                        </label>
                        <label
                            class="flex items-center gap-2 h-10 px-3 rounded-md border border-dashed cursor-pointer hover:bg-muted transition-colors text-sm text-muted-foreground"
                            :class="fileRequired ? 'border-red-300 dark:border-red-700' : ''"
                        >
                            <Upload class="h-4 w-4 shrink-0" />
                            <span class="truncate">{{ form.file?.name ?? (editingCert?.file_name ?? 'Click to upload PDF…') }}</span>
                            <input ref="fileInput" type="file" accept=".pdf" class="hidden" @change="handleFile" />
                        </label>
                        <p v-if="fileRequired" class="text-[11px] text-red-500">
                            A certificate PDF is required before saving.
                        </p>
                    </div>

                    <!-- Quick issue shortcut -->
                    <button
                        v-if="form.status !== 'Issued'"
                        type="button"
                        class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1 self-start"
                        @click="() => { form.status = 'Issued'; form.issued_date = new Date().toISOString().split('T')[0]; }"
                    >
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        Mark as Issued with today's date
                    </button>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-2 px-5 pb-5 pt-1">
                    <Button variant="outline" @click="closeModal">Cancel</Button>
                    <Button
                        class="bg-violet-600 hover:bg-violet-700 text-white"
                        :disabled="processing || fileRequired"
                        @click="saveCertificate"
                    >
                        <Award class="h-4 w-4 mr-1.5" />
                        {{ processing ? 'Saving…' : (editingCert ? 'Update Certificate' : 'Issue Certificate') }}
                    </Button>
                </div>

            </div>
        </div>
    </Teleport>
</template>