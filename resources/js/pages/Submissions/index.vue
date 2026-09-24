<script setup lang="ts">
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlarmClockMinus,
    CalendarClock,
    CheckCircle2,
    Clock3,
    Eye,
    FileText,
    Inbox,
    LoaderCircle,
    MessageSquareText,
    Pencil,
    Save,
    Search,
    StickyNote,
    UserCog,
    XCircle,
} from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

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
            return 'bg-emerald-600/10 text-emerald-600 dark:text-emerald-400';
        case 'rejected':
            return 'bg-red-600/10 text-red-600 dark:text-red-400';
        default:
            return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
    }
};

const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
const fileUrl = (s: Submission) => (s.file_path ? `/storage/${s.file_path}` : null);

/* ===================== FILE PREVIEW ===================== */

const showFilePreview = ref(false);
const previewSubmission = ref<Submission | null>(null);

const openFilePreview = (s: Submission) => {
    previewSubmission.value = s;
    showFilePreview.value = true;
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
            return 'bg-emerald-600/10 text-emerald-600 dark:text-emerald-400';
        case 'Absent':
            return 'bg-red-600/10 text-red-600 dark:text-red-400';
        default:
            return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
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
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 shadow-md">
                        <Inbox class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold leading-none">Submissions</h1>
                        <p class="mt-1 text-sm text-muted-foreground">All requirement submissions across every program — review and set status here.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Search -->
                    <div class="relative">
                        <Search class="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" class="h-9 w-56 pl-7 text-xs" placeholder="Search name, empcode, or requirement..." />
                    </div>

                    <!-- Program filter -->
                    <Select v-model="filterProgram">
                        <SelectTrigger class="h-9 w-48 text-xs">
                            <SelectValue placeholder="All programs" />
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
                        <SelectTrigger class="h-9 w-32 text-xs">
                            <SelectValue placeholder="All status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem class="text-xs" value="all">All status</SelectItem>
                            <SelectItem v-for="st in STATUSES" :key="st" :value="st" class="text-xs">{{ st }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="flex items-center gap-2.5 rounded-2xl border p-3">
                    <div class="rounded-full bg-slate-100 p-2 dark:bg-slate-800">
                        <FileText class="h-4 w-4 text-slate-500" />
                    </div>
                    <div>
                        <p class="text-lg font-extrabold leading-none">{{ stats.total }}</p>
                        <p class="text-[11px] text-muted-foreground">Total</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 rounded-2xl border p-3">
                    <div class="rounded-full bg-amber-100 p-2 dark:bg-amber-900/40">
                        <Clock3 class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-lg font-extrabold leading-none">{{ stats.pending }}</p>
                        <p class="text-[11px] text-muted-foreground">Pending</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 rounded-2xl border p-3">
                    <div class="rounded-full bg-emerald-100 p-2 dark:bg-emerald-900/40">
                        <CheckCircle2 class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-lg font-extrabold leading-none">{{ stats.approved }}</p>
                        <p class="text-[11px] text-muted-foreground">Approved</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 rounded-2xl border p-3">
                    <div class="rounded-full bg-red-100 p-2 dark:bg-red-900/40">
                        <XCircle class="h-4 w-4 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-lg font-extrabold leading-none">{{ stats.rejected }}</p>
                        <p class="text-[11px] text-muted-foreground">Rejected</p>
                    </div>
                </div>
            </div>

            <!-- Empty -->
            <div
                v-if="!submissions.data.length"
                class="flex flex-col items-center justify-center gap-3 rounded-2xl border px-6 py-16 text-center"
            >
                <Inbox class="h-12 w-12 text-muted-foreground/40" />
                <p class="text-sm font-bold text-slate-500">No submissions found</p>
                <p class="max-w-xs text-xs text-slate-400">Try a different search term, program, or status filter.</p>
            </div>

            <!-- List -->
            <div v-else class="flex flex-col gap-2">
                <p class="text-[11px] text-muted-foreground">
                    Showing {{ submissions.from ?? 0 }}–{{ submissions.to ?? 0 }} of {{ submissions.total }} submission(s)
                </p>

                <div
                    v-for="s in submissions.data"
                    :key="s.id"
                    class="flex items-start justify-between gap-3 rounded-2xl border px-4 py-3 shadow-sm"
                >
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <p class="text-sm font-bold leading-snug">
                            {{ participantName(s) }}
                            <span class="text-xs font-normal text-muted-foreground">({{ s.participant?.empcode }})</span>
                        </p>
                        <p class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                            <FileText class="h-3.5 w-3.5" />
                            <span class="font-semibold text-foreground">{{ s.requirement?.title }}</span>
                            <span v-if="s.requirement?.name">— {{ s.requirement?.name }}</span>
                            <span class="mx-1">·</span>
                            <span class="font-semibold text-blue-600">{{ s.program?.title }}</span>
                            <span class="mx-1">·</span>
                            <span>{{ s.batch?.batch }}</span>
                        </p>
                        <p class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                            <CalendarClock class="h-3.5 w-3.5" />
                            Submitted: <span class="font-semibold text-foreground">{{ formatDateTime(s.submitted_at) }}</span>
                            <template v-if="s.requirement?.due_date">
                                <span class="mx-1">·</span>
                                Due: {{ formatDate(s.requirement.due_date) }}
                            </template>
                        </p>
                        <p v-if="s.notes" class="mt-0.5 flex items-start gap-1 text-xs text-muted-foreground">
                            <StickyNote class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span class="leading-snug">{{ s.notes }}</span>
                        </p>
                        <p v-if="s.remarks" class="mt-0.5 flex items-start gap-1 text-xs text-muted-foreground">
                            <MessageSquareText class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span class="leading-snug">
                                <span class="font-semibold">Remarks:</span> {{ s.remarks }}
                                <span v-if="s.reviewed_by"> — {{ s.reviewed_by }}</span>
                            </span>
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            v-if="isLate(s)"
                            class="inline-flex items-center gap-1 rounded-full bg-red-600/10 px-2 py-0.5 text-[11px] font-bold text-red-600 dark:text-red-400"
                        >
                            <AlarmClockMinus class="h-3 w-3" /> Late
                        </span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold" :class="statusClass(s.status)">
                            {{ capitalize(s.status) }}
                        </span>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold transition-opacity hover:opacity-80"
                            :class="attendanceClass(s.participant?.attendance ?? 'Pending')"
                            title="Edit attendance"
                            @click="openAttendance(s)"
                        >
                            {{ s.participant?.attendance ?? 'Pending' }}
                            <Pencil class="h-2.5 w-2.5" />
                        </button>
                        <Button v-if="fileUrl(s)" variant="outline" size="sm" class="h-7 text-xs" @click="openFilePreview(s)">
                            <Eye class="mr-1 h-3.5 w-3.5" /> View PDF
                        </Button>
                        <Button variant="outline" size="sm" class="h-7 text-xs" @click="openReview(s)">
                            <Pencil class="mr-1 h-3.5 w-3.5" /> Review
                        </Button>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="submissions.last_page > 1" class="mt-2 flex items-center justify-between text-sm">
                    <p class="text-xs text-muted-foreground">Page {{ submissions.current_page }} of {{ submissions.last_page }}</p>
                    <div class="flex items-center gap-1">
                        <template v-for="link in submissions.links" :key="link.label">
                            <a
                                v-if="link.url"
                                :href="link.url"
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs transition-colors"
                                :class="link.active ? 'border-blue-600 bg-blue-600 text-white' : 'text-muted-foreground hover:bg-muted'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs text-muted-foreground opacity-40"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review dialog -->
        <Dialog :open="showReview" @update:open="showReview = $event">
            <DialogContent class="max-w-sm !rounded-2xl">
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

                <div class="flex justify-end gap-2 pt-2">
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
            <DialogContent class="max-w-sm !rounded-2xl">
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
                        <p v-if="attErrors.hours" class="text-xs text-red-500">{{ attErrors.hours }}</p>
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
                            class="text-xs file:mr-2 file:rounded-md file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                            @change="onAttFileChange"
                        />
                        <p v-if="attErrors.justification" class="text-xs text-red-500">{{ attErrors.justification }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" size="sm" @click="showAttendance = false">Cancel</Button>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" :disabled="attProcessing" @click="submitAttendance">
                        <LoaderCircle v-if="attProcessing" class="mr-1 h-3 w-3 animate-spin" />
                        <Save v-else class="h-3.5 w-3.5" />
                        Save Attendance
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

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
