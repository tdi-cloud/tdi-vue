<script setup lang="ts">
import EditSubmittedAtDialog from '@/components/EditSubmittedAtDialog.vue';
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlarmClockMinus,
    Building2,
    CalendarClock,
    CheckCheck,
    CheckCircle2,
    Clock3,
    Eye,
    FileText,
    Inbox,
    Layers,
    LoaderCircle,
    MessageSquareText,
    Pencil,
    Save,
    Search,
    StickyNote,
    Trash2,
    UserCog,
    UserPlus,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface Employee {
    name?: string;
    [key: string]: any;
}

interface Participant {
    id: number;
    empcode: string;
    attendance: string;
    hours?: number | null;
    employee?: Employee | null;
    justification?: { id: number; file_path: string } | null;
}

interface Batch {
    id: number;
    batch: string;
    hours?: number | string | null;
}

interface Requirement {
    id: number;
    title: string;
    name: string;
    due_date: string;
}

interface ProgramRef {
    id: number;
    program_code: string;
    title: string;
}

interface Submission {
    id: number;
    participant_id: number;
    program_code: string;
    batch_id: number;
    requirement_id: number;
    status: string;
    file_path: string | null;
    notes: string | null;
    remarks: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    reviewed_by: string | null;
    added_by: string | null;
    participant?: Participant | null;
    batch?: Batch | null;
    requirement?: Requirement | null;
    program?: ProgramRef | null;
}

