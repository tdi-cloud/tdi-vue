<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { router } from '@inertiajs/vue3';
import {
    Award,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    FileCheck,
    Filter,
    Plus,
    Search,
    Trash2,
    Upload,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const { confirmDialog } = useConfirm();

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
    Participation: {
        bg: 'bg-violet-50 dark:bg-violet-950/40',
        text: 'text-violet-700 dark:text-violet-300',
        border: 'border-violet-300 dark:border-violet-700',
        dot: 'bg-violet-500',
    },
    Completion: {
        bg: 'bg-emerald-50 dark:bg-emerald-950/40',
        text: 'text-emerald-700 dark:text-emerald-300',
        border: 'border-emerald-300 dark:border-emerald-700',
        dot: 'bg-emerald-500',
    },
    Appearance: {
        bg: 'bg-sky-50 dark:bg-sky-950/40',
        text: 'text-sky-700 dark:text-sky-300',
        border: 'border-sky-300 dark:border-sky-700',
        dot: 'bg-sky-500',
    },
    Appreciation: {
        bg: 'bg-amber-50 dark:bg-amber-950/40',
        text: 'text-amber-700 dark:text-amber-300',
        border: 'border-amber-300 dark:border-amber-700',
        dot: 'bg-amber-500',
    },
    Recognition: {
        bg: 'bg-rose-50 dark:bg-rose-950/40',
        text: 'text-rose-700 dark:text-rose-300',
        border: 'border-rose-300 dark:border-rose-700',
        dot: 'bg-rose-500',
    },
    Achievement: {
        bg: 'bg-orange-50 dark:bg-orange-950/40',
        text: 'text-orange-700 dark:text-orange-300',
        border: 'border-orange-300 dark:border-orange-700',
        dot: 'bg-orange-500',
    },
};

// ─── State ───────────────────────────────────────────────────────────────────

const search = ref('');
const batchFilter = ref<number | ''>('');
const statusFilter = ref<string>('');
const page = ref(1);

const modalOpen = ref(false);
const modalParticipant = ref<Participant | null>(null);
const modalBatch = ref<Batch | null>(null);
const editingCert = ref<Certificate | null>(null);

const form = ref({
    type: 'Participation' as string,
    status: 'Pending' as string,
    issued_date: '',
    issued_by: '',
    remarks: '',
    file: null as File | null,
});

const processing = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

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
        rows = rows.filter((r) => r.batch.id === batchFilter.value);
    }

    if (search.value.trim()) {
        const q = search.value.trim().toLowerCase();
        rows = rows.filter((r) => {
            const name = r.participant.employee?.name?.toLowerCase() ?? '';
            const code = r.participant.empcode.toLowerCase();
            return name.includes(q) || code.includes(q);
        });
    }

    // Keep only participants who have at least one cert matching the status filter
    // (or all participants if no filter is active)
    if (statusFilter.value !== '') {
        rows = rows.filter((r) => (r.participant.certificates ?? []).some((c) => c.status === statusFilter.value));
    }

    return rows;
});

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)));
const paginated = computed(() => {
    const s = (page.value - 1) * PAGE_SIZE;
    return filtered.value.slice(s, s + PAGE_SIZE);
});

const stats = computed(() => {
    let total = 0,
        issued = 0,
        pending = 0;
    for (const { participant } of allRows.value) {
        for (const c of participant.certificates ?? []) {
            total++;
            if (c.status === 'Issued') issued++;
            if (c.status === 'Pending') pending++;
        }
    }
    return { participants: allRows.value.length, total, issued, pending };
});

// Kailangan ng file: walang bagong na-select na file AT walang existing file na naka-save.
const fileRequired = computed(() => !form.value.file && !editingCert.value?.file_name);

