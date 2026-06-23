<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import {
    Search, FileText, CalendarClock, Eye, StickyNote, MessageSquareText,
    AlarmClockMinus, Pencil, LoaderCircle, Save, CheckCircle2, XCircle, Clock3, FileX,
} from 'lucide-vue-next';
import MissingSubmissionsModal from '@/pages/programs/MissingSubmissionsModal.vue';

/* ===================== INTERFACES ===================== */

interface Employee {
    EMPCODE?: string;
    FIRSTNAME?: string;
    LASTNAME?: string;
    SURNAME?: string;
    FULLNAME?: string;
    fullname?: string;
    name?: string;
    [key: string]: any;
}

interface Participant {
    id: number;
    empcode: string;
    attendance: string;
    employee?: Employee | null;
    user_email?: string | null;
}

interface Batch {
    id: number;
    batch: string;
    participants?: Participant[];
    requirements?: { id: number; title: string; name: string; due_date: string }[];
}

interface Requirement {
    id: number;
    title: string;
    name: string;
    due_date: string;
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
}

interface Program {
    id: number;
    program_code: string;
    batches?: Batch[];
}

/* ===================== PROPS ===================== */

const props = defineProps<{
    program: Program;
    submissions: Submission[];
}>();

/* ===================== MISSING MODAL ===================== */

const showMissing = ref(false);
const missingModal = ref<{ missingCount: number } | null>(null);

// Badge count — mirrors the logic inside MissingSubmissionsModal
// (absent participants are excluded)
const missingCount = computed(() => {
    // Key: empcode + batch_id + requirement title
    // Dahil may cross-program requirement_id mismatch sa data,
    // ginagamit natin ang batch_id + title para ma-match nang tama.
    const submittedSet = new Set(
        props.submissions.map((s) => `${s.participant?.empcode ?? s.participant_id}__${s.batch_id}__${s.requirement?.title ?? ''}`)
    );
    let count = 0;
    for (const batch of props.program.batches ?? []) {
        for (const req of batch.requirements ?? []) {
            for (const p of batch.participants ?? []) {
                if (p.attendance?.toLowerCase() === 'absent') continue;
                if (!submittedSet.has(`${p.empcode}__${batch.id}__${req.title}`)) count++;
            }
        }
    }
    return count;
});

/* ===================== FILTERS ===================== */

const search       = ref('');
const filterBatch  = ref('all');
const filterStatus = ref('all');

const STATUSES = ['Pending', 'Approved', 'Rejected'];

const participantName = (s: Submission): string => {
    const emp = s.participant?.employee;
    if (!emp) return s.participant?.empcode ?? '—';
    const combined = [emp.FIRSTNAME ?? emp.firstname, emp.SURNAME ?? emp.surname].filter(Boolean).join(' ');
    return emp.FULLNAME ?? emp.fullname ?? emp.name ?? (combined || null) ?? s.participant?.empcode ?? '—';
};

const isLate = (s: Submission): boolean => {
    if (!s.submitted_at || !s.requirement?.due_date) return false;
    const due = new Date(s.requirement.due_date);
    due.setHours(23, 59, 59, 999);
    return new Date(s.submitted_at) > due;
};

const stats = computed(() => ({
    total:    props.submissions.length,
    approved: props.submissions.filter((s) => s.status.toLowerCase() === 'approved').length,
    rejected: props.submissions.filter((s) => s.status.toLowerCase() === 'rejected').length,
    pending:  props.submissions.filter((s) => s.status.toLowerCase() === 'pending').length,
}));

const filteredSubmissions = computed(() => {
    const q = search.value.toLowerCase().trim();
    return props.submissions.filter((s) => {
        if (filterBatch.value !== 'all' && String(s.batch_id) !== filterBatch.value) return false;
        if (filterStatus.value !== 'all' && s.status.toLowerCase() !== filterStatus.value.toLowerCase()) return false;
        if (q) {
            const hay = [
                participantName(s),
                s.participant?.empcode ?? '',
                s.requirement?.title ?? '',
                s.requirement?.name ?? '',
            ].join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });
});

/* ===================== DISPLAY HELPERS ===================== */

const formatDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

const formatDateTime = (d: string | null) =>
    d ? new Date(d).toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: 'numeric', minute: '2-digit',
    }) : '—';