interface PaginatedSubmissions {
    data: Submission[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    submissions: PaginatedSubmissions;
    programs: ProgramRef[];
    stats: { total: number; pending: number; approved: number; rejected: number };
    filters: {
        status?: string;
        program_code?: string;
        search?: string;
    };
    // Superadmin only — "Approve all" here spans every program.
    canApproveAll: boolean;
    // Pending submissions matching the current program/search filters.
    approvablePendingCount: number;
}>();

const STATUSES = ['Pending', 'Approved', 'Rejected'];

/* ===================== FILTERS ===================== */

const search = ref(props.filters.search ?? '');
const filterProgram = ref(props.filters.program_code || 'all');
const filterStatus = ref(props.filters.status || 'all');

const applyFilters = () => {
    router.get(
        route('submissions.index'),
        {
            search: search.value || undefined,
            program_code: filterProgram.value === 'all' ? undefined : filterProgram.value,
            status: filterStatus.value === 'all' ? undefined : filterStatus.value,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

let debounce: ReturnType<typeof setTimeout>;
watch([search, filterProgram, filterStatus], () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});

/* ===================== DISPLAY HELPERS ===================== */

const participantName = (s: Submission): string => s.participant?.employee?.name ?? s.participant?.empcode ?? '—';

const isLate = (s: Submission): boolean => {
    if (!s.submitted_at || !s.requirement?.due_date) return false;
    const due = new Date(s.requirement.due_date);
    due.setHours(23, 59, 59, 999);
    return new Date(s.submitted_at) > due;
};

const formatDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—');

const formatDateTime = (d: string | null) =>
    d
        ? new Date(d).toLocaleString('en-US', {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          })
        : '—';

const statusClass = (status: string) => {
    switch (status.toLowerCase()) {
        case 'approved':
            return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20';
        case 'rejected':
            return 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20';
        default:
            return 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20';
    }
};

const statCards = computed(() => [
    { label: 'Total', value: props.stats.total, icon: FileText, tone: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' },
    { label: 'Pending', value: props.stats.pending, icon: Clock3, tone: 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' },
    {
        label: 'Approved',
        value: props.stats.approved,
        icon: CheckCircle2,
        tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
    },
    { label: 'Rejected', value: props.stats.rejected, icon: XCircle, tone: 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400' },
]);

const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
const fileUrl = (s: Submission) => (s.file_path ? `/storage/${s.file_path}` : null);

/* ===================== FILE PREVIEW ===================== */

const showFilePreview = ref(false);
const previewSubmission = ref<Submission | null>(null);

const openFilePreview = (s: Submission) => {
    previewSubmission.value = s;
    showFilePreview.value = true;
};

/* ===================== EDIT SUBMISSION DATE ===================== */

// Admins can correct the submission date (e.g. to clear a wrong "Late" indication).
const showEditDate = ref(false);
const editDateTarget = ref<Submission | null>(null);

const openEditDate = (s: Submission) => {
    editDateTarget.value = s;
    showEditDate.value = true;
};

/* ===================== REVIEW DIALOG ===================== */

const showReview = ref(false);
const reviewTarget = ref<Submission | null>(null);
const reviewStatus = ref<'Approved' | 'Rejected'>('Approved');
const reviewRemarks = ref('');
const reviewProcessing = ref(false);

const openReview = (s: Submission) => {
    reviewTarget.value = s;
    reviewStatus.value = s.status.toLowerCase() === 'rejected' ? 'Rejected' : 'Approved';
    reviewRemarks.value = s.remarks ?? '';
    showReview.value = true;
};

const submitReview = () => {
    if (!reviewTarget.value) return;
    reviewProcessing.value = true;
    router.patch(
        route('submissions.review', reviewTarget.value.id),
        { status: reviewStatus.value, remarks: reviewRemarks.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showReview.value = false;
                reviewTarget.value = null;
            },
            onFinish: () => {
                reviewProcessing.value = false;
            },
        },
    );
};

/* ===================== REMOVE SUBMISSION ===================== */

const { confirmDialog } = useConfirm();

/* ===================== APPROVE ALL (superadmin) ===================== */

const approvingAll = ref(false);

const approveAll = async () => {
    const count = props.approvablePendingCount;
    if (!count) return;
    const programTitle = props.programs.find((p) => p.program_code === filterProgram.value)?.title;
    const scope = programTitle ? `of "${programTitle}"` : 'across all programs';
    const confirmed = await confirmDialog(
        `Approve all ${count} pending submission(s) ${scope}${search.value ? ' that match your search' : ''}? Rejected submissions will not be changed.`,
        { title: 'Approve all pending submissions?', confirmText: 'Approve all' },
    );
    if (!confirmed) return;

    approvingAll.value = true;
    router.post(
        route('submissions.approve-all'),
        {
            program_code: filterProgram.value === 'all' ? null : filterProgram.value,
            search: search.value || null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                approvingAll.value = false;
            },
        },
    );
};
const removingId = ref<number | null>(null);

// For wrongly uploaded submissions — deletes the record and its uploaded file.
const removeSubmission = async (s: Submission) => {
    const confirmed = await confirmDialog(
        `Remove the "${s.requirement?.title ?? 'requirement'}" submission of ${participantName(s)}? The uploaded file will also be deleted.`,
        { title: 'Remove submission?', confirmText: 'Remove' },
    );
    if (!confirmed) return;

    removingId.value = s.id;
    router.delete(route('submissions.destroy', s.id), {
        preserveScroll: true,
        onFinish: () => {
            removingId.value = null;
        },
    });
};

/* ===================== ATTENDANCE DIALOG ===================== */

const showAttendance = ref(false);
const attendanceTarget = ref<Submission | null>(null);
const attStatus = ref<'Pending' | 'Complete' | 'Absent'>('Pending');
const attHours = ref('');
const attFile = ref<File | null>(null);
const attProcessing = ref(false);
const attErrors = ref<{ hours?: string; justification?: string }>({});

const attendanceClass = (attendance: string) => {
    switch (attendance) {
        case 'Complete':
            return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20 dark:hover:bg-emerald-500/20';
        case 'Absent':
            return 'bg-red-50 text-red-700 ring-red-600/20 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20 dark:hover:bg-red-500/20';
        default:
            return 'bg-amber-50 text-amber-700 ring-amber-600/20 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20 dark:hover:bg-amber-500/20';
    }
};

const openAttendance = (s: Submission) => {
    attendanceTarget.value = s;

    const current = ['Pending', 'Complete', 'Absent'].includes(s.participant?.attendance ?? '') ? s.participant!.attendance : 'Pending';
    attStatus.value = current as 'Pending' | 'Complete' | 'Absent';
    attHours.value = current === 'Complete' && s.participant?.hours ? String(s.participant.hours) : String(s.batch?.hours ?? '');

    attFile.value = null;
    attErrors.value = {};
    showAttendance.value = true;
};

const onAttFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    attFile.value = input.files?.[0] ?? null;
};

const submitAttendance = () => {
    if (!attendanceTarget.value?.participant) return;
    attErrors.value = {};

    if (attStatus.value === 'Complete') {
        const h = Number(attHours.value);
        if (!attHours.value || isNaN(h) || h <= 0) {
            attErrors.value.hours = 'Please enter the completed hours.';
            return;
        }
    }

    if (attStatus.value === 'Absent' && !attFile.value && !attendanceTarget.value.participant.justification) {
        attErrors.value.justification = 'Please upload the justification memo for the absence.';
        return;
    }

    attProcessing.value = true;
    router.post(
        route('participants.attendance', attendanceTarget.value.participant.id),
        {
            attendance: attStatus.value,
            hours: attStatus.value === 'Complete' ? attHours.value : 0,
            justification: attStatus.value === 'Absent' ? attFile.value : null,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showAttendance.value = false;
                attendanceTarget.value = null;
            },
            onError: (errors) => {
                attErrors.value = errors as any;
            },
            onFinish: () => {
                attProcessing.value = false;
            },
        },
    );
};

// Kapag galing sa notification (may ?submission_id=), i-open agad ang review
// dialog para doon mismong submission na kaka-encode lang.
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const submissionId = params.get('submission_id');
    if (!submissionId) return;

    const target = props.submissions.data.find((s) => String(s.id) === submissionId);
    if (target) openReview(target);
});
</script>