watch([search, batchFilter, statusFilter], () => {
    page.value = 1;
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function certForType(p: Participant, type: string): Certificate | undefined {
    return (p.certificates ?? []).find((c) => c.type === type);
}

function formatDate(d: string | null): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function statusStyle(status: string) {
    if (status === 'Issued') return { icon: CheckCircle2, cls: 'text-emerald-600 dark:text-emerald-400' };
    if (status === 'Revoked') return { icon: XCircle, cls: 'text-red-500 dark:text-red-400' };
    return { icon: Clock, cls: 'text-amber-500 dark:text-amber-400' };
}

// ─── Modal ───────────────────────────────────────────────────────────────────

function openModal(participant: Participant, batch: Batch, type: string) {
    const existing = certForType(participant, type);
    modalParticipant.value = participant;
    modalBatch.value = batch;
    editingCert.value = existing ?? null;
    form.value = {
        type,
        status: existing?.status ?? 'Pending',
        issued_date: existing?.issued_date ? String(existing.issued_date).split('T')[0] : '',
        issued_by: existing?.issued_by ?? '',
        remarks: existing?.remarks ?? '',
        file: null,
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
    data.append('batch_id', String(modalBatch.value.id));
    data.append('program_code', props.program.program_code);
    data.append('type', form.value.type);
    data.append('status', form.value.status);
    if (form.value.issued_date) data.append('issued_date', form.value.issued_date);
    if (form.value.issued_by) data.append('issued_by', form.value.issued_by);
    if (form.value.remarks) data.append('remarks', form.value.remarks);
    if (form.value.file) data.append('file', form.value.file);
    router.post(route('certificates.store'), data, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => {
            processing.value = false;
        },
    });
}

async function deleteCert(cert: Certificate) {
    if (!(await confirmDialog(`Delete this ${cert.type} certificate? This cannot be undone.`))) return;
    router.delete(route('certificates.destroy', cert.id), { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-col gap-5 pb-6">
        <!-- ── Summary cards ─────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                class="flex flex-col gap-1 rounded-xl border bg-gradient-to-br from-violet-50 to-violet-100/50 p-4 dark:from-violet-950/40 dark:to-violet-900/20"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-600 dark:text-violet-400">Participants</p>
                <p class="text-2xl font-extrabold text-violet-800 dark:text-violet-200">{{ stats.participants }}</p>
            </div>
            <div
                class="flex flex-col gap-1 rounded-xl border bg-gradient-to-br from-sky-50 to-sky-100/50 p-4 dark:from-sky-950/40 dark:to-sky-900/20"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-600 dark:text-sky-400">Total Certs</p>
                <p class="text-2xl font-extrabold text-sky-800 dark:text-sky-200">{{ stats.total }}</p>
            </div>
            <div
                class="flex flex-col gap-1 rounded-xl border bg-gradient-to-br from-emerald-50 to-emerald-100/50 p-4 dark:from-emerald-950/40 dark:to-emerald-900/20"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Issued</p>
                <p class="text-2xl font-extrabold text-emerald-800 dark:text-emerald-200">{{ stats.issued }}</p>
            </div>
            <div
                class="flex flex-col gap-1 rounded-xl border bg-gradient-to-br from-amber-50 to-amber-100/50 p-4 dark:from-amber-950/40 dark:to-amber-900/20"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">Pending</p>
                <p class="text-2xl font-extrabold text-amber-800 dark:text-amber-200">{{ stats.pending }}</p>
            </div>
        </div>

        <!-- ── Toolbar ──────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative min-w-52 flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Search by name or employee code…" class="pl-9" />
                <button
                    v-if="search"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="search = ''"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Batch filter -->
            <div class="relative">
                <Filter class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <select
                    v-model="batchFilter"
                    class="h-9 cursor-pointer appearance-none rounded-md border bg-background pl-9 pr-8 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="">All Batches</option>
                    <option v-for="b in program.batches" :key="b.id" :value="b.id">{{ b.batch }}</option>
                </select>
            </div>

            <!-- Status filter pills -->
            <div class="flex items-center gap-1.5">
                <button
                    class="h-8 rounded-full border px-3 text-xs font-semibold transition-all"
                    :class="
                        statusFilter === '' ? 'border-foreground bg-foreground text-background' : 'border-border text-muted-foreground hover:bg-muted'
                    "
                    @click="statusFilter = ''"
                >
                    All
                </button>
                <button
                    class="flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-all"
                    :class="
                        statusFilter === 'Pending'
                            ? 'border-amber-500 bg-amber-500 text-white'
                            : 'border-border text-muted-foreground hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-950/30 dark:hover:text-amber-400'
                    "
                    @click="statusFilter = statusFilter === 'Pending' ? '' : 'Pending'"
                >
                    <Clock class="h-3 w-3" /> Pending
                </button>
                <button
                    class="flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-all"
                    :class="
                        statusFilter === 'Issued'
                            ? 'border-emerald-600 bg-emerald-600 text-white'
                            : 'border-border text-muted-foreground hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-950/30 dark:hover:text-emerald-400'
                    "
                    @click="statusFilter = statusFilter === 'Issued' ? '' : 'Issued'"
                >
                    <CheckCircle2 class="h-3 w-3" /> Issued
                </button>
                <button
                    class="flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-all"
                    :class="
                        statusFilter === 'Revoked'
                            ? 'border-red-600 bg-red-600 text-white'
                            : 'border-border text-muted-foreground hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30 dark:hover:text-red-400'
                    "
                    @click="statusFilter = statusFilter === 'Revoked' ? '' : 'Revoked'"
                >
                    <XCircle class="h-3 w-3" /> Revoked
                </button>
            </div>

            <span class="ml-auto text-xs text-muted-foreground"> {{ filtered.length }} participant{{ filtered.length !== 1 ? 's' : '' }} </span>
        </div>

        <!-- ── Participant cards grid ─────────────────────────────────────── -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="{ participant, batch } in paginated"
                :key="`${participant.id}-${batch.id}`"
                class="flex flex-col overflow-hidden rounded-2xl border bg-card shadow-sm"
            >
                <!-- Card header -->
                <div class="flex items-center gap-3 border-b bg-muted/30 p-4">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                        :class="participant.employee?.avatar_color ?? 'bg-slate-400'"
                    >
                        {{ participant.employee?.initials ?? '?' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold leading-tight">
                            {{ participant.employee?.name ?? participant.empcode }}
                        </p>
                        <p class="text-xs text-muted-foreground">{{ participant.empcode }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="
                            participant.attendance === 'Complete'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300'
                                : 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300'
                        "
                    >
                        {{ participant.attendance }}{{ participant.hours > 0 ? ` · ${participant.hours}h` : '' }}
                    </span>
                </div>

                <!-- Batch label -->
                <div class="px-4 pb-1 pt-2">
                    <p class="text-[11px] font-medium text-muted-foreground">
                        {{ batch.batch }} · {{ formatDate(batch.date_start) }} – {{ formatDate(batch.date_end) }}
                    </p>
                </div>

                <!-- Certificates list -->
                <div class="flex flex-1 flex-col gap-2 px-4 pb-4">
                    <!-- Existing certificates -->
                    <div
                        v-for="cert in participant.certificates ?? []"
                        :key="cert.id"
                        class="flex items-center gap-2.5 rounded-lg px-3 py-2 transition-opacity"
                        :class="[
                            TYPE_COLORS[cert.type]?.bg ?? 'bg-muted/40',
                            statusFilter !== '' && cert.status !== statusFilter ? 'opacity-30' : '',
                        ]"
                    >
                        <div class="h-2 w-2 shrink-0 rounded-full" :class="TYPE_COLORS[cert.type]?.dot ?? 'bg-gray-400'"></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold" :class="TYPE_COLORS[cert.type]?.text">
                                {{ cert.type }}
                            </p>
                            <p v-if="cert.certificate_number" class="text-[10px] leading-tight text-muted-foreground">
                                {{ cert.certificate_number }}
                            </p>
                        </div>
                        <component :is="statusStyle(cert.status).icon" class="h-4 w-4 shrink-0" :class="statusStyle(cert.status).cls" />
                        <div class="flex shrink-0 items-center gap-0.5">
                            <a
                                v-if="cert.file_url"
                                :href="cert.file_url"
                                target="_blank"
                                class="rounded p-1 text-muted-foreground transition-colors hover:bg-black/10 hover:text-foreground dark:hover:bg-white/10"
                                title="View PDF"
                            >
                                <Eye class="h-3.5 w-3.5" />
                            </a>
                            <button
                                class="rounded p-1 text-muted-foreground transition-colors hover:bg-black/10 hover:text-foreground dark:hover:bg-white/10"
                                title="Edit"
                                @click="openModal(participant, batch, cert.type)"
                            >
                                <FileCheck class="h-3.5 w-3.5" />
                            </button>
                            <button
                                class="rounded p-1 text-muted-foreground transition-colors hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30"
                                title="Delete"
                                @click="deleteCert(cert)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Empty hint -->
                    <p v-if="(participant.certificates ?? []).length === 0" class="py-1 text-center text-[11px] text-muted-foreground">
                        No certificates issued yet
                    </p>

                    <!-- Issue button -->
                    <button
                        class="mt-auto flex items-center justify-center gap-1.5 rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground transition-all hover:border-primary hover:bg-primary/5 hover:text-primary"
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
            <Award class="mb-3 h-12 w-12 opacity-20" />
            <p class="text-sm font-semibold">No participants found</p>
            <p class="mt-1 text-xs">
                {{
                    search || batchFilter !== ''
                        ? 'Try adjusting your search or filter.'
                        : 'Add participants with Complete or Pending attendance to manage their certificates.'
                }}
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
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 backdrop-blur-sm sm:items-center"
            @click.self="closeModal"
        >
            <div class="flex max-h-[90vh] w-full max-w-md flex-col overflow-y-auto rounded-2xl bg-background shadow-xl">
                <!-- Header -->
                <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b bg-background p-5">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                            :class="modalParticipant?.employee?.avatar_color ?? 'bg-slate-400'"
                        >
                            {{ modalParticipant?.employee?.initials ?? '?' }}
                        </div>
                        <div>
                            <p class="text-sm font-bold">{{ modalParticipant?.employee?.name ?? modalParticipant?.empcode }}</p>
                            <p class="text-xs text-muted-foreground">{{ modalBatch?.batch }}</p>
                        </div>
                    </div>
                    <button class="mt-0.5 rounded-lg p-1 text-muted-foreground hover:text-foreground" @click="closeModal">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="flex flex-col gap-4 p-5">
                    <!-- Type grid -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Certificate Type</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="t in CERT_TYPES"
                                :key="t"
                                class="rounded-lg border px-1 py-2 text-center text-xs font-semibold transition-all"
                                :class="
                                    form.type === t
                                        ? `${TYPE_COLORS[t]?.bg} ${TYPE_COLORS[t]?.text} ${TYPE_COLORS[t]?.border}`
                                        : 'border-border hover:bg-muted'
                                "
                                @click="form.type = t"
                            >
                                <span class="flex flex-wrap items-center justify-center gap-1.5">
                                    <span class="h-2 w-2 shrink-0 rounded-full" :class="TYPE_COLORS[t]?.dot"></span>
                                    {{ t }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status</label>
                        <div class="flex gap-2">
                            <button
                                v-for="s in CERT_STATUSES"
                                :key="s"
                                class="flex-1 rounded-lg border py-2 text-xs font-semibold transition-all"
                                :class="
                                    form.status === s
                                        ? s === 'Issued'
                                            ? 'border-emerald-600 bg-emerald-600 text-white'
                                            : s === 'Revoked'
                                              ? 'border-red-600 bg-red-600 text-white'
                                              : 'border-amber-500 bg-amber-500 text-white'
                                        : 'border-border hover:bg-muted'
                                "
                                @click="form.status = s"
                            >
                                {{ s }}
                            </button>
                        </div>
                    </div>

                    <!-- Date + Issued by -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Date Issued</label>
                            <Input v-model="form.issued_date" type="date" class="h-9 text-sm" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Issued By</label>
                            <Input v-model="form.issued_by" placeholder="Signatory name" class="h-9 text-sm" />
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Remarks</label>
                        <textarea
                            v-model="form.remarks"
                            placeholder="Optional notes…"
                            rows="2"
                            class="resize-none rounded-md border bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>

                    <!-- File upload -->
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Certificate PDF <span class="text-red-500">*</span>
                        </label>
                        <label
                            class="flex h-10 cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 text-sm text-muted-foreground transition-colors hover:bg-muted"
                            :class="fileRequired ? 'border-red-300 dark:border-red-700' : ''"
                        >
                            <Upload class="h-4 w-4 shrink-0" />
                            <span class="truncate">{{ form.file?.name ?? editingCert?.file_name ?? 'Click to upload PDF…' }}</span>
                            <input ref="fileInput" type="file" accept=".pdf" class="hidden" @change="handleFile" />
                        </label>
                        <p v-if="fileRequired" class="text-[11px] text-red-500">A certificate PDF is required before saving.</p>
                    </div>

                    <!-- Quick issue shortcut -->
                    <button
                        v-if="form.status !== 'Issued'"
                        type="button"
                        class="flex items-center gap-1 self-start text-xs text-emerald-600 hover:underline dark:text-emerald-400"
                        @click="
                            () => {
                                form.status = 'Issued';
                                form.issued_date = new Date().toISOString().split('T')[0];
                            }
                        "
                    >
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        Mark as Issued with today's date
                    </button>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-2 px-5 pb-5 pt-1">
                    <Button variant="outline" @click="closeModal">Cancel</Button>
                    <Button class="bg-violet-600 text-white hover:bg-violet-700" :disabled="processing || fileRequired" @click="saveCertificate">
                        <Award class="mr-1.5 h-4 w-4" />
                        {{ processing ? 'Saving…' : editingCert ? 'Update Certificate' : 'Issue Certificate' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