const statusClass = (status: string) => {
    switch (status.toLowerCase()) {
        case 'approved': return 'bg-emerald-600/10 text-emerald-600 dark:text-emerald-400';
        case 'rejected': return 'bg-red-600/10 text-red-600 dark:text-red-400';
        default:         return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
    }
};

const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
const fileUrl    = (s: Submission) => s.file_path ? `/storage/${s.file_path}` : null;

/* ===================== REVIEW DIALOG ===================== */

const showReview       = ref(false);
const reviewTarget     = ref<Submission | null>(null);
const reviewStatus     = ref<'Approved' | 'Rejected'>('Approved');
const reviewRemarks    = ref('');
const reviewProcessing = ref(false);

const openReview = (s: Submission) => {
    reviewTarget.value  = s;
    reviewStatus.value  = s.status.toLowerCase() === 'rejected' ? 'Rejected' : 'Approved';
    reviewRemarks.value = s.remarks ?? '';
    showReview.value    = true;
};

const submitReview = () => {
    if (!reviewTarget.value) return;
    reviewProcessing.value = true;
    router.patch(
        route('submissions.review', reviewTarget.value.id),
        { status: reviewStatus.value, remarks: reviewRemarks.value },
        {
            preserveScroll: true,
            onSuccess: () => { showReview.value = false; reviewTarget.value = null; },
            onFinish:  () => { reviewProcessing.value = false; },
        }
    );
};
</script>