<template>
    <Head title="Submissions" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
            <!-- Header + toolbar -->
            <section class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3.5">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 shadow-md shadow-blue-600/20 ring-1 ring-inset ring-white/10"
                        >
                            <Inbox class="h-5 w-5 text-white" />
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-xl font-bold leading-tight tracking-tight text-slate-900 dark:text-slate-50 sm:text-2xl">Submissions</h1>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                All requirement submissions across every program — review and set status here.
                            </p>
                        </div>
                    </div>

                    <!-- Approve all pending (superadmin only; follows the program + search filters) -->
                    <Button
                        v-if="canApproveAll"
                        class="h-10 w-full shrink-0 bg-emerald-600 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus-visible:ring-emerald-500 dark:bg-emerald-600/90 dark:hover:bg-emerald-600 sm:w-auto"
                        :disabled="!approvablePendingCount || approvingAll"
                        @click="approveAll"
                    >
                        <LoaderCircle v-if="approvingAll" class="mr-1.5 h-4 w-4 animate-spin" />
                        <CheckCheck v-else class="mr-1.5 h-4 w-4" />
                        Approve all
                        <span
                            v-if="approvablePendingCount"
                            class="ml-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white/25 px-1.5 text-[11px] font-bold leading-none"
                        >
                            {{ approvablePendingCount > 999 ? '999+' : approvablePendingCount }}
                        </span>
                    </Button>
                </div>

                <!-- Filters -->
                <div
                    class="grid grid-cols-1 gap-2.5 rounded-2xl border border-slate-200/80 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900/60 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_16rem_11rem]"
                >
                    <!-- Search -->
                    <div class="relative sm:col-span-2 lg:col-span-1">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="search"
                            class="h-10 w-full pl-9 text-sm"
                            placeholder="Search name, empcode, or requirement..."
                            aria-label="Search submissions"
                        />
                    </div>

                    <!-- Program filter -->
                    <Select v-model="filterProgram">
                        <SelectTrigger class="h-10 w-full min-w-0 text-sm" aria-label="Filter by program">
                            <SelectValue placeholder="All programs" class="truncate" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem class="text-xs" value="all">All programs</SelectItem>
                            <SelectItem v-for="p in programs" :key="p.id" :value="p.program_code" class="text-xs">
                                {{ p.title }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Status filter -->
                    <Select v-model="filterStatus">
                        <SelectTrigger class="h-10 w-full text-sm" aria-label="Filter by status">
                            <SelectValue placeholder="All status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem class="text-xs" value="all">All status</SelectItem>
                            <SelectItem v-for="st in STATUSES" :key="st" :value="st" class="text-xs">{{ st }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </section>

            <!-- Stats -->
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div
                    v-for="card in statCards"
                    :key="card.label"
                    class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900/60 dark:hover:shadow-black/20"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="card.tone">
                        <component :is="card.icon" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold tabular-nums leading-none tracking-tight text-slate-900 dark:text-slate-50">
                            {{ card.value }}
                        </p>
                        <p class="mt-1 text-xs font-medium text-muted-foreground">{{ card.label }}</p>
                    </div>
                </div>
            </section>

            <!-- Empty -->
            <div
                v-if="!submissions.data.length"
                class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center dark:border-slate-700 dark:bg-slate-900/40"
            >
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800">
                    <Inbox class="h-7 w-7 text-slate-400 dark:text-slate-500" />
                </div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No submissions found</p>
                <p class="max-w-xs text-xs text-muted-foreground">Try a different search term, program, or status filter.</p>
            </div>

            <!-- List -->
            <section v-else class="flex flex-col gap-3">
                <p class="text-xs text-muted-foreground">
                    Showing <span class="font-semibold text-foreground">{{ submissions.from ?? 0 }}–{{ submissions.to ?? 0 }}</span> of
                    <span class="font-semibold text-foreground">{{ submissions.total }}</span> submission(s)
                </p>

                <article
                    v-for="s in submissions.data"
                    :key="s.id"
                    class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition-colors hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900/60 dark:hover:border-slate-700 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="flex min-w-0 flex-1 flex-col gap-3">
                        <!-- Employee + status badges -->
                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                            <div class="min-w-0">
                                <p class="break-words text-[15px] font-semibold leading-snug text-slate-900 dark:text-slate-50">
                                    {{ participantName(s) }}
                                </p>
                                <p class="mt-0.5 font-mono text-xs text-muted-foreground">{{ s.participant?.empcode }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                    :class="statusClass(s.status)"
                                >
                                    {{ capitalize(s.status) }}
                                </span>
                                <span
                                    v-if="isLate(s)"
                                    class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-[11px] font-semibold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20"
                                >
                                    <AlarmClockMinus class="h-3 w-3" /> Late
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    :class="attendanceClass(s.participant?.attendance ?? 'Pending')"
                                    title="Edit attendance"
                                    :aria-label="`Attendance: ${s.participant?.attendance ?? 'Pending'}. Edit attendance`"
                                    @click="openAttendance(s)"
                                >
                                    <UserCog class="h-3 w-3" />
                                    {{ s.participant?.attendance ?? 'Pending' }}
                                    <Pencil class="h-2.5 w-2.5 opacity-70" />
                                </button>
                            </div>
                        </div>

                        <!-- Requirement + program -->
                        <div class="min-w-0">
                            <p class="flex items-start gap-1.5 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                <FileText class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                <span class="min-w-0 break-words">
                                    {{ s.requirement?.title }}
                                    <span v-if="s.requirement?.name" class="font-normal text-muted-foreground">— {{ s.requirement?.name }}</span>
                                </span>
                            </p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 pl-[22px] text-xs text-muted-foreground">
                                <span v-if="s.program?.title" class="inline-flex min-w-0 items-start gap-1">
                                    <Building2 class="mt-px h-3.5 w-3.5 shrink-0" />
                                    <span class="break-words font-medium text-blue-700 dark:text-blue-400">{{ s.program?.title }}</span>
                                </span>
                                <span v-if="s.batch?.batch" class="inline-flex items-center gap-1">
                                    <Layers class="h-3.5 w-3.5 shrink-0" />
                                    {{ s.batch?.batch }}
                                </span>
                            </div>
                        </div>

                        <!-- Submission meta -->
                        <div
                            class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-xl bg-slate-50 px-3 py-2 text-xs text-muted-foreground dark:bg-slate-800/50"
                        >
                            <span class="inline-flex items-center gap-1">
                                <CalendarClock class="h-3.5 w-3.5 shrink-0" />
                                Submitted:
                                <span class="font-semibold text-foreground">{{ formatDateTime(s.submitted_at) }}</span>
                                <button
                                    type="button"
                                    class="ml-0.5 inline-flex h-6 w-6 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-blue-100 hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:hover:bg-blue-500/15 dark:hover:text-blue-300"
                                    title="Edit submission date"
                                    aria-label="Edit submission date"
                                    @click="openEditDate(s)"
                                >
                                    <Pencil class="h-3 w-3" />
                                </button>
                            </span>
                            <span v-if="s.requirement?.due_date" class="inline-flex items-center gap-1">
                                <Clock3 class="h-3.5 w-3.5 shrink-0" />
                                Due: <span class="font-medium text-foreground">{{ formatDate(s.requirement.due_date) }}</span>
                            </span>
                            <span v-if="s.added_by" class="inline-flex min-w-0 items-center gap-1">
                                <UserPlus class="h-3.5 w-3.5 shrink-0" />
                                Added by <span class="break-words font-medium text-foreground">{{ s.added_by }}</span>
                            </span>
                        </div>

                        <!-- Notes / remarks -->
                        <div v-if="s.notes || s.remarks" class="flex flex-col gap-1.5">
                            <p v-if="s.notes" class="flex items-start gap-1.5 text-xs text-muted-foreground">
                                <StickyNote class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span class="min-w-0 whitespace-pre-line break-words leading-relaxed">{{ s.notes }}</span>
                            </p>
                            <p
                                v-if="s.remarks"
                                class="flex items-start gap-1.5 rounded-lg border-l-2 border-blue-500/60 bg-blue-50/60 px-2.5 py-1.5 text-xs text-slate-700 dark:border-blue-400/50 dark:bg-blue-500/10 dark:text-slate-300"
                            >
                                <MessageSquareText class="mt-0.5 h-3.5 w-3.5 shrink-0 text-blue-600 dark:text-blue-400" />
                                <span class="min-w-0 break-words leading-relaxed">
                                    <span class="font-semibold">Remarks:</span> {{ s.remarks }}
                                    <span v-if="s.reviewed_by" class="text-muted-foreground"> — {{ s.reviewed_by }}</span>
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div
                        class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800 lg:shrink-0 lg:flex-col lg:items-stretch lg:border-0 lg:pt-0"
                    >
                        <Button
                            v-if="fileUrl(s)"
                            variant="outline"
                            size="sm"
                            class="h-9 flex-1 text-xs sm:flex-none lg:w-32"
                            @click="openFilePreview(s)"
                        >
                            <Eye class="mr-1.5 h-3.5 w-3.5" /> View PDF
                        </Button>
                        <Button
                            size="sm"
                            class="h-9 flex-1 bg-blue-600 text-xs text-white hover:bg-blue-700 dark:bg-blue-600/90 dark:hover:bg-blue-600 sm:flex-none lg:w-32"
                            @click="openReview(s)"
                        >
                            <Pencil class="mr-1.5 h-3.5 w-3.5" /> Review
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-9 flex-1 text-xs text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10 dark:hover:text-red-300 sm:flex-none lg:w-32"
                            :disabled="removingId === s.id"
                            title="Remove submission"
                            @click="removeSubmission(s)"
                        >
                            <LoaderCircle v-if="removingId === s.id" class="mr-1.5 h-3.5 w-3.5 animate-spin" />
                            <Trash2 v-else class="mr-1.5 h-3.5 w-3.5" /> Remove
                        </Button>
                    </div>
                </article>

                <!-- Pagination -->
                <nav
                    v-if="submissions.last_page > 1"
                    class="mt-1 flex flex-col items-center gap-3 sm:flex-row sm:justify-between"
                    aria-label="Submissions pagination"
                >
                    <p class="text-xs text-muted-foreground">
                        Page <span class="font-semibold text-foreground">{{ submissions.current_page }}</span> of
                        <span class="font-semibold text-foreground">{{ submissions.last_page }}</span>
                    </p>
                    <div class="flex max-w-full flex-wrap items-center justify-center gap-1">
                        <template v-for="link in submissions.links" :key="link.label">
                            <a
                                v-if="link.url"
                                :href="link.url"
                                class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-2.5 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                :class="
                                    link.active
                                        ? 'border-blue-600 bg-blue-600 text-white shadow-sm dark:border-blue-500 dark:bg-blue-600'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-50'
                                "
                                :aria-current="link.active ? 'page' : undefined"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2.5 text-xs text-muted-foreground opacity-40"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </nav>
            </section>
        </div>

        <!-- Review dialog -->
        <Dialog :open="showReview" @update:open="showReview = $event">
            <DialogContent class="max-w-[calc(100vw-2rem)] !rounded-2xl sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"> <Pencil class="h-5 w-5 text-blue-600" /> Review Submission </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        {{ reviewTarget ? participantName(reviewTarget) : '' }} — {{ reviewTarget?.requirement?.title }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 py-1">
                    <div class="grid gap-1">
                        <Label class="text-xs">Status <span class="text-red-500">*</span></Label>
                        <Select v-model="reviewStatus">
                            <SelectTrigger class="h-8 text-xs">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="Approved">Approved</SelectItem>
                                <SelectItem class="text-xs" value="Rejected">Rejected</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs">Remarks</Label>
                        <Input class="h-8 text-xs" v-model="reviewRemarks" placeholder="e.g. Needs revision on section 3" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <Button variant="outline" size="sm" @click="showReview = false">Cancel</Button>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" :disabled="reviewProcessing" @click="submitReview">
                        <LoaderCircle v-if="reviewProcessing" class="mr-1 h-3 w-3 animate-spin" />
                        <Save v-else class="h-3.5 w-3.5" />
                        Save Review
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Attendance dialog -->
        <Dialog :open="showAttendance" @update:open="showAttendance = $event">
            <DialogContent class="max-w-[calc(100vw-2rem)] !rounded-2xl sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"> <UserCog class="h-5 w-5 text-blue-600" /> Edit Attendance </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        {{ attendanceTarget ? participantName(attendanceTarget) : '' }} — {{ attendanceTarget?.batch?.batch }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 py-1">
                    <div class="grid gap-1">
                        <Label class="text-xs">Status <span class="text-red-500">*</span></Label>
                        <Select v-model="attStatus">
                            <SelectTrigger class="h-8 text-xs">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="Pending">Pending</SelectItem>
                                <SelectItem class="text-xs" value="Complete">Complete</SelectItem>
                                <SelectItem class="text-xs" value="Absent">Absent</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="attStatus === 'Complete'" class="grid gap-1">
                        <Label class="text-xs">Completed Hours <span class="text-red-500">*</span></Label>
                        <Input class="h-8 text-xs" type="number" step="0.5" min="0.5" v-model="attHours" />
                        <p v-if="attErrors.hours" class="text-xs text-red-600 dark:text-red-400">{{ attErrors.hours }}</p>
                    </div>

                    <div v-if="attStatus === 'Absent'" class="grid gap-1">
                        <Label class="text-xs">
                            Justification Memo
                            <span v-if="!attendanceTarget?.participant?.justification" class="text-red-500">*</span>
                        </Label>
                        <p v-if="attendanceTarget?.participant?.justification && !attFile" class="text-[11px] text-muted-foreground">
                            Already has an uploaded memo. Choose a file only to replace it.
                        </p>
                        <input
                            type="file"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                            class="w-full min-w-0 rounded-md border border-input bg-background p-1 text-xs text-muted-foreground file:mr-2 file:cursor-pointer file:rounded-md file:border-0 file:bg-muted file:px-2.5 file:py-1 file:text-xs file:font-medium file:text-foreground hover:file:bg-muted/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="onAttFileChange"
                        />
                        <p v-if="attErrors.justification" class="text-xs text-red-600 dark:text-red-400">{{ attErrors.justification }}</p>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <Button variant="outline" size="sm" @click="showAttendance = false">Cancel</Button>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" :disabled="attProcessing" @click="submitAttendance">
                        <LoaderCircle v-if="attProcessing" class="mr-1 h-3 w-3 animate-spin" />
                        <Save v-else class="h-3.5 w-3.5" />
                        Save Attendance
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <EditSubmittedAtDialog
            v-model:open="showEditDate"
            :submission="editDateTarget"
            :participant-name="editDateTarget ? participantName(editDateTarget) : undefined"
        />

        <!-- File preview modal -->
        <FilePreviewModal
            :open="showFilePreview"
            :file-url="previewSubmission ? fileUrl(previewSubmission) : null"
            :title="previewSubmission ? participantName(previewSubmission) : undefined"
            :description="previewSubmission?.requirement?.title"
            @update:open="showFilePreview = $event"
        />
    </AppLayout>
</template>
