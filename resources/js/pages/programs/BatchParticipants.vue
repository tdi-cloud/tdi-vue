<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import SearchEmptyState from '@/components/SearchEmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/components/ui/toast/use-toast';
import { useConfirm } from '@/composables/useConfirm';
import { suggestionFrom } from '@/lib/http';
import { suggestSearch } from '@/lib/searchSuggestion';
import BulkAddParticipants from '@/pages/programs/BulkAddParticipants.vue';
import BulkSubmissionUpload from '@/pages/programs/BulkSubmissionUpload.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    ClipboardCheck,
    ClipboardList,
    ClipboardPaste,
    Clock,
    CloudUpload,
    Download,
    ExternalLink,
    Eye,
    FileImage,
    FileText,
    FileX,
    History,
    Info,
    ListFilter,
    LoaderCircle,
    Paperclip,
    Save,
    Search,
    Trash2,
    TriangleAlert,
    Upload,
    UserPlus,
    UserRound,
    Users,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const { confirmDialog } = useConfirm();
const { toast } = useToast();

interface EmployeeOption {
    empcode: string;
    name: string;
}

const props = defineProps<{
    open: boolean;
    batch: any | null;
    program?: any | null;
    initialQuery?: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const query = ref('');
const results = ref<EmployeeOption[]>([]);
const searching = ref(false);
const showDropdown = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const selected = ref<EmployeeOption[]>([]);
const processing = ref(false);

// Participants being removed are hidden immediately (optimistic) so the deletion feels instant
const removingIds = ref(new Set<number>());

const participants = computed(() =>
    [...(props.batch?.participants ?? [])]
        .filter((p) => !removingIds.value.has(p.id))
        .sort((a, b) => {
            if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
            return a.id - b.id; // tiebreaker
        }),
);
const requirements = computed(() => props.batch?.requirements ?? []);

const listQuery = ref('');
const page = ref(1);
const perPage = 10;

const filteredParticipants = computed(() => {
    const q = listQuery.value.trim().toLowerCase();
    if (!q) return participants.value;

    return participants.value.filter((p: any) => {
        const name = (p.employee?.name ?? '').toLowerCase();
        const empcode = (p.empcode ?? '').toLowerCase();
        return name.includes(q) || empcode.includes(q);
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredParticipants.value.length / perPage)));

const paginatedParticipants = computed(() => {
    const start = (page.value - 1) * perPage;
    return filteredParticipants.value.slice(start, start + perPage);
});

const rowNumber = (i: number) => (page.value - 1) * perPage + i + 1;

watch(listQuery, () => {
    page.value = 1;
});

watch(totalPages, (val) => {
    if (page.value > val) page.value = val;
});

/* ---------- Set Attendance dialog ---------- */
const showAttendance = ref(false);
const attendanceTarget = ref<any | null>(null);
const attStatus = ref<'Pending' | 'Complete' | 'Absent'>('Pending');
const attHours = ref('');
const attFile = ref<File | null>(null);
const attProcessing = ref(false);
const attErrors = ref<{ hours?: string; justification?: string }>({});

const batchHours = computed(() => Number(props.batch?.hours ?? 0));

const openAttendance = (p: any) => {
    attendanceTarget.value = p;

    const current = ['Pending', 'Complete', 'Absent'].includes(p.attendance) ? p.attendance : 'Pending';
    attStatus.value = current as any;

    attHours.value = current === 'Complete' && p.hours ? String(p.hours) : String(batchHours.value || '');

    attFile.value = null;
    attErrors.value = {};
    isDraggingMemo.value = false;
    showAttendance.value = true;
};

const MEMO_MAX_BYTES = 5 * 1024 * 1024;
const memoInput = ref<HTMLInputElement | null>(null);
const isDraggingMemo = ref(false);

const pickMemoFile = (file: File | null) => {
    attErrors.value.justification = undefined;

    if (file && file.size > MEMO_MAX_BYTES) {
        attFile.value = null;
        attErrors.value.justification = `"${file.name}" is ${formatFileSize(file.size)}. The memo must be 5MB or smaller.`;
        return;
    }

    attFile.value = file;
};

const onFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    pickMemoFile(input.files?.[0] ?? null);
};

const onMemoDrop = (e: DragEvent) => {
    isDraggingMemo.value = false;
    pickMemoFile(e.dataTransfer?.files?.[0] ?? null);
};

const clearPickedMemo = () => {
    attFile.value = null;
    if (memoInput.value) memoInput.value.value = '';
};

const selectAttendance = (value: 'Pending' | 'Complete' | 'Absent') => {
    attStatus.value = value;
    attErrors.value = {};
};

const attendanceOptions = [
    {
        value: 'Pending' as const,
        label: 'Pending',
        hint: 'Not yet confirmed',
        icon: Clock,
        activeClass: 'border-amber-400 bg-amber-50 ring-2 ring-amber-400/30 dark:border-amber-500/70 dark:bg-amber-500/10',
        iconClass: 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
    },
    {
        value: 'Complete' as const,
        label: 'Complete',
        hint: 'Attended, earns hours',
        icon: CheckCircle2,
        activeClass: 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-400/30 dark:border-emerald-500/70 dark:bg-emerald-500/10',
        iconClass: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
    },
    {
        value: 'Absent' as const,
        label: 'Absent',
        hint: 'Needs a memo',
        icon: XCircle,
        activeClass: 'border-red-400 bg-red-50 ring-2 ring-red-400/30 dark:border-red-500/70 dark:bg-red-500/10',
        iconClass: 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-300',
    },
];

const memoOnFile = computed(() => attendanceTarget.value?.justification ?? null);

const fileExtension = (name: string | null | undefined) => (name?.split('.').pop() ?? '').toLowerCase();

const memoFileName = computed(() => {
    const memo = memoOnFile.value;
    if (!memo) return '';
    return memo.original_name ?? `Justification memo.${fileExtension(memo.file_path)}`;
});

const memoExtension = computed(() => fileExtension(memoOnFile.value?.original_name ?? memoOnFile.value?.file_path));

const isImageExtension = (ext: string) => ['jpg', 'jpeg', 'png'].includes(ext);

const memoUploader = computed(() => {
    const memo = memoOnFile.value;
    if (!memo?.uploaded_by_name) return null;
    return {
        name: memo.uploaded_by_name as string,
        empcode: memo.uploaded_by_empcode as string | null,
        isSelfSubmitted: memo.uploaded_by_empcode === attendanceTarget.value?.empcode,
    };
});

/** A memo counts as replaced when its record changed well after it was first filed. */
const memoWasReplaced = computed(() => {
    const memo = memoOnFile.value;
    if (!memo?.created_at || !memo?.updated_at) return false;
    return new Date(memo.updated_at).getTime() - new Date(memo.created_at).getTime() > 60_000;
});

/** Saving as Pending or Complete makes the server delete the memo on file. */
const willDeleteMemo = computed(() => !!memoOnFile.value && attStatus.value !== 'Absent');

const hasUnsavedStatusChange = computed(() => !!attendanceTarget.value && attendanceTarget.value.attendance !== attStatus.value);

const initialsOf = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

const formatFileSize = (bytes: number | null | undefined) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDateTime = (d: string | null | undefined) => {
    if (!d) return '—';
    return new Date(d).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const timeAgo = (d: string | null | undefined) => {
    if (!d) return '';
    const seconds = Math.round((new Date(d).getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit);
    }
    return 'just now';
};

const batchDateRange = computed(() => {
    const start = props.batch?.date_start;
    const end = props.batch?.date_end;
    if (!start) return '';
    const fmt = (d: string) => new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    return end && end !== start ? `${fmt(start)} – ${fmt(end)}` : fmt(start);
});

const deletingJustification = ref(false);

const deleteJustificationMemo = async () => {
    if (!attendanceTarget.value) return;
    if (!(await confirmDialog('Remove the uploaded justification memo?', { confirmText: 'Remove' }))) return;

    const participantId = attendanceTarget.value.id;
    deletingJustification.value = true;
    router.delete(route('participants.justification.destroy', participantId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const updated = participants.value.find((p: any) => p.id === participantId);
            if (updated) {
                attendanceTarget.value = updated;
                attStatus.value = ['Pending', 'Complete', 'Absent'].includes(updated.attendance) ? updated.attendance : 'Pending';
            }
            clearPickedMemo();
            attErrors.value = {};
        },
        onFinish: () => {
            deletingJustification.value = false;
        },
    });
};

const submitAttendance = () => {
    if (!attendanceTarget.value) return;
    attErrors.value = {};

    if (attStatus.value === 'Complete') {
        const h = Number(attHours.value);
        if (!attHours.value || isNaN(h) || h <= 0) {
            attErrors.value.hours = 'Please enter the completed hours.';
            return;
        }
    }

    if (attStatus.value === 'Absent' && !attFile.value && !attendanceTarget.value.justification) {
        attErrors.value.justification = 'Please upload the justification memo for the absence.';
        return;
    }

    attProcessing.value = true;
    router.post(
        route('participants.attendance', attendanceTarget.value.id),
        {
            attendance: attStatus.value,
            hours: attStatus.value === 'Complete' ? attHours.value : 0,
            justification: attStatus.value === 'Absent' ? attFile.value : null,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                // Keep attendanceTarget so the dialog body doesn't collapse during the close animation.
                showAttendance.value = false;
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

/* ---------- Submissions dialog ---------- */
const showSubmissions = ref(false);
const submissionsTargetId = ref<number | null>(null);

const submissionsTarget = computed(() => {
    if (!submissionsTargetId.value) return null;
    return participants.value.find((p: any) => p.id === submissionsTargetId.value) ?? null;
});

/* ---------- Submission file preview ---------- */
const showFilePreview = ref(false);
const previewFile = ref<{ url: string; title?: string; description?: string } | null>(null);

const openFilePreview = (filePath: string, title?: string, description?: string) => {
    previewFile.value = { url: `/storage/${filePath}`, title, description };
    showFilePreview.value = true;
};

const mergedSubmissions = computed(() => {
    if (!submissionsTarget.value) return [];
    const existing = submissionsTarget.value.submissions ?? [];

    return requirements.value.map((req: any) => {
        const sub = existing.find((s: any) => s.requirement_id === req.id);
        return {
            requirement: req,
            submission: sub ?? null,
        };
    });
});

const openSubmissions = (p: any) => {
    submissionsTargetId.value = p.id;
    showSubmissions.value = true;
};

const normalizeStatus = (status: string | null | undefined): 'Pending' | 'Approved' | 'Rejected' => {
    // Walang existing submission pa (bagong entry) — "Approved" na ang default
    // sa halip na "Pending", para hindi na kailangang palitan pa manu-mano
    // kada bagong submission.
    if (!status) return 'Approved';

    const s = status.toLowerCase();
    if (s === 'rejected') return 'Rejected';
    if (s === 'pending') return 'Pending';
    return 'Approved';
};

const editingRow = ref<number | null>(null);
const subStatus = ref<'Pending' | 'Approved' | 'Rejected'>('Approved');
const subRemarks = ref('');
const subFile = ref<File | null>(null);
const subProcessing = ref(false);
const subErrors = ref<{ file?: string; status?: string }>({});

const startEdit = (row: any) => {
    editingRow.value = row.requirement.id;
    subStatus.value = normalizeStatus(row.submission?.status);
    subRemarks.value = row.submission?.remarks ?? '';
    subFile.value = null;
    subErrors.value = {};
};

const cancelEdit = () => {
    editingRow.value = null;
    subFile.value = null;
    subErrors.value = {};
};

const onSubFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    subFile.value = input.files?.[0] ?? null;
};

const saveSubmission = (row: any) => {
    if (!submissionsTarget.value || !props.batch) return;

    subProcessing.value = true;
    router.post(
        route('submissions.store'),
        {
            participant_id: submissionsTarget.value.id,
            batch_id: props.batch.id,
            requirement_id: row.requirement.id,
            program_code: props.batch.program_code,
            status: subStatus.value,
            file: subFile.value,
            remarks: subRemarks.value,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                editingRow.value = null;
                subFile.value = null;
            },
            onError: (errors) => {
                subErrors.value = errors as any;
            },
            onFinish: () => {
                subProcessing.value = false;
            },
        },
    );
};

const deleteSubmission = async (submissionId: number) => {
    if (!(await confirmDialog('Delete this submission file/record?'))) return;
    router.delete(route('submissions.destroy', submissionId), {
        preserveScroll: true,
        onSuccess: () => {
            showSubmissions.value = false;
            submissionsTargetId.value = null;
            editingRow.value = null;
        },
    });
};

/* ---------- Other documents (submissions without a requirement) ---------- */
const otherDocs = computed(() => (submissionsTarget.value?.submissions ?? []).filter((s: any) => !s.requirement_id));

// Only submissions tied to a requirement count toward the X/Y progress badge
const requirementSubmissionCount = (p: any) => (p.submissions ?? []).filter((s: any) => s.requirement_id).length;

const docFormOpen = ref(false);
const docEditingId = ref<number | null>(null);
const docTitle = ref('');
const docStatus = ref<'Pending' | 'Approved' | 'Rejected'>('Approved');
const docRemarks = ref('');
const docFile = ref<File | null>(null);
const docProcessing = ref(false);
const docErrors = ref<{ document_title?: string; file?: string }>({});

const openDocForm = (doc: any = null) => {
    docEditingId.value = doc?.id ?? null;
    docTitle.value = doc?.document_title ?? '';
    docStatus.value = normalizeStatus(doc?.status);
    docRemarks.value = doc?.remarks ?? '';
    docFile.value = null;
    docErrors.value = {};
    docFormOpen.value = true;
};

const closeDocForm = () => {
    docFormOpen.value = false;
    docEditingId.value = null;
    docFile.value = null;
    docErrors.value = {};
};

const onDocFileChange = (e: Event) => {
    docFile.value = (e.target as HTMLInputElement).files?.[0] ?? null;
};

const saveOtherDoc = () => {
    if (!submissionsTarget.value || !props.batch) return;

    docProcessing.value = true;
    router.post(
        route('submissions.store'),
        {
            participant_id: submissionsTarget.value.id,
            batch_id: props.batch.id,
            program_code: props.batch.program_code,
            submission_id: docEditingId.value,
            document_title: docTitle.value,
            status: docStatus.value,
            file: docFile.value,
            remarks: docRemarks.value,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeDocForm(),
            onError: (errors) => {
                docErrors.value = errors as any;
            },
            onFinish: () => {
                docProcessing.value = false;
            },
        },
    );
};

/* ---------- Helpers ---------- */
const attendanceColor = (status: string) => {
    switch (status) {
        case 'Complete':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'Absent':
            return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
        case 'Pending':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    }
};

const formatDueDate = (d: string) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

/* ---------- CSV Export ---------- */
const exportCsv = () => {
    const b = props.batch;
    if (!b) return;

    const reqs = requirements.value;
    const rows: string[] = [];

    const esc = (v: any) => {
        const s = v === null || v === undefined ? '' : String(v);
        return `"${s.replace(/"/g, '""')}"`;
    };
    const line = (cells: any[]) => rows.push(cells.map(esc).join(','));

    const schedule = `${formatDueDate(b.date_start)} - ${formatDueDate(b.date_end)}`;
    const generated = new Date().toLocaleString('en-PH');

    // ── Single header row (lahat ng column sa isang linya) ──
    const header = [
        'Program Code',
        'Program Title',
        'Batch',
        'Status',
        'Modality',
        'Venue',
        'Schedule',
        'Total Hours',
        '#',
        'Employee Name',
        'Employee Code',
        'Position',
        'Office',
        'Attendance',
        'Hours',
    ];
    for (const r of reqs) header.push(r.title ?? r.name ?? `Req ${r.id}`);
    header.push('Generated');
    line(header);

    // ── Participant rows (inuulit ang program/batch info kada row) ──
    participants.value.forEach((p: any, i: number) => {
        const subMap: Record<number, string> = {};
        for (const s of p.submissions ?? []) {
            subMap[s.requirement_id] = normalizeStatus(s.status);
        }

        const row: any[] = [
            b.program_code ?? '',
            props.program?.title ?? '',
            b.batch ?? '',
            b.status ?? '',
            b.modality ?? '',
            b.venue ?? '',
            schedule,
            b.hours ?? '',
            i + 1,
            p.employee?.name ?? p.empcode,
            p.empcode,
            p.employee?.POSITION ?? '',
            p.employee?.['OFFICE/DIVISION'] ?? p.employee?.OFFICE ?? '',
            p.attendance ?? '',
            p.attendance === 'Complete' ? (p.hours ?? '') : '',
        ];

        for (const r of reqs) {
            row.push(subMap[r.id] ?? 'No submission');
        }

        row.push(generated);
        line(row);
    });

    // ── Download ──
    const csv = rows.join('\r\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const safeBatch = (b.batch ?? 'batch').replace(/[^a-z0-9]+/gi, '_');
    a.download = `${b.program_code ?? 'program'}_${safeBatch}_participants.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            query.value = '';
            results.value = [];
            selected.value = [];
            showDropdown.value = false;
            listQuery.value = props.initialQuery ?? '';
            page.value = 1;
            showSubmissions.value = false;
            submissionsTargetId.value = null;
            editingRow.value = null;
        }
    },
);

// "Did you mean …?" for the employee lookup (X-Search-Suggestion header).
const lookupSuggestion = ref<string | null>(null);

watch(query, (val) => {
    if (debounceTimer) clearTimeout(debounceTimer);

    if (!val || val.length < 2) {
        results.value = [];
        showDropdown.value = false;
        return;
    }

    debounceTimer = setTimeout(async () => {
        searching.value = true;
        try {
            const url = route('participants.search', {
                q: val,
                batch_id: props.batch?.id,
            });
            const response = await axios.get<EmployeeOption[]>(url);
            const data = response.data;
            lookupSuggestion.value = suggestionFrom(response);

            results.value = data.filter((emp) => !selected.value.some((s) => s.empcode === emp.empcode));
            showDropdown.value = true;
        } catch {
            results.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
});

const selectEmployee = (emp: EmployeeOption) => {
    selected.value.push(emp);
    results.value = results.value.filter((r) => r.empcode !== emp.empcode);
    query.value = '';
    showDropdown.value = false;
};

const removeSelected = (empcode: string) => {
    selected.value = selected.value.filter((s) => s.empcode !== empcode);
};

const addParticipants = () => {
    if (!selected.value.length || !props.batch) return;

    processing.value = true;
    router.post(
        route('participants.store'),
        {
            batch_id: props.batch.id,
            empcodes: selected.value.map((s) => s.empcode),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const removeParticipant = async (participant: any) => {
    const label = participant.employee?.name ?? participant.empcode;
    if (!(await confirmDialog(`Remove ${label} from this batch?`))) return;

    removingIds.value.add(participant.id);

    router.delete(route('participants.destroy', participant.id), {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            toast({
                title: 'Something went wrong',
                description: `Could not remove ${label}. Your session may have expired — try refreshing the page.`,
                variant: 'destructive',
            });
        },
        onFinish: () => {
            // On success the row is gone from props; on failure it reappears in the list
            removingIds.value.delete(participant.id);
        },
    });
};

const reordering = ref<number | null>(null);

const reorder = (participant: any, direction: 'up' | 'down') => {
    reordering.value = participant.id;
    router.post(
        route('participants.reorder', participant.id),
        { direction },
        {
            preserveScroll: true,
            onFinish: () => {
                reordering.value = null;
            },
        },
    );
};

const showBulkAdd = ref(false);
const showBulkSubmissionUpload = ref(false);

const clearingAll = ref(false);

const clearAllParticipants = async () => {
    if (!participants.value.length) return;

    const count = participants.value.length;
    if (!(await confirmDialog(`Remove ALL ${count} participant(s) from this batch? This cannot be undone.`, { confirmText: 'Remove All' }))) return;

    clearingAll.value = true;

    const deleteOne = (index: number) => {
        if (index >= participants.value.length) {
            clearingAll.value = false;
            return;
        }

        const participant = participants.value[index];
        const countBefore = participants.value.length;

        router.delete(route('participants.destroy', participant.id), {
            preserveScroll: true,
            onError: () => {
                clearingAll.value = false;
                toast({
                    title: 'Something went wrong',
                    description: 'Removing participants was interrupted. Your session may have expired — try refreshing the page.',
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                // Kung hindi humikpo ang list (nag-fail ang request), itigil na
                // sa halip na mag-retry nang walang hanggan sa parehong participant.
                if (participants.value.length < countBefore) {
                    deleteOne(0);
                }
            },
        });
    };

    deleteOne(0);
};

const applyToAll = async () => {
    if (!attendanceTarget.value) return;
    attErrors.value = {};

    const h = Number(attHours.value);
    if (!attHours.value || isNaN(h) || h <= 0) {
        attErrors.value.hours = 'Please enter the completed hours.';
        return;
    }

    const eligibleCount = participants.value.filter((p: any) => p.attendance !== 'Absent').length;

    if (
        !(await confirmDialog(
            `Apply ${h} hr(s) / Complete to all ${eligibleCount} participant(s) in this batch? ` + `Absent participants will be skipped.`,
            { confirmText: 'Apply', variant: 'default' },
        ))
    )
        return;

    attProcessing.value = true;
    router.post(
        route('participants.applyToAll', attendanceTarget.value.id),
        { attendance: 'Complete', hours: attHours.value },
        {
            preserveScroll: true,
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

// Icon + kulay para sa bawat submission state
const statusMeta = (status: string | null | undefined, hasSubmission: boolean) => {
    if (!hasSubmission) {
        return {
            label: 'No submission',
            icon: FileX,
            badge: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
            ring: 'border-slate-200 dark:border-slate-700',
        };
    }
    switch (normalizeStatus(status)) {
        case 'Approved':
            return {
                label: 'Approved',
                icon: CheckCircle2,
                badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                ring: 'border-emerald-200 dark:border-emerald-800',
            };
        case 'Rejected':
            return {
                label: 'Rejected',
                icon: XCircle,
                badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                ring: 'border-red-200 dark:border-red-800',
            };
        default:
            return {
                label: 'Pending',
                icon: Clock,
                badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                ring: 'border-amber-200 dark:border-amber-800',
            };
    }
};

// Buod ng approved/total para sa header
const submissionSummary = computed(() => {
    if (!submissionsTarget.value) return { done: 0, total: 0 };
    const subs = submissionsTarget.value.submissions ?? [];
    const approved = subs.filter((s: any) => normalizeStatus(s.status) === 'Approved').length;
    return { done: approved, total: requirements.value.length };
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex max-h-[90vh] max-w-4xl flex-col gap-0 overflow-hidden !rounded-2xl p-0">
            <!-- ── Modal Header ── -->
            <div class="flex shrink-0 items-center gap-3 border-b px-6 py-4">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600">
                    <Users class="h-4 w-4 text-white" />
                </div>
                <div>
                    <h2 class="text-sm font-bold leading-tight">{{ batch?.batch }} — Participants</h2>
                    <p class="text-[11px] text-muted-foreground">Manage participants enrolled in this batch</p>
                </div>

                <Button
                    v-if="participants.length"
                    variant="outline"
                    size="sm"
                    class="ml-auto mr-5 border-emerald-200 text-xs text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                    @click="exportCsv"
                >
                    <Download class="mr-1 h-3.5 w-3.5" />
                    Export CSV
                </Button>
            </div>

            <div class="flex flex-1 flex-col gap-0 overflow-y-auto">
                <!-- ══════════════════════════════════════════
                     SECTION 1 — ADD PARTICIPANTS
                ══════════════════════════════════════════ -->
                <div class="flex flex-col gap-3 border-b bg-blue-50 px-6 py-4 dark:bg-blue-950/30">
                    <!-- Section title -->
                    <div class="flex items-center gap-2">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-blue-600">
                            <UserPlus class="h-3.5 w-3.5 text-white" />
                        </div>
                        <div>
                            <p class="text-xs font-bold leading-none text-blue-900 dark:text-blue-100">Add Participants</p>
                            <p class="mt-0.5 text-[11px] text-blue-600 dark:text-blue-400">Search employees or paste a list of employee codes</p>
                        </div>
                    </div>

                    <!-- Search input -->
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-blue-400" />
                        <Input
                            v-model="query"
                            class="h-9 border-blue-200 bg-white pl-8 text-xs focus-visible:ring-blue-400 dark:border-blue-800 dark:bg-blue-950/50"
                            placeholder="Search employee by name or empcode..."
                        />
                        <LoaderCircle v-if="searching" class="absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 animate-spin text-blue-400" />

                        <!-- Dropdown results -->
                        <div
                            v-if="showDropdown && results.length"
                            class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-blue-100 bg-white shadow-lg dark:bg-slate-900"
                        >
                            <button
                                v-for="emp in results"
                                :key="emp.empcode"
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-left text-xs transition-colors hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                @click="selectEmployee(emp)"
                            >
                                <span class="font-semibold">{{ emp.name }}</span>
                                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] text-muted-foreground dark:bg-slate-800">{{
                                    emp.empcode
                                }}</span>
                            </button>
                        </div>
                        <div
                            v-else-if="showDropdown && !searching && query.length >= 2"
                            class="absolute z-50 mt-1 w-full rounded-xl border bg-white shadow-md dark:bg-slate-900"
                        >
                            <SearchEmptyState
                                compact
                                :query="query"
                                :suggestion="lookupSuggestion"
                                @search="query = $event"
                                @clear-filters="query = ''"
                            />
                        </div>
                    </div>

                    <!-- Selected tags -->
                    <div v-if="selected.length" class="flex flex-wrap gap-1.5">
                        <Badge
                            v-for="emp in selected"
                            :key="emp.empcode"
                            class="gap-1 border-blue-200 bg-blue-100 py-1 pl-2 pr-1 text-[11px] font-semibold text-blue-800 hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-900/60 dark:text-blue-200"
                        >
                            {{ emp.name }}
                            <button
                                type="button"
                                class="rounded-full p-0.5 transition-colors hover:bg-blue-200 dark:hover:bg-blue-800"
                                @click="removeSelected(emp.empcode)"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex items-center justify-between gap-2">
                        <!-- Stats pill -->
                        <div v-if="selected.length" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400">
                            {{ selected.length }} employee{{ selected.length > 1 ? 's' : '' }} selected
                        </div>
                        <div v-else class="text-[11px] italic text-blue-400 dark:text-blue-500">Select employees above to add them</div>

                        <div class="ml-auto flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="border-blue-200 bg-white text-xs text-blue-700 hover:bg-blue-100 dark:border-blue-700 dark:bg-transparent dark:text-blue-300 dark:hover:bg-blue-900/40"
                                @click="showBulkAdd = true"
                            >
                                <ClipboardPaste class="mr-1 h-3.5 w-3.5" />
                                Bulk Add
                            </Button>
                            <Button
                                size="sm"
                                class="bg-blue-600 text-xs font-bold hover:bg-blue-700 dark:text-white"
                                :disabled="!selected.length || processing"
                                @click="addParticipants"
                            >
                                <LoaderCircle v-if="processing" class="mr-1 h-3 w-3 animate-spin" />
                                <UserPlus v-else class="mr-1 h-3.5 w-3.5" />
                                Add{{
                                    selected.length > 1
                                        ? ` ${selected.length} Participants`
                                        : selected.length === 1
                                          ? ' Participant'
                                          : ' Participants'
                                }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════
                     SECTION 2 — ENROLLED PARTICIPANTS LIST
                ══════════════════════════════════════════ -->
                <div class="flex flex-col gap-3 px-6 py-4">
                    <!-- Section title -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-600">
                                <Users class="h-3.5 w-3.5 text-white" />
                            </div>
                            <div>
                                <p class="text-xs font-bold leading-none text-slate-700 dark:text-slate-200">
                                    Enrolled Participants
                                    <span class="ml-1.5 text-[11px] font-normal text-muted-foreground">({{ participants.length }} total)</span>
                                </p>
                                <p class="mt-0.5 text-[11px] text-muted-foreground">Manage attendance, submissions, and order</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                v-if="participants.length && requirements.length"
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs"
                                @click="showBulkSubmissionUpload = true"
                            >
                                <CloudUpload class="mr-1 h-3.5 w-3.5" /> Bulk Upload Submissions
                            </Button>

                            <!-- Search -->
                            <div v-if="participants.length" class="relative w-52">
                                <Search class="absolute left-2 top-1/2 h-3 w-3 -translate-y-1/2 text-muted-foreground" />
                                <Input v-model="listQuery" class="h-7 pl-6 text-xs" placeholder="Search enrolled..." />
                            </div>
                        </div>
                    </div>

                    <!-- Empty: no participants -->
                    <EmptyState
                        v-if="!participants.length"
                        :icon="Users"
                        title="No participants yet"
                        description='Use the search above to find and add employees, or use "Bulk Add" to paste a list of employee codes.'
                    />

                    <!-- Empty: filter no match -->
                    <SearchEmptyState
                        v-else-if="!filteredParticipants.length"
                        compact
                        :query="listQuery"
                        :suggestion="
                            suggestSearch(
                                listQuery,
                                participants.flatMap((p: any) => [p.employee?.name, p.empcode]),
                            )
                        "
                        class="rounded-2xl border border-dashed"
                        @search="listQuery = $event"
                        @clear-filters="listQuery = ''"
                    />

                    <!-- Participant list -->
                    <template v-else>
                        <div class="divide-y overflow-hidden rounded-xl border shadow-md">
                            <!-- List header -->
                            <div class="grid grid-cols-[2rem_1fr_auto] items-center gap-2 border-b bg-muted/40 px-3 py-1.5">
                                <span class="text-center text-[10px] font-bold uppercase tracking-wide text-muted-foreground">#</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground">Employee</span>
                                <span class="pr-1 text-right text-[10px] font-bold uppercase tracking-wide text-muted-foreground">Actions</span>
                            </div>

                            <div
                                v-for="(p, i) in paginatedParticipants"
                                :key="p.id"
                                class="flex items-center justify-between px-3 py-2.5 transition-colors hover:bg-muted/20"
                            >
                                <!-- Left: number + name -->
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <!-- Row number -->
                                    <span class="w-6 shrink-0 text-right text-[11px] font-bold text-slate-400"> {{ rowNumber(i) }}. </span>

                                    <!-- Avatar circle -->
                                    <div
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                                    >
                                        {{
                                            (p.employee?.name ?? p.empcode)
                                                .split(' ')
                                                .map((n: string) => n[0])
                                                .slice(0, 2)
                                                .join('')
                                                .toUpperCase()
                                        }}
                                    </div>

                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-bold leading-4">
                                            {{ p.employee?.name ?? p.empcode }}
                                        </p>
                                        <p class="truncate text-[11px] text-muted-foreground">
                                            <span v-if="p.employee?.['OFFICE/DIVISION'] ?? p.employee?.OFFICE">
                                                {{ p.employee?.['OFFICE/DIVISION'] ?? p.employee?.OFFICE }} ·
                                            </span>
                                            {{ p.empcode }}
                                            <span v-if="p.attendance === 'Complete'" class="font-semibold text-emerald-600 dark:text-emerald-400">
                                                · {{ p.hours }} hr/s
                                            </span>
                                        </p>
                                        <a
                                            v-if="p.attendance === 'Absent' && p.justification"
                                            :href="`/storage/${p.justification.file_path}`"
                                            target="_blank"
                                            class="inline-flex items-center gap-0.5 text-[11px] font-semibold text-blue-600 hover:underline"
                                        >
                                            <FileText class="h-3 w-3" /> View memo
                                        </a>
                                    </div>
                                </div>

                                <!-- Right: actions -->
                                <div class="flex shrink-0 items-center gap-2.5">
                                    <!-- Reorder -->
                                    <div class="flex gap-0.5">
                                        <button
                                            type="button"
                                            :disabled="reordering === p.id || rowNumber(i) === 1"
                                            class="rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-slate-800"
                                            title="Move up"
                                            @click="reorder(p, 'up')"
                                        >
                                            <ChevronUp class="h-5 w-5" />
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="reordering === p.id || rowNumber(i) === filteredParticipants.length"
                                            class="rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-slate-800"
                                            title="Move down"
                                            @click="reorder(p, 'down')"
                                        >
                                            <ChevronDown class="h-5 w-5" />
                                        </button>
                                    </div>

                                    <div class="h-4 w-px bg-border" />

                                    <!-- Submissions badge -->
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Submissions</span>
                                        <button
                                            type="button"
                                            class="rounded-md transition hover:opacity-80"
                                            title="View submissions"
                                            @click="openSubmissions(p)"
                                        >
                                            <Badge
                                                variant="outline"
                                                class="flex cursor-pointer items-center gap-1 text-[10px] font-bold"
                                                :class="
                                                    requirementSubmissionCount(p) === requirements.length && requirements.length > 0
                                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400'
                                                        : ''
                                                "
                                            >
                                                <ClipboardList class="h-3 w-3" />
                                                {{ requirementSubmissionCount(p) }}/{{ requirements.length }}
                                            </Badge>
                                        </button>
                                    </div>

                                    <!-- Attendance badge -->
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Attendance</span>
                                        <button
                                            type="button"
                                            class="rounded-md transition hover:opacity-80"
                                            title="Set attendance"
                                            @click="openAttendance(p)"
                                        >
                                            <Badge :class="attendanceColor(p.attendance)" class="cursor-pointer border-0 text-[10px] font-bold">
                                                {{ p.attendance }}
                                            </Badge>
                                        </button>
                                    </div>

                                    <div class="h-4 w-px bg-border" />

                                    <!-- Remove -->
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 w-7 p-0 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                                        @click="removeParticipant(p)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="totalPages > 1" class="mt-1 flex items-center justify-between">
                            <p class="text-[11px] font-semibold text-slate-400">
                                Showing {{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, filteredParticipants.length) }} of
                                {{ filteredParticipants.length }}
                            </p>
                            <div class="flex items-center gap-1">
                                <Button variant="outline" size="sm" class="h-7 w-7 p-0" :disabled="page <= 1" @click="page--">
                                    <ChevronLeft class="h-3.5 w-3.5" />
                                </Button>
                                <span class="px-1.5 text-[11px] font-bold text-slate-500">{{ page }} / {{ totalPages }}</span>
                                <Button variant="outline" size="sm" class="h-7 w-7 p-0" :disabled="page >= totalPages" @click="page++">
                                    <ChevronRight class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </template>

                    <!-- Clear All -->
                    <div v-if="participants.length" class="flex justify-end">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 shrink-0 px-2 text-[11px] font-bold text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                            :disabled="clearingAll"
                            @click="clearAllParticipants"
                        >
                            <LoaderCircle v-if="clearingAll" class="mr-1 h-3 w-3 animate-spin" />
                            <Trash2 v-else class="mr-1 h-3 w-3" />
                            Clear All
                        </Button>
                    </div>
                </div>
            </div>

            <!-- ── Set Attendance Dialog ── -->
            <Dialog :open="showAttendance" @update:open="showAttendance = $event">
                <DialogContent class="flex max-h-[92vh] max-w-lg flex-col gap-0 overflow-hidden !rounded-2xl p-0">
                    <!-- Header -->
                    <div
                        class="relative shrink-0 overflow-hidden border-b bg-gradient-to-br from-blue-50 via-white to-indigo-50 px-5 py-4 pr-12 dark:from-blue-950/50 dark:via-background dark:to-indigo-950/40"
                    >
                        <div
                            class="pointer-events-none absolute -right-8 -top-12 h-32 w-32 rounded-full bg-blue-400/20 blur-2xl dark:bg-blue-500/20"
                        />
                        <DialogHeader class="relative flex-row items-center gap-3 space-y-0 text-left sm:text-left">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-600/30"
                            >
                                <ClipboardCheck class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <DialogTitle class="text-base font-bold leading-5">Set Attendance</DialogTitle>
                                <DialogDescription class="mt-0.5 truncate text-[11px]">
                                    Record how this participant did in {{ batch?.batch ?? 'this batch' }}
                                </DialogDescription>
                            </div>
                        </DialogHeader>
                    </div>

                    <!-- Body -->
                    <div v-if="attendanceTarget" class="grid min-h-0 flex-1 gap-4 overflow-y-auto px-5 py-4">
                        <!-- Participant card -->
                        <section class="rounded-xl border bg-card p-3 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div
                                    :class="attendanceTarget.employee?.avatar_color ?? 'bg-blue-500'"
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white shadow-sm ring-4 ring-slate-100 dark:ring-slate-800"
                                >
                                    {{
                                        attendanceTarget.employee?.initials || initialsOf(attendanceTarget.employee?.name ?? attendanceTarget.empcode)
                                    }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold leading-5">
                                        {{ attendanceTarget.employee?.name ?? attendanceTarget.empcode }}
                                    </p>
                                    <p class="truncate text-[11px] text-muted-foreground">
                                        {{ attendanceTarget.empcode }}
                                        <span v-if="attendanceTarget.employee?.POSITION"> · {{ attendanceTarget.employee.POSITION }}</span>
                                    </p>
                                    <p
                                        v-if="attendanceTarget.employee?.['OFFICE/DIVISION'] ?? attendanceTarget.employee?.OFFICE"
                                        class="truncate text-[11px] text-muted-foreground"
                                    >
                                        {{ attendanceTarget.employee?.['OFFICE/DIVISION'] ?? attendanceTarget.employee?.OFFICE }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-wide text-muted-foreground">On record</p>
                                    <Badge :class="attendanceColor(attendanceTarget.attendance)" class="border-0 text-[10px] font-bold">
                                        {{ attendanceTarget.attendance }}
                                    </Badge>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5 border-t pt-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                                >
                                    <ClipboardList class="h-3 w-3" /> {{ batch?.batch }}
                                </span>
                                <span
                                    v-if="batchDateRange"
                                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                >
                                    <CalendarDays class="h-3 w-3" /> {{ batchDateRange }}
                                </span>
                                <span
                                    v-if="batchHours"
                                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                >
                                    <Clock class="h-3 w-3" /> {{ batchHours }} hrs
                                </span>
                                <span
                                    v-if="attendanceTarget.added_by"
                                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                    :title="attendanceTarget.created_at ? `Enrolled ${formatDateTime(attendanceTarget.created_at)}` : undefined"
                                >
                                    <UserRound class="h-3 w-3" /> Enrolled by {{ attendanceTarget.added_by }}
                                </span>
                            </div>
                        </section>

                        <!-- Status picker -->
                        <section class="grid gap-2">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">
                                    Attendance status <span class="text-red-500">*</span>
                                </h3>
                                <span
                                    v-if="hasUnsavedStatusChange"
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                                >
                                    {{ attendanceTarget.attendance }} <ChevronRight class="h-3 w-3" /> {{ attStatus }}
                                </span>
                            </div>
                            <div role="radiogroup" aria-label="Attendance status" class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="option in attendanceOptions"
                                    :key="option.value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="attStatus === option.value"
                                    class="flex flex-col items-center gap-1.5 rounded-xl border p-2.5 text-center transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    :class="
                                        attStatus === option.value
                                            ? option.activeClass
                                            : 'border-border hover:border-slate-300 hover:bg-muted/40 dark:hover:border-slate-600'
                                    "
                                    @click="selectAttendance(option.value)"
                                >
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full" :class="option.iconClass">
                                        <component :is="option.icon" class="h-4 w-4" />
                                    </span>
                                    <span class="text-xs font-bold">{{ option.label }}</span>
                                    <span class="text-[10px] leading-tight text-muted-foreground">{{ option.hint }}</span>
                                </button>
                            </div>
                        </section>

                        <!-- Complete: hours -->
                        <section
                            v-if="attStatus === 'Complete'"
                            class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/20"
                        >
                            <h3
                                class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-emerald-800 dark:text-emerald-300"
                            >
                                <Clock class="h-3.5 w-3.5" /> Completed hours <span class="text-red-500">*</span>
                            </h3>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <div class="relative min-w-[8rem] flex-1">
                                    <Input
                                        v-model="attHours"
                                        type="number"
                                        step="0.5"
                                        min="0.5"
                                        class="h-9 bg-white pr-10 text-sm font-semibold dark:bg-slate-900"
                                        placeholder="e.g. 16"
                                    />
                                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-muted-foreground"
                                        >hrs</span
                                    >
                                </div>
                                <Button
                                    v-if="batchHours && String(batchHours) !== attHours"
                                    variant="outline"
                                    size="sm"
                                    class="h-9 text-[11px]"
                                    @click="attHours = String(batchHours)"
                                >
                                    Use batch hours ({{ batchHours }})
                                </Button>
                            </div>
                            <p v-if="attErrors.hours" class="mt-1.5 text-xs text-red-500">{{ attErrors.hours }}</p>
                            <p class="mt-1.5 text-[11px] text-emerald-900/70 dark:text-emerald-200/70">
                                These hours go on the participant's training record. "Apply to all" gives the same hours to everyone not marked
                                Absent.
                            </p>
                        </section>

                        <!-- Pending: info -->
                        <section
                            v-if="attStatus === 'Pending'"
                            class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900/60 dark:bg-amber-950/20"
                        >
                            <Info class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <p class="text-[11px] text-amber-900 dark:text-amber-200">
                                Pending means attendance isn't confirmed yet. The participant's hours are reset to 0 until you mark them Complete.
                            </p>
                        </section>

                        <!-- Warning: switching away from Absent deletes the memo -->
                        <section
                            v-if="willDeleteMemo"
                            class="flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-900/60 dark:bg-red-950/30"
                        >
                            <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />
                            <p class="text-[11px] text-red-800 dark:text-red-200">
                                Saving as <b>{{ attStatus }}</b> permanently deletes the justification memo on file (<b>{{ memoFileName }}</b
                                >).
                            </p>
                        </section>

                        <!-- Absent: justification memo -->
                        <section v-if="attStatus === 'Absent'" class="grid gap-3">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-muted-foreground">
                                    <Paperclip class="h-3.5 w-3.5" /> Justification memo
                                    <span v-if="!memoOnFile" class="text-red-500">*</span>
                                </h3>
                                <span
                                    v-if="memoOnFile"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                >
                                    <CheckCircle2 class="h-3 w-3" /> On file
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-500/15 dark:text-red-300"
                                >
                                    <FileX class="h-3 w-3" /> Not yet filed
                                </span>
                            </div>

                            <!-- Memo on file -->
                            <div v-if="memoOnFile" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                                <div class="flex items-center gap-3 p-3">
                                    <div
                                        class="flex h-11 w-10 shrink-0 flex-col items-center justify-center gap-0.5 rounded-lg"
                                        :class="
                                            isImageExtension(memoExtension)
                                                ? 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300'
                                                : 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-300'
                                        "
                                    >
                                        <FileImage v-if="isImageExtension(memoExtension)" class="h-4 w-4" />
                                        <FileText v-else class="h-4 w-4" />
                                        <span class="text-[8px] font-black uppercase leading-none">{{ memoExtension }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-bold" :title="memoFileName">{{ memoFileName }}</p>
                                        <p class="text-[11px] text-muted-foreground">
                                            {{ memoExtension.toUpperCase() }} file
                                            <span v-if="memoOnFile.file_size"> · {{ formatFileSize(memoOnFile.file_size) }}</span>
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button
                                            type="button"
                                            title="Preview memo"
                                            aria-label="Preview memo"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-slate-600 transition hover:bg-muted dark:text-slate-300"
                                            @click="
                                                openFilePreview(
                                                    memoOnFile.file_path,
                                                    'Justification memo',
                                                    attendanceTarget.employee?.name ?? attendanceTarget.empcode,
                                                )
                                            "
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                        </button>
                                        <a
                                            :href="`/storage/${memoOnFile.file_path}`"
                                            target="_blank"
                                            title="Open in new tab"
                                            aria-label="Open memo in new tab"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-slate-600 transition hover:bg-muted dark:text-slate-300"
                                        >
                                            <ExternalLink class="h-3.5 w-3.5" />
                                        </a>
                                        <button
                                            type="button"
                                            :disabled="deletingJustification"
                                            title="Remove memo"
                                            aria-label="Remove memo"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400"
                                            @click="deleteJustificationMemo"
                                        >
                                            <LoaderCircle v-if="deletingJustification" class="h-3.5 w-3.5 animate-spin" />
                                            <Trash2 v-else class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Filing details -->
                                <dl class="grid gap-px border-t bg-border sm:grid-cols-2">
                                    <div class="bg-muted/40 p-3 dark:bg-slate-900/60">
                                        <dt class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground">
                                            <UserRound class="h-3 w-3" /> Filed by
                                        </dt>
                                        <dd v-if="memoUploader" class="mt-1.5 flex items-center gap-2">
                                            <span
                                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                                                :class="
                                                    memoUploader.isSelfSubmitted
                                                        ? 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300'
                                                        : 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300'
                                                "
                                            >
                                                {{ initialsOf(memoUploader.name) }}
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-xs font-semibold">{{ memoUploader.name }}</span>
                                                <span
                                                    class="block text-[10px] font-semibold"
                                                    :class="
                                                        memoUploader.isSelfSubmitted
                                                            ? 'text-violet-600 dark:text-violet-400'
                                                            : 'text-blue-600 dark:text-blue-400'
                                                    "
                                                >
                                                    {{ memoUploader.isSelfSubmitted ? 'Self-submitted via My Programs' : 'Filed on their behalf' }}
                                                </span>
                                            </span>
                                        </dd>
                                        <dd v-else class="mt-1.5">
                                            <span class="block text-xs font-semibold text-muted-foreground">Not recorded</span>
                                            <span class="block text-[10px] text-muted-foreground">Uploaded before uploader tracking was added</span>
                                        </dd>
                                    </div>
                                    <div class="bg-muted/40 p-3 dark:bg-slate-900/60">
                                        <dt class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground">
                                            <CalendarDays class="h-3 w-3" /> Filed on
                                        </dt>
                                        <dd class="mt-1.5 text-xs font-semibold">
                                            {{ formatDateTime(memoWasReplaced ? memoOnFile.updated_at : memoOnFile.created_at) }}
                                        </dd>
                                        <dd class="text-[10px] text-muted-foreground">
                                            {{ timeAgo(memoWasReplaced ? memoOnFile.updated_at : memoOnFile.created_at) }}
                                        </dd>
                                    </div>
                                </dl>

                                <div
                                    v-if="memoWasReplaced"
                                    class="flex items-start gap-1.5 border-t bg-amber-50/70 px-3 py-2 text-[11px] text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
                                >
                                    <History class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                    <span>
                                        First filed on <b>{{ formatDateTime(memoOnFile.created_at) }}</b> ({{ timeAgo(memoOnFile.created_at) }}). The
                                        file above replaced it.
                                    </span>
                                </div>
                            </div>

                            <!-- Upload -->
                            <div class="grid gap-1.5">
                                <p class="text-[11px] font-semibold text-muted-foreground">
                                    {{ memoOnFile ? 'Replace with a new file (optional)' : 'Upload the signed memo or excuse letter' }}
                                </p>
                                <label
                                    v-if="!attFile"
                                    class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-5 text-center transition"
                                    :class="
                                        isDraggingMemo
                                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-500/10'
                                            : attErrors.justification
                                              ? 'border-red-300 bg-red-50/40 dark:border-red-800 dark:bg-red-950/20'
                                              : 'border-slate-300 hover:border-blue-400 hover:bg-blue-50/40 dark:border-slate-700 dark:hover:border-blue-700 dark:hover:bg-blue-500/5'
                                    "
                                    @dragover.prevent="isDraggingMemo = true"
                                    @dragleave.prevent="isDraggingMemo = false"
                                    @drop.prevent="onMemoDrop"
                                >
                                    <span
                                        class="pointer-events-none flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300"
                                    >
                                        <CloudUpload class="h-5 w-5" />
                                    </span>
                                    <span class="pointer-events-none text-xs font-semibold">
                                        <span class="text-blue-600 dark:text-blue-400">Click to choose</span> or drag &amp; drop
                                    </span>
                                    <span class="pointer-events-none text-[10px] text-muted-foreground"
                                        >PDF, Word (.doc, .docx), JPG or PNG · Max 5MB</span
                                    >
                                    <input
                                        ref="memoInput"
                                        type="file"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                        class="sr-only"
                                        @change="onFileChange"
                                    />
                                </label>

                                <div
                                    v-else
                                    class="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/30"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/30"
                                    >
                                        <FileImage v-if="isImageExtension(fileExtension(attFile.name))" class="h-4 w-4" />
                                        <FileText v-else class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-bold" :title="attFile.name">{{ attFile.name }}</p>
                                        <p class="text-[11px] text-blue-700 dark:text-blue-300">
                                            {{ formatFileSize(attFile.size) }} · Ready to upload when you save
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        title="Remove selected file"
                                        aria-label="Remove selected file"
                                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-blue-100 hover:text-slate-700 dark:hover:bg-blue-900/50 dark:hover:text-slate-200"
                                        @click="clearPickedMemo"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <p v-if="attFile && memoOnFile" class="flex items-start gap-1.5 text-[11px] text-amber-700 dark:text-amber-400">
                                    <TriangleAlert class="mt-0.5 h-3 w-3 shrink-0" />
                                    Saving replaces "{{ memoFileName }}" with this file. The old file is deleted.
                                </p>
                                <p v-if="attErrors.justification" class="text-xs text-red-500">{{ attErrors.justification }}</p>
                            </div>
                        </section>
                    </div>

                    <!-- Footer -->
                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t bg-muted/30 px-5 py-3">
                        <Button variant="outline" size="sm" @click="showAttendance = false">Cancel</Button>
                        <Button v-if="attStatus === 'Complete'" variant="outline" size="sm" :disabled="attProcessing" @click="applyToAll">
                            <LoaderCircle v-if="attProcessing" class="mr-1 h-3 w-3 animate-spin" />
                            <Users v-else class="mr-1 h-3.5 w-3.5" />
                            Apply to all
                        </Button>
                        <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" :disabled="attProcessing" @click="submitAttendance">
                            <LoaderCircle v-if="attProcessing" class="mr-1 h-3 w-3 animate-spin" />
                            <Save v-else class="mr-1 h-3.5 w-3.5" />
                            Save Attendance
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <BulkAddParticipants :open="showBulkAdd" :batch="batch" @update:open="showBulkAdd = $event" />

            <!-- ── Submissions Dialog ── -->
            <Dialog :open="showSubmissions" @update:open="showSubmissions = $event">
                <DialogContent class="flex max-h-[90vh] max-w-2xl flex-col gap-0 overflow-hidden !rounded-2xl border-0 p-0 shadow-2xl">
                    <!-- Gradient header -->
                    <DialogHeader
                        class="relative shrink-0 overflow-hidden bg-gradient-to-br from-blue-600 via-blue-500 to-indigo-500 px-6 pb-5 pt-5 text-left"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 opacity-20"
                            style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 18px 18px"
                        ></div>
                        <div class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 rounded-full bg-white/10 blur-2xl"></div>

                        <div class="relative z-10 flex items-center gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur"
                            >
                                <ClipboardList class="h-5 w-5 text-white" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <DialogTitle class="truncate text-base font-bold text-white">
                                    {{ submissionsTarget?.employee?.name ?? submissionsTarget?.empcode }}
                                </DialogTitle>
                                <DialogDescription class="text-xs text-blue-100"> Submissions — {{ batch?.batch }} </DialogDescription>
                            </div>

                            <!-- Approved/total pill -->
                            <div
                                v-if="requirements.length"
                                class="relative z-10 shrink-0 rounded-xl bg-white/15 px-3 py-1.5 text-center ring-1 ring-white/25 backdrop-blur"
                            >
                                <p class="text-sm font-extrabold leading-none text-white">
                                    {{ submissionSummary.done }}/{{ submissionSummary.total }}
                                </p>
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-blue-100">Approved</p>
                            </div>
                        </div>
                    </DialogHeader>

                    <div class="flex-1 overflow-y-auto bg-muted/20 px-5 py-4">
                        <!-- Empty: walang requirements -->
                        <div
                            v-if="!requirements.length"
                            class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed bg-card px-6 py-10 text-center shadow-md"
                        >
                            <svg viewBox="0 0 120 100" class="h-20 w-auto" xmlns="http://www.w3.org/2000/svg">
                                <ellipse cx="60" cy="92" rx="44" ry="6" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                                <rect x="22" y="10" width="76" height="76" rx="8" fill="currentColor" class="text-blue-100 dark:text-blue-900/40" />
                                <rect x="38" y="2" width="44" height="14" rx="4" fill="currentColor" class="text-blue-300 dark:text-blue-700/60" />
                                <rect
                                    x="34"
                                    y="34"
                                    width="8"
                                    height="8"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    fill="none"
                                    class="text-slate-300 dark:text-slate-600"
                                />
                                <rect x="50" y="35" width="34" height="5" rx="2.5" fill="currentColor" class="text-slate-300 dark:text-slate-700" />
                                <rect
                                    x="34"
                                    y="52"
                                    width="8"
                                    height="8"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    fill="none"
                                    class="text-slate-300 dark:text-slate-600"
                                />
                                <rect x="50" y="53" width="26" height="5" rx="2.5" fill="currentColor" class="text-slate-300 dark:text-slate-700" />
                            </svg>
                            <p class="text-sm font-bold text-slate-500">No requirements set</p>
                            <p class="max-w-xs text-xs text-slate-400">
                                Add requirements to this batch in the Requirements tab, or add the participant's documents under Other Documents
                                below.
                            </p>
                        </div>

                        <div v-else class="flex flex-col gap-2.5">
                            <div
                                v-for="row in mergedSubmissions"
                                :key="row.requirement.id"
                                class="rounded-xl border bg-card shadow-md transition hover:shadow-lg"
                                :class="statusMeta(row.submission?.status, !!row.submission).ring"
                            >
                                <!-- VIEW MODE -->
                                <div v-if="editingRow !== row.requirement.id" class="flex items-center justify-between gap-2 p-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <!-- Status icon chip -->
                                        <div
                                            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                            :class="statusMeta(row.submission?.status, !!row.submission).badge"
                                        >
                                            <component :is="statusMeta(row.submission?.status, !!row.submission).icon" class="h-4 w-4" />
                                        </div>

                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-bold leading-4">
                                                {{ row.requirement.name }}
                                                <span class="text-[10px] font-semibold text-blue-500">({{ row.requirement.title }})</span>
                                            </p>
                                            <p class="text-[11px] text-muted-foreground">
                                                Due: {{ formatDueDate(row.requirement.due_date) }}
                                                <span v-if="!row.requirement.is_required" class="ml-1 text-slate-400">(optional)</span>
                                            </p>
                                            <p v-if="row.submission?.remarks" class="mt-0.5 text-[11px] italic text-slate-500">
                                                "{{ row.submission.remarks }}"
                                            </p>
                                            <button
                                                v-if="row.submission?.file_path"
                                                type="button"
                                                class="mt-0.5 inline-flex items-center gap-0.5 text-[11px] font-semibold text-blue-600 hover:underline"
                                                @click="openFilePreview(row.submission.file_path, row.requirement.name, row.requirement.title)"
                                            >
                                                <FileText class="h-3 w-3" /> View file
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-2">
                                        <Badge
                                            :class="statusMeta(row.submission?.status, !!row.submission).badge"
                                            class="gap-1 border-0 text-[10px] font-bold"
                                        >
                                            <component :is="statusMeta(row.submission?.status, !!row.submission).icon" class="h-3 w-3" />
                                            {{ statusMeta(row.submission?.status, !!row.submission).label }}
                                        </Badge>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 px-2 text-xs text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/30"
                                            @click="startEdit(row)"
                                        >
                                            <Upload class="mr-1 h-3 w-3" /> Set
                                        </Button>
                                        <Button
                                            v-if="row.submission"
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 p-0 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                                            @click="deleteSubmission(row.submission.id)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>

                                <!-- EDIT MODE -->
                                <div v-else class="grid gap-2 rounded-xl bg-blue-50/50 p-3 dark:bg-blue-950/20">
                                    <p class="flex items-center gap-2 text-xs font-bold leading-4">
                                        <CloudUpload class="h-4 w-4 text-blue-600" />
                                        {{ row.requirement.name }}
                                        <span class="text-[10px] font-semibold text-blue-500">({{ row.requirement.title }})</span>
                                        <Badge
                                            v-if="row.submission?.file_path"
                                            variant="outline"
                                            class="gap-0.5 border-emerald-300 text-[9px] font-bold text-emerald-700"
                                        >
                                            <FileText class="h-3 w-3" /> File on record
                                        </Badge>
                                    </p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="grid gap-1">
                                            <Label class="flex items-center gap-1 text-xs"
                                                ><ClipboardCheck class="h-3 w-3 text-slate-400" /> Status</Label
                                            >
                                            <Select v-model="subStatus">
                                                <SelectTrigger class="h-8 bg-white text-xs dark:bg-slate-900"
                                                    ><SelectValue placeholder="Select status"
                                                /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem class="text-xs" value="Pending">Pending</SelectItem>
                                                    <SelectItem class="text-xs" value="Approved">Approved</SelectItem>
                                                    <SelectItem class="text-xs" value="Rejected">Rejected</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="grid gap-1">
                                            <Label class="flex items-center gap-1 text-xs"
                                                ><FileText class="h-3 w-3 text-slate-400" /> File (PDF)</Label
                                            >
                                            <input
                                                type="file"
                                                accept=".pdf"
                                                class="cursor-pointer text-xs file:mr-2 file:rounded-md file:border-0 file:bg-blue-600 file:px-2.5 file:py-1.5 file:text-[11px] file:font-bold file:text-white hover:file:bg-blue-500"
                                                @change="onSubFileChange"
                                            />
                                            <p v-if="row.submission?.file_path && !subFile" class="text-[11px] text-slate-500">
                                                Naka-upload na. Mag-upload ulit para palitan.
                                            </p>
                                            <p class="text-xs text-red-500">{{ subErrors.file }}</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-1">
                                        <Label class="flex items-center gap-1 text-xs"><ListFilter class="h-3 w-3 text-slate-400" /> Remarks</Label>
                                        <Input
                                            class="h-8 bg-white text-xs dark:bg-slate-900"
                                            v-model="subRemarks"
                                            placeholder="e.g. Needs revision on section 3"
                                        />
                                    </div>
                                    <div class="flex justify-end gap-2 pt-1">
                                        <Button variant="outline" size="sm" @click="cancelEdit">Cancel</Button>
                                        <Button
                                            class="bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-md shadow-blue-500/25 hover:from-blue-700 hover:to-blue-600"
                                            size="sm"
                                            :disabled="subProcessing"
                                            @click="saveSubmission(row)"
                                        >
                                            <LoaderCircle v-if="subProcessing" class="mr-1 h-3 w-3 animate-spin" />
                                            <Save v-else class="mr-1 h-3.5 w-3.5" />
                                            Save
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Other documents: no requirement, or their requirement was deleted -->
                        <div class="mt-5">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Other Documents</p>
                                <Button
                                    v-if="!docFormOpen"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 px-2 text-xs text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/30"
                                    @click="openDocForm()"
                                >
                                    <Upload class="mr-1 h-3 w-3" /> Add Document
                                </Button>
                            </div>

                            <div v-if="docFormOpen" class="mb-2.5 grid gap-2 rounded-xl border bg-blue-50/50 p-3 shadow-md dark:bg-blue-950/20">
                                <div class="grid gap-1">
                                    <Label class="flex items-center gap-1 text-xs"><FileText class="h-3 w-3 text-slate-400" /> Document Title</Label>
                                    <Input
                                        v-model="docTitle"
                                        class="h-8 bg-white text-xs dark:bg-slate-900"
                                        placeholder="e.g. Certificate of Attendance"
                                    />
                                    <p class="text-xs text-red-500">{{ docErrors.document_title }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="grid gap-1">
                                        <Label class="flex items-center gap-1 text-xs"
                                            ><ClipboardCheck class="h-3 w-3 text-slate-400" /> Status</Label
                                        >
                                        <Select v-model="docStatus">
                                            <SelectTrigger class="h-8 bg-white text-xs dark:bg-slate-900"
                                                ><SelectValue placeholder="Select status"
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem class="text-xs" value="Pending">Pending</SelectItem>
                                                <SelectItem class="text-xs" value="Approved">Approved</SelectItem>
                                                <SelectItem class="text-xs" value="Rejected">Rejected</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1">
                                        <Label class="flex items-center gap-1 text-xs"><FileText class="h-3 w-3 text-slate-400" /> File (PDF)</Label>
                                        <input
                                            type="file"
                                            accept=".pdf"
                                            class="cursor-pointer text-xs file:mr-2 file:rounded-md file:border-0 file:bg-blue-600 file:px-2.5 file:py-1.5 file:text-[11px] file:font-bold file:text-white hover:file:bg-blue-500"
                                            @change="onDocFileChange"
                                        />
                                        <p v-if="docEditingId && !docFile" class="text-[11px] text-slate-500">
                                            Leave empty to keep the current file.
                                        </p>
                                        <p class="text-xs text-red-500">{{ docErrors.file }}</p>
                                    </div>
                                </div>
                                <div class="grid gap-1">
                                    <Label class="flex items-center gap-1 text-xs"><ListFilter class="h-3 w-3 text-slate-400" /> Remarks</Label>
                                    <Input v-model="docRemarks" class="h-8 bg-white text-xs dark:bg-slate-900" />
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <Button variant="outline" size="sm" @click="closeDocForm">Cancel</Button>
                                    <Button
                                        class="bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-md shadow-blue-500/25 hover:from-blue-700 hover:to-blue-600"
                                        size="sm"
                                        :disabled="docProcessing || !docTitle.trim()"
                                        @click="saveOtherDoc"
                                    >
                                        <LoaderCircle v-if="docProcessing" class="mr-1 h-3 w-3 animate-spin" />
                                        <Save v-else class="mr-1 h-3.5 w-3.5" />
                                        Save
                                    </Button>
                                </div>
                            </div>

                            <p v-if="!otherDocs.length && !docFormOpen" class="text-[11px] text-slate-400">No other documents.</p>

                            <div class="flex flex-col gap-2.5">
                                <div
                                    v-for="doc in otherDocs"
                                    :key="doc.id"
                                    class="flex items-center justify-between gap-2 rounded-xl border bg-card p-3 shadow-md"
                                    :class="statusMeta(doc.status, true).ring"
                                >
                                    <div class="flex min-w-0 items-start gap-3">
                                        <div
                                            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                            :class="statusMeta(doc.status, true).badge"
                                        >
                                            <component :is="statusMeta(doc.status, true).icon" class="h-4 w-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-bold leading-4">{{ doc.document_title ?? 'Other Document' }}</p>
                                            <p v-if="doc.remarks" class="mt-0.5 text-[11px] italic text-slate-500">"{{ doc.remarks }}"</p>
                                            <button
                                                v-if="doc.file_path"
                                                type="button"
                                                class="mt-0.5 inline-flex items-center gap-0.5 text-[11px] font-semibold text-blue-600 hover:underline"
                                                @click="openFilePreview(doc.file_path, doc.document_title ?? 'Other Document')"
                                            >
                                                <FileText class="h-3 w-3" /> View file
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <Badge :class="statusMeta(doc.status, true).badge" class="gap-1 border-0 text-[10px] font-bold">
                                            <component :is="statusMeta(doc.status, true).icon" class="h-3 w-3" />
                                            {{ statusMeta(doc.status, true).label }}
                                        </Badge>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 px-2 text-xs text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/30"
                                            @click="openDocForm(doc)"
                                        >
                                            <Upload class="mr-1 h-3 w-3" /> Set
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 p-0 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                                            @click="deleteSubmission(doc.id)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 justify-end border-t bg-card px-5 py-3">
                        <Button variant="outline" size="sm" @click="showSubmissions = false">Close</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </DialogContent>
    </Dialog>

    <FilePreviewModal
        :open="showFilePreview"
        :file-url="previewFile?.url ?? null"
        :title="previewFile?.title"
        :description="previewFile?.description"
        @update:open="showFilePreview = $event"
    />

    <BulkSubmissionUpload :open="showBulkSubmissionUpload" :batch="batch" @update:open="showBulkSubmissionUpload = $event" />
</template>