<template>
    <div class="flex flex-col gap-4">

        <!-- ── Header ── -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="font-bold">Submissions</h1>
                <p class="text-xs text-muted-foreground">All requirement submissions from participants of this program.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">

                <!-- Missing button with badge -->
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:border-red-300"
                    @click="showMissing = true"
                >
                    <FileX class="h-3.5 w-3.5 mr-1.5" />
                    Incomplete
                    <span
                        v-if="missingCount > 0"
                        class="ml-1.5 inline-flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold w-4 h-4 leading-none"
                    >
                        {{ missingCount > 99 ? '99+' : missingCount }}
                    </span>
                </Button>

                <!-- Search -->
                <div class="relative">
                    <Search class="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                    <Input v-model="search" class="text-xs h-8 w-52 pl-7" placeholder="Search name or requirement..." />
                </div>

                <!-- Batch filter -->
                <Select v-model="filterBatch">
                    <SelectTrigger class="text-xs h-8 w-36">
                        <SelectValue placeholder="All batches" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem class="text-xs" value="all">All batches</SelectItem>
                        <SelectItem v-for="b in program.batches ?? []" :key="b.id" :value="String(b.id)" class="text-xs">
                            {{ b.batch }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <!-- Status filter -->
                <Select v-model="filterStatus">
                    <SelectTrigger class="text-xs h-8 w-32">
                        <SelectValue placeholder="All status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem class="text-xs" value="all">All status</SelectItem>
                        <SelectItem v-for="st in STATUSES" :key="st" :value="st" class="text-xs">{{ st }}</SelectItem>
                    </SelectContent>
                </Select>

            </div>
        </div>

        <!-- ── Stats ── -->
        <div v-if="submissions.length" class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <div class="rounded-2xl border p-3 flex items-center gap-2.5">
                <div class="rounded-full bg-slate-100 dark:bg-slate-800 p-2">
                    <FileText class="h-4 w-4 text-slate-500" />
                </div>
                <div>
                    <p class="text-lg font-extrabold leading-none">{{ stats.total }}</p>
                    <p class="text-[11px] text-muted-foreground">Total</p>
                </div>
            </div>
            <div class="rounded-2xl border p-3 flex items-center gap-2.5">
                <div class="rounded-full bg-emerald-100 dark:bg-emerald-900/40 p-2">
                    <CheckCircle2 class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <p class="text-lg font-extrabold leading-none">{{ stats.approved }}</p>
                    <p class="text-[11px] text-muted-foreground">Approved</p>
                </div>
            </div>
            <div class="rounded-2xl border p-3 flex items-center gap-2.5">
                <div class="rounded-full bg-amber-100 dark:bg-amber-900/40 p-2">
                    <Clock3 class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <p class="text-lg font-extrabold leading-none">{{ stats.pending }}</p>
                    <p class="text-[11px] text-muted-foreground">Pending</p>
                </div>
            </div>
            <div class="rounded-2xl border p-3 flex items-center gap-2.5">
                <div class="rounded-full bg-red-100 dark:bg-red-900/40 p-2">
                    <XCircle class="h-4 w-4 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <p class="text-lg font-extrabold leading-none">{{ stats.rejected }}</p>
                    <p class="text-[11px] text-muted-foreground">Rejected</p>
                </div>
            </div>
        </div>

        <!-- ── Empty: no submissions at all ── -->
        <div v-if="!submissions.length"
            class="flex flex-col items-center justify-center py-16 px-6 text-center rounded-2xl border gap-3">
            <svg viewBox="0 0 200 160" class="h-40 w-auto" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="100" cy="142" rx="70" ry="8" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                <path d="M40 70 L70 30 H130 L160 70 V125 H40 Z" fill="currentColor" class="text-blue-100 dark:text-blue-900/40" />
                <path d="M40 70 H75 L85 85 H115 L125 70 H160 V125 H40 Z" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <path d="M70 30 H130 L150 65 H50 Z" fill="currentColor" class="text-blue-50 dark:text-blue-900/20" />
                <circle cx="148" cy="42" r="16" fill="currentColor" class="text-amber-100 dark:text-amber-900/40" />
                <path d="M141 42 h14 M148 35 v14" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" class="text-amber-500" />
            </svg>
            <p class="text-sm font-bold text-slate-500">No submissions yet</p>
            <p class="text-xs text-slate-400 max-w-xs">Submissions from participants will appear here once they submit their requirements.</p>
            <Button variant="outline" size="sm" class="mt-1 text-xs text-red-600 border-red-200 hover:bg-red-50" @click="showMissing = true">
                <FileX class="h-3.5 w-3.5 mr-1" /> View who hasn't completed yet
            </Button>
        </div>

        <!-- ── Empty: filter no match ── -->
        <div v-else-if="!filteredSubmissions.length"
            class="flex flex-col items-center justify-center py-16 px-6 text-center rounded-2xl border gap-3">
            <svg viewBox="0 0 200 160" class="h-36 w-auto" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="100" cy="142" rx="70" ry="8" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                <circle cx="90" cy="75" r="40" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                <circle cx="90" cy="75" r="40" stroke="currentColor" stroke-width="6" fill="none" class="text-blue-300 dark:text-blue-700/60" />
                <line x1="120" y1="105" x2="150" y2="135" stroke="currentColor" stroke-width="8" stroke-linecap="round" class="text-blue-300 dark:text-blue-700/60" />
                <path d="M75 75 h30 M90 60 v30" stroke="currentColor" stroke-width="5" stroke-linecap="round" class="text-slate-300 dark:text-slate-700" />
            </svg>
            <p class="text-sm font-bold text-slate-500">No submissions match your filters</p>
            <p class="text-xs text-slate-400 max-w-xs">Try a different search term, batch, or status.</p>
        </div>

        <!-- ── Submission list ── -->
        <div v-else class="flex flex-col gap-2">
            <p class="text-[11px] text-muted-foreground">
                Showing <span class="font-semibold">{{ filteredSubmissions.length }}</span> of {{ submissions.length }} submission(s)
            </p>

            <div
                v-for="s in filteredSubmissions"
                :key="s.id"
                class="rounded-2xl border px-4 py-3 shadow-sm flex flex-wrap items-start justify-between gap-3"
            >
                <div class="flex flex-col gap-1 min-w-0">
                    <p class="text-sm font-bold leading-snug">
                        {{ participantName(s) }}
                        <span class="font-normal text-xs text-muted-foreground">({{ s.participant?.empcode }})</span>
                    </p>
                    <p class="text-xs text-muted-foreground flex items-center gap-1 flex-wrap">
                        <FileText class="h-3.5 w-3.5" />
                        <span class="font-semibold text-foreground">{{ s.requirement?.title }}</span>
                        <span v-if="s.requirement?.name">— {{ s.requirement?.name }}</span>
                        <span class="mx-1">·</span>
                        <span>{{ s.batch?.batch }}</span>
                    </p>
                    <p class="text-xs text-muted-foreground flex items-center gap-1 flex-wrap">
                        <CalendarClock class="h-3.5 w-3.5" />
                        Submitted: <span class="font-semibold text-foreground">{{ formatDateTime(s.submitted_at) }}</span>
                        <template v-if="s.requirement?.due_date">
                            <span class="mx-1">·</span>
                            Due: {{ formatDate(s.requirement.due_date) }}
                        </template>
                    </p>
                    <p v-if="s.notes" class="text-xs text-muted-foreground flex items-start gap-1 mt-0.5">
                        <StickyNote class="h-3.5 w-3.5 shrink-0 mt-0.5" />
                        <span class="leading-snug">{{ s.notes }}</span>
                    </p>
                    <p v-if="s.remarks" class="text-xs text-muted-foreground flex items-start gap-1 mt-0.5">
                        <MessageSquareText class="h-3.5 w-3.5 shrink-0 mt-0.5" />
                        <span class="leading-snug">
                            <span class="font-semibold">Remarks:</span> {{ s.remarks }}
                            <span v-if="s.reviewed_by"> — {{ s.reviewed_by }}</span>
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span v-if="isLate(s)"
                        class="inline-flex items-center gap-1 rounded-full bg-red-600/10 text-red-600 dark:text-red-400 px-2 py-0.5 text-[11px] font-bold">
                        <AlarmClockMinus class="h-3 w-3" /> Late
                    </span>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold" :class="statusClass(s.status)">
                        {{ capitalize(s.status) }}
                    </span>
                    <a v-if="fileUrl(s)" :href="fileUrl(s)!" target="_blank" rel="noopener noreferrer">
                        <Button variant="outline" size="sm" class="h-7 text-xs">
                            <Eye class="h-3.5 w-3.5 mr-1" /> View PDF
                        </Button>
                    </a>
                    <Button variant="outline" size="sm" class="h-7 text-xs" @click="openReview(s)">
                        <Pencil class="h-3.5 w-3.5 mr-1" /> Review
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── Review dialog ── -->
        <Dialog :open="showReview" @update:open="showReview = $event">
            <DialogContent class="max-w-sm !rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex gap-2 items-center">
                        <Pencil class="h-5 w-5 text-blue-600" /> Review Submission
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        {{ reviewTarget ? participantName(reviewTarget) : '' }} — {{ reviewTarget?.requirement?.title }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 py-1">
                    <div class="grid gap-1">
                        <Label class="text-xs">Status <span class="text-red-500">*</span></Label>
                        <Select v-model="reviewStatus">
                            <SelectTrigger class="text-xs h-8">
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
                        <Input class="text-xs h-8" v-model="reviewRemarks" placeholder="e.g. Needs revision on section 3" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" size="sm" @click="showReview = false">Cancel</Button>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" :disabled="reviewProcessing" @click="submitReview">
                        <LoaderCircle v-if="reviewProcessing" class="h-3 w-3 animate-spin mr-1" />
                        <Save v-else class="h-3.5 w-3.5" />
                        Save Review
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- ── Missing submissions modal ── -->
        <MissingSubmissionsModal
            ref="missingModal"
            :open="showMissing"
            :program="program"
            :submissions="submissions"
            @update:open="showMissing = $event"
        />

    </div>
</template>