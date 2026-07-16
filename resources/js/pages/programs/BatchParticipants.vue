<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import {
    Users, Search, X, UserPlus, LoaderCircle, Trash2, ChevronLeft, ChevronRight,
    ListFilter, ClipboardCheck, ClipboardList, FileText, Save, Upload,
    ClipboardPaste, ChevronDown, ChevronUp, Download,
    CheckCircle2, XCircle, Clock, FileX, CloudUpload,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import BulkAddParticipants from '@/pages/programs/BulkAddParticipants.vue';

interface EmployeeOption {
    empcode: string;
    name: string;
}

const props = defineProps<{
    open: boolean;
    batch: any | null;
    program?: any | null;
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

const participants = computed(() =>
    [...(props.batch?.participants ?? [])].sort((a, b) => {
        if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
        return a.id - b.id; // tiebreaker
    })
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

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredParticipants.value.length / perPage))
);

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

    const current = ['Pending', 'Complete', 'Absent'].includes(p.attendance)
        ? p.attendance
        : 'Pending';
    attStatus.value = current as any;

    attHours.value = current === 'Complete' && p.hours
        ? String(p.hours)
        : String(batchHours.value || '');

    attFile.value = null;
    attErrors.value = {};
    showAttendance.value = true;
};

const onFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    attFile.value = input.files?.[0] ?? null;
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
        if (batchHours.value > 0 && h > batchHours.value) {
            attErrors.value.hours = `Cannot exceed the batch total of ${batchHours.value} hour(s).`;
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
                showAttendance.value = false;
                attendanceTarget.value = null;
            },
            onError: (errors) => {
                attErrors.value = errors as any;
            },
            onFinish: () => {
                attProcessing.value = false;
            },
        }
    );
};

/* ---------- Submissions dialog ---------- */
const showSubmissions = ref(false);
const submissionsTargetId = ref<number | null>(null);

const submissionsTarget = computed(() => {
    if (!submissionsTargetId.value) return null;
    return participants.value.find((p: any) => p.id === submissionsTargetId.value) ?? null;
});

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
    const s = (status ?? '').toLowerCase();
    if (s === 'approved') return 'Approved';
    if (s === 'rejected') return 'Rejected';
    return 'Pending';
};

const editingRow = ref<number | null>(null);
const subStatus = ref<'Pending' | 'Approved' | 'Rejected'>('Pending');
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
        }
    );
};

const deleteSubmission = (submissionId: number) => {
    if (!confirm('Delete this submission file/record?')) return;
    router.delete(route('submissions.destroy', submissionId), {
        preserveScroll: true,
        onSuccess: () => {
            showSubmissions.value = false;
            submissionsTarget.value = null;
            editingRow.value = null;
        },
    });
};

/* ---------- Helpers ---------- */
const attendanceColor = (status: string) => {
    switch (status) {
        case 'Complete': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'Absent':   return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
        case 'Pending':  return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
        default:         return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    }
};

const submissionStatusColor = (status: string | null | undefined) => {
    switch (normalizeStatus(status)) {
        case 'Approved': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'Rejected': return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
        case 'Pending':
        default:         return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    }
};

const submissionStatusLabel = (status: string | null | undefined) => {
    return normalizeStatus(status);
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
        'Program Code', 'Program Title', 'Batch', 'Status', 'Modality', 'Venue', 'Schedule', 'Total Hours',
        '#', 'Employee Name', 'Employee Code', 'Position', 'Office', 'Attendance', 'Hours',
    ];
    for (const r of reqs) header.push(r.title ?? r.name ?? `Req ${r.id}`);
    header.push('Generated');
    line(header);

    // ── Participant rows (inuulit ang program/batch info kada row) ──
    participants.value.forEach((p: any, i: number) => {
        const subMap: Record<number, string> = {};
        for (const s of (p.submissions ?? [])) {
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

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        results.value = [];
        selected.value = [];
        showDropdown.value = false;
        listQuery.value = '';
        page.value = 1;
        showSubmissions.value = false;
        submissionsTarget.value = null;
        editingRow.value = null;
    }
});

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
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const data: EmployeeOption[] = await res.json();

            results.value = data.filter(
                (emp) => !selected.value.some((s) => s.empcode === emp.empcode)
            );
            showDropdown.value = true;
        } catch (e) {
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
        }
    );
};

const removeParticipant = (participant: any) => {
    const label = participant.employee?.name ?? participant.empcode;
    if (!confirm(`Remove ${label} from this batch?`)) return;

    router.delete(route('participants.destroy', participant.id), {
        preserveScroll: true,
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
            onFinish: () => { reordering.value = null; },
        }
    );
};

const showBulkAdd = ref(false);

const clearingAll = ref(false);

const clearAllParticipants = () => {
    if (!participants.value.length) return;

    const count = participants.value.length;
    if (!confirm(`Remove ALL ${count} participant(s) from this batch? This cannot be undone.`)) return;

    clearingAll.value = true;

    const deleteOne = (index: number) => {
        if (index >= participants.value.length) {
            clearingAll.value = false;
            return;
        }

        const participant = participants.value[index];

        router.delete(route('participants.destroy', participant.id), {
            preserveScroll: true,
            onFinish: () => {
                // ✅ Laging i-delete ang index 0 hanggang maubos,
                // dahil mag-shrink ang array pagkatapos ng bawat reload
                deleteOne(0);
            },
        });
    };

    deleteOne(0);
};


const applyToAll = () => {
    if (!attendanceTarget.value) return;
    attErrors.value = {};

    const h = Number(attHours.value);
    if (!attHours.value || isNaN(h) || h <= 0) {
        attErrors.value.hours = 'Please enter the completed hours.';
        return;
    }
    if (batchHours.value > 0 && h > batchHours.value) {
        attErrors.value.hours = `Cannot exceed the batch total of ${batchHours.value} hour(s).`;
        return;
    }

    const eligibleCount = participants.value.filter(
        (p: any) => p.attendance !== 'Absent'
    ).length;

    if (!confirm(
        `Apply ${h} hr(s) / Complete to all ${eligibleCount} participant(s) in this batch? ` +
        `Absent participants will be skipped.`
    )) return;

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
            onError: (errors) => { attErrors.value = errors as any; },
            onFinish: () => { attProcessing.value = false; },
        }
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
            return { label: 'Approved', icon: CheckCircle2,
                badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                ring: 'border-emerald-200 dark:border-emerald-800' };
        case 'Rejected':
            return { label: 'Rejected', icon: XCircle,
                badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                ring: 'border-red-200 dark:border-red-800' };
        default:
            return { label: 'Pending', icon: Clock,
                badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                ring: 'border-amber-200 dark:border-amber-800' };
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
        <DialogContent class="max-w-4xl flex flex-col max-h-[90vh] overflow-hidden !rounded-2xl p-0 gap-0">

            <!-- ── Modal Header ── -->
            <div class="flex items-center gap-3 px-6 py-4 border-b shrink-0">
                <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-blue-600 shrink-0">
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
                    class="ml-auto mr-5 text-xs border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
                    @click="exportCsv"
                >
                    <Download class="h-3.5 w-3.5 mr-1" />
                    Export CSV
                </Button>
            </div>

            <div class="flex flex-col gap-0 overflow-y-auto flex-1">

                <!-- ══════════════════════════════════════════
                     SECTION 1 — ADD PARTICIPANTS
                ══════════════════════════════════════════ -->
                <div class="bg-blue-50 dark:bg-blue-950/30 border-b px-6 py-4 flex flex-col gap-3">

                    <!-- Section title -->
                    <div class="flex items-center gap-2">
                        <div class="flex items-center justify-center w-6 h-6 rounded-lg bg-blue-600 shrink-0">
                            <UserPlus class="h-3.5 w-3.5 text-white" />
                        </div>
                        <div>
                            <p class="text-xs font-bold text-blue-900 dark:text-blue-100 leading-none">Add Participants</p>
                            <p class="text-[11px] text-blue-600 dark:text-blue-400 mt-0.5">Search employees or paste a list of employee codes</p>
                        </div>
                    </div>

                    <!-- Search input -->
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-blue-400" />
                        <Input
                            v-model="query"
                            class="text-xs h-9 pl-8 bg-white dark:bg-blue-950/50 border-blue-200 dark:border-blue-800 focus-visible:ring-blue-400"
                            placeholder="Search employee by name or empcode..."
                        />
                        <LoaderCircle
                            v-if="searching"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 animate-spin text-blue-400"
                        />

                        <!-- Dropdown results -->
                        <div
                            v-if="showDropdown && results.length"
                            class="absolute z-50 mt-1 w-full rounded-xl border border-blue-100 bg-white dark:bg-slate-900 shadow-lg max-h-48 overflow-y-auto"
                        >
                            <button
                                v-for="emp in results"
                                :key="emp.empcode"
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-left text-xs hover:bg-blue-50 dark:hover:bg-blue-950/40 transition-colors"
                                @click="selectEmployee(emp)"
                            >
                                <span class="font-semibold">{{ emp.name }}</span>
                                <span class="text-muted-foreground text-[11px] bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded-full">{{ emp.empcode }}</span>
                            </button>
                        </div>
                        <div
                            v-else-if="showDropdown && !searching && query.length >= 2"
                            class="absolute z-50 mt-1 w-full rounded-xl border bg-white dark:bg-slate-900 shadow-md px-3 py-2 text-xs text-muted-foreground"
                        >
                            No employees found.
                        </div>
                    </div>

                    <!-- Selected tags -->
                    <div v-if="selected.length" class="flex flex-wrap gap-1.5">
                        <Badge
                            v-for="emp in selected"
                            :key="emp.empcode"
                            class="text-[11px] font-semibold pl-2 pr-1 py-1 gap-1 bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200 border-blue-200 dark:border-blue-700 hover:bg-blue-100"
                        >
                            {{ emp.name }}
                            <button
                                type="button"
                                class="rounded-full hover:bg-blue-200 dark:hover:bg-blue-800 p-0.5 transition-colors"
                                @click="removeSelected(emp.empcode)"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex items-center justify-between gap-2">
                        <!-- Stats pill -->
                        <div v-if="selected.length" class="text-[11px] text-blue-600 dark:text-blue-400 font-semibold">
                            {{ selected.length }} employee{{ selected.length > 1 ? 's' : '' }} selected
                        </div>
                        <div v-else class="text-[11px] text-blue-400 dark:text-blue-500 italic">
                            Select employees above to add them
                        </div>

                        <div class="flex gap-2 ml-auto">
                            <Button
                                variant="outline"
                                size="sm"
                                class="text-xs border-blue-200 dark:border-blue-700 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/40 bg-white dark:bg-transparent"
                                @click="showBulkAdd = true"
                            >
                                <ClipboardPaste class="h-3.5 w-3.5 mr-1" />
                                Bulk Add
                            </Button>
                            <Button
                                size="sm"
                                class="bg-blue-600 hover:bg-blue-700 dark:text-white text-xs font-bold"
                                :disabled="!selected.length || processing"
                                @click="addParticipants"
                            >
                                <LoaderCircle v-if="processing" class="h-3 w-3 animate-spin mr-1" />
                                <UserPlus v-else class="h-3.5 w-3.5 mr-1" />
                                Add{{ selected.length > 1 ? ` ${selected.length} Participants` : selected.length === 1 ? ' Participant' : ' Participants' }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════
                     SECTION 2 — ENROLLED PARTICIPANTS LIST
                ══════════════════════════════════════════ -->
                <div class="px-6 py-4 flex flex-col gap-3">

                    <!-- Section title -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-600 shrink-0">
                                <Users class="h-3.5 w-3.5 text-white" />
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-700 dark:text-slate-200 leading-none">
                                    Enrolled Participants
                                    <span class="ml-1.5 text-[11px] font-normal text-muted-foreground">({{ participants.length }} total)</span>
                                </p>
                                <p class="text-[11px] text-muted-foreground mt-0.5">Manage attendance, submissions, and order</p>
                            </div>
                        </div>

                        <!-- Search + Clear All -->
                        <div v-if="participants.length" class="flex items-center gap-2">
                            <div class="relative w-52">
                                <Search class="absolute left-2 top-1/2 -translate-y-1/2 h-3 w-3 text-muted-foreground" />
                                <Input
                                    v-model="listQuery"
                                    class="text-xs h-7 pl-6"
                                    placeholder="Search enrolled..."
                                />
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-[11px] font-bold text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 shrink-0"
                                :disabled="clearingAll"
                                @click="clearAllParticipants"
                            >
                                <LoaderCircle v-if="clearingAll" class="h-3 w-3 animate-spin mr-1" />
                                <Trash2 v-else class="h-3 w-3 mr-1" />
                                Clear All
                            </Button>
                        </div>
                    </div>

                    <!-- Empty: no participants -->
                    <div
                        v-if="!participants.length"
                        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 py-10 px-6 text-center gap-2"
                    >
                        <svg viewBox="0 0 160 110" class="h-24 w-auto" xmlns="http://www.w3.org/2000/svg">
                            <ellipse cx="80" cy="98" rx="60" ry="6" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                            <circle cx="55" cy="40" r="18" fill="currentColor" class="text-emerald-100 dark:text-emerald-900/40" />
                            <path d="M30 85 c0 -18 12 -28 25 -28 s25 10 25 28" fill="currentColor" class="text-emerald-100 dark:text-emerald-900/40" />
                            <circle cx="105" cy="36" r="22" fill="currentColor" class="text-emerald-200 dark:text-emerald-800/60" />
                            <path d="M75 88 c0 -22 14 -34 30 -34 s30 12 30 34" fill="currentColor" class="text-emerald-200 dark:text-emerald-800/60" />
                            <circle cx="105" cy="36" r="22" stroke="currentColor" stroke-width="2" fill="none" class="text-emerald-300 dark:text-emerald-700/60" stroke-dasharray="4 4" />
                            <path d="M95 36 l7 7 l12 -14" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-500 dark:text-emerald-400" />
                        </svg>
                        <p class="text-sm font-bold text-slate-500">No participants yet</p>
                        <p class="text-xs text-slate-400 max-w-xs">Use the search above to find and add employees, or use "Bulk Add" to paste a list of employee codes.</p>
                    </div>

                    <!-- Empty: filter no match -->
                    <div
                        v-else-if="!filteredParticipants.length"
                        class="flex flex-col items-center justify-center rounded-2xl border border-dashed py-8 px-6 text-center gap-2"
                    >
                        <svg viewBox="0 0 140 100" class="h-20 w-auto" xmlns="http://www.w3.org/2000/svg">
                            <ellipse cx="70" cy="90" rx="50" ry="6" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                            <circle cx="60" cy="45" r="28" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                            <circle cx="60" cy="45" r="28" stroke="currentColor" stroke-width="5" fill="none" class="text-blue-300 dark:text-blue-700/60" />
                            <line x1="80" y1="65" x2="102" y2="87" stroke="currentColor" stroke-width="7" stroke-linecap="round" class="text-blue-300 dark:text-blue-700/60" />
                            <path d="M48 45 h24 M60 33 v24" stroke="currentColor" stroke-width="4" stroke-linecap="round" class="text-slate-300 dark:text-slate-700" />
                        </svg>
                        <p class="text-xs font-bold text-slate-500">No participant matching "{{ listQuery }}"</p>
                    </div>

                    <!-- Participant list -->
                    <template v-else>
                        <div class="rounded-xl border overflow-hidden divide-y">

                            <!-- List header -->
                            <div class="grid grid-cols-[2rem_1fr_auto] gap-2 items-center px-3 py-1.5 bg-muted/40 border-b">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground text-center">#</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground">Employee</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground text-right pr-1">Actions</span>
                            </div>

                            <div
                                v-for="(p, i) in paginatedParticipants"
                                :key="p.id"
                                class="flex items-center justify-between px-3 py-2.5 hover:bg-muted/20 transition-colors"
                            >
                                <!-- Left: number + name -->
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <!-- Row number -->
                                    <span class="text-[11px] font-bold text-slate-400 w-6 text-right shrink-0">
                                        {{ rowNumber(i) }}.
                                    </span>

                                    <!-- Avatar circle -->
                                    <div class="shrink-0 w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-[10px] font-bold text-blue-700 dark:text-blue-300">
                                        {{ (p.employee?.name ?? p.empcode).split(' ').map((n: string) => n[0]).slice(0,2).join('').toUpperCase() }}
                                    </div>

                                    <div class="min-w-0">
                                        <p class="text-xs font-bold leading-4 truncate">
                                            {{ p.employee?.name ?? p.empcode }}
                                        </p>
                                        <p class="text-[11px] text-muted-foreground">
                                            {{ p.empcode }}
                                            <span v-if="p.attendance === 'Complete'" class="text-emerald-600 dark:text-emerald-400 font-semibold">
                                                · {{ p.hours }} hr/s
                                            </span>
                                        </p>
                                        <a v-if="p.attendance === 'Absent' && p.justification"
                                            :href="`/storage/${p.justification.file_path}`"
                                            target="_blank"
                                            class="inline-flex items-center gap-0.5 text-[11px] text-blue-600 hover:underline font-semibold"
                                        >
                                            <FileText class="h-3 w-3" /> View memo
                                        </a>
                                    </div>
                                </div>

                                <!-- Right: actions -->
                                <div class="flex items-center gap-2.5 shrink-0">

                                    <!-- Reorder -->
                                    <div class="flex gap-0.5">
                                        <button
                                            type="button"
                                            :disabled="reordering === p.id || rowNumber(i) === 1"
                                            class="rounded p-0.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-30 disabled:cursor-not-allowed transition"
                                            title="Move up"
                                            @click="reorder(p, 'up')"
                                        >
                                            <ChevronUp  class="h-5 w-5" />
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="reordering === p.id || rowNumber(i) === filteredParticipants.length"
                                            class="rounded p-0.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-30 disabled:cursor-not-allowed transition"
                                            title="Move down"
                                            @click="reorder(p, 'down')"
                                        >
                                            <ChevronDown class="h-5 w-5" />
                                        </button>
                                    </div>

                                    <div class="w-px h-4 bg-border" />

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
                                                class="text-[10px] font-bold cursor-pointer flex items-center gap-1"
                                                :class="(p.submissions?.length ?? 0) === requirements.length && requirements.length > 0
                                                    ? 'border-emerald-300 text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30'
                                                    : ''"
                                            >
                                                <ClipboardList class="h-3 w-3" />
                                                {{ p.submissions?.length ?? 0 }}/{{ requirements.length }}
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
                                            <Badge :class="attendanceColor(p.attendance)" class="text-[10px] font-bold border-0 cursor-pointer">
                                                {{ p.attendance }}
                                            </Badge>
                                        </button>
                                    </div>

                                    <div class="w-px h-4 bg-border" />

                                    <!-- Remove -->
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 w-7 p-0 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                                        @click="removeParticipant(p)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="totalPages > 1" class="flex items-center justify-between mt-1">
                            <p class="text-[11px] font-semibold text-slate-400">
                                Showing {{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, filteredParticipants.length) }}
                                of {{ filteredParticipants.length }}
                            </p>
                            <div class="flex items-center gap-1">
                                <Button variant="outline" size="sm" class="h-7 w-7 p-0" :disabled="page <= 1" @click="page--">
                                    <ChevronLeft class="h-3.5 w-3.5" />
                                </Button>
                                <span class="text-[11px] font-bold text-slate-500 px-1.5">{{ page }} / {{ totalPages }}</span>
                                <Button variant="outline" size="sm" class="h-7 w-7 p-0" :disabled="page >= totalPages" @click="page++">
                                    <ChevronRight class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ── Set Attendance Dialog ── -->
            <Dialog :open="showAttendance" @update:open="showAttendance = $event">
                <DialogContent class="max-w-sm !rounded-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            <span class="flex gap-2 items-center">
                                <ClipboardCheck class="h-5 w-5 text-blue-600" /> Set Attendance
                            </span>
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground">
                            {{ attendanceTarget?.employee?.name ?? attendanceTarget?.empcode }}
                            — {{ batch?.batch }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-3 py-1">
                        <div class="grid gap-1">
                            <Label class="text-xs">Attendance <span class="text-red-500">*</span></Label>
                            <Select v-model="attStatus">
                                <SelectTrigger class="text-xs h-8">
                                    <SelectValue placeholder="Select attendance" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem class="text-xs" value="Pending">Pending</SelectItem>
                                    <SelectItem class="text-xs" value="Complete">Complete</SelectItem>
                                    <SelectItem class="text-xs" value="Absent">Absent</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="attStatus === 'Complete'" class="grid gap-1">
                            <Label class="text-xs">
                                Completed Hours <span class="text-red-500">*</span>
                                <span class="text-muted-foreground font-normal">(max: {{ batchHours }} hr/s)</span>
                            </Label>
                            <Input type="number" step="0.5" min="0.5" :max="batchHours || undefined"
                                class="text-xs h-8" v-model="attHours" placeholder="e.g. 16" />
                            <p class="text-xs text-red-500">{{ attErrors.hours }}</p>
                        </div>

                        <div v-if="attStatus === 'Absent'" class="grid gap-1">
                            <Label class="text-xs">Justification Memo <span class="text-red-500">*</span></Label>
                            <input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                class="text-xs file:mr-2 file:rounded-md file:border-0 file:bg-blue-600 file:px-2.5 file:py-1.5 file:text-[11px] file:font-bold file:text-white hover:file:bg-blue-500 cursor-pointer"
                                @change="onFileChange" />
                            <p class="text-[11px] text-muted-foreground">PDF, Word, o image. Max 5MB.</p>
                            <p v-if="attendanceTarget?.justification && !attFile" class="text-[11px] text-slate-500">
                                May naka-upload nang memo. Mag-upload ulit para palitan ito.
                            </p>
                            <p class="text-xs text-red-500">{{ attErrors.justification }}</p>
                        </div>

                        <p v-if="attStatus === 'Pending'" class="text-[11px] text-muted-foreground">
                            Pending attendance resets the participant's hours to 0.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button variant="outline" size="sm" @click="showAttendance = false">Cancel</Button>
                        <Button v-if="attStatus === 'Complete'" variant="outline" size="sm"
                            :disabled="attProcessing" @click="applyToAll">
                            <LoaderCircle v-if="attProcessing" class="h-3 w-3 animate-spin mr-1" />
                            <Users v-else class="h-3.5 w-3.5 mr-1" />
                            Apply to all
                        </Button>
                        <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm"
                            :disabled="attProcessing" @click="submitAttendance">
                            <LoaderCircle v-if="attProcessing" class="h-3 w-3 animate-spin mr-1" />
                            <Save v-else class="h-3.5 w-3.5 mr-1" />
                            Save Attendance
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <BulkAddParticipants :open="showBulkAdd" :batch="batch" @update:open="showBulkAdd = $event" />

            <!-- ── Submissions Dialog ── -->
            <Dialog :open="showSubmissions" @update:open="showSubmissions = $event">
                <DialogContent class="max-w-2xl flex flex-col max-h-[90vh] overflow-hidden border-0 p-0 !rounded-2xl shadow-2xl gap-0">

                    <!-- Gradient header -->
                    <DialogHeader class="relative shrink-0 overflow-hidden bg-gradient-to-br from-blue-600 via-blue-500 to-indigo-500 px-6 pb-5 pt-5 text-left">
                        <div class="pointer-events-none absolute inset-0 opacity-20"
                            style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 18px 18px;"></div>
                        <div class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 rounded-full bg-white/10 blur-2xl"></div>

                        <div class="relative z-10 flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                                <ClipboardList class="h-5 w-5 text-white" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <DialogTitle class="text-base font-bold text-white truncate">
                                    {{ submissionsTarget?.employee?.name ?? submissionsTarget?.empcode }}
                                </DialogTitle>
                                <DialogDescription class="text-xs text-blue-100">
                                    Submissions — {{ batch?.batch }}
                                </DialogDescription>
                            </div>

                            <!-- Approved/total pill -->
                            <div v-if="requirements.length" class="relative z-10 shrink-0 rounded-xl bg-white/15 px-3 py-1.5 text-center ring-1 ring-white/25 backdrop-blur">
                                <p class="text-sm font-extrabold leading-none text-white">{{ submissionSummary.done }}/{{ submissionSummary.total }}</p>
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-blue-100">Approved</p>
                            </div>
                        </div>
                    </DialogHeader>

                    <div class="overflow-y-auto flex-1 px-5 py-4 bg-muted/20">
                        <!-- Empty: walang requirements -->
                        <div v-if="!requirements.length"
                            class="flex flex-col items-center justify-center rounded-2xl border border-dashed py-10 px-6 text-center gap-2 bg-card">
                            <svg viewBox="0 0 120 100" class="h-20 w-auto" xmlns="http://www.w3.org/2000/svg">
                                <ellipse cx="60" cy="92" rx="44" ry="6" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                                <rect x="22" y="10" width="76" height="76" rx="8" fill="currentColor" class="text-blue-100 dark:text-blue-900/40" />
                                <rect x="38" y="2" width="44" height="14" rx="4" fill="currentColor" class="text-blue-300 dark:text-blue-700/60" />
                                <rect x="34" y="34" width="8" height="8" rx="2" stroke="currentColor" stroke-width="2" fill="none" class="text-slate-300 dark:text-slate-600" />
                                <rect x="50" y="35" width="34" height="5" rx="2.5" fill="currentColor" class="text-slate-300 dark:text-slate-700" />
                                <rect x="34" y="52" width="8" height="8" rx="2" stroke="currentColor" stroke-width="2" fill="none" class="text-slate-300 dark:text-slate-600" />
                                <rect x="50" y="53" width="26" height="5" rx="2.5" fill="currentColor" class="text-slate-300 dark:text-slate-700" />
                            </svg>
                            <p class="text-sm font-bold text-slate-500">No requirements set</p>
                            <p class="text-xs text-slate-400 max-w-xs">Add requirements to this batch in the Requirements tab to track participant submissions here.</p>
                        </div>

                        <div v-else class="flex flex-col gap-2.5">
                            <div v-for="row in mergedSubmissions" :key="row.requirement.id"
                                class="rounded-xl border bg-card shadow-sm transition hover:shadow-md"
                                :class="statusMeta(row.submission?.status, !!row.submission).ring">

                                <!-- VIEW MODE -->
                                <div v-if="editingRow !== row.requirement.id" class="flex items-center justify-between gap-2 p-3">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <!-- Status icon chip -->
                                        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                            :class="statusMeta(row.submission?.status, !!row.submission).badge">
                                            <component :is="statusMeta(row.submission?.status, !!row.submission).icon" class="h-4 w-4" />
                                        </div>

                                        <div class="min-w-0">
                                            <p class="text-xs font-bold leading-4 truncate">
                                                {{ row.requirement.name }}
                                                <span class="text-[10px] font-semibold text-blue-500">({{ row.requirement.title }})</span>
                                            </p>
                                            <p class="text-[11px] text-muted-foreground">
                                                Due: {{ formatDueDate(row.requirement.due_date) }}
                                                <span v-if="!row.requirement.is_required" class="ml-1 text-slate-400">(optional)</span>
                                            </p>
                                            <p v-if="row.submission?.remarks" class="text-[11px] text-slate-500 mt-0.5 italic">
                                                "{{ row.submission.remarks }}"
                                            </p>
                                            <a v-if="row.submission?.file_path" :href="`/storage/${row.submission.file_path}`"
                                                target="_blank" class="inline-flex items-center gap-0.5 text-[11px] text-blue-600 hover:underline font-semibold mt-0.5">
                                                <FileText class="h-3 w-3" /> View file
                                            </a>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <Badge :class="statusMeta(row.submission?.status, !!row.submission).badge" class="text-[10px] font-bold border-0 gap-1">
                                            <component :is="statusMeta(row.submission?.status, !!row.submission).icon" class="h-3 w-3" />
                                            {{ statusMeta(row.submission?.status, !!row.submission).label }}
                                        </Badge>
                                        <Button variant="ghost" size="sm" class="h-7 px-2 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950/30" @click="startEdit(row)">
                                            <Upload class="h-3 w-3 mr-1" /> Set
                                        </Button>
                                        <Button v-if="row.submission" variant="ghost" size="sm"
                                            class="h-7 w-7 p-0 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                                            @click="deleteSubmission(row.submission.id)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>

                                <!-- EDIT MODE -->
                                <div v-else class="grid gap-2 p-3 bg-blue-50/50 dark:bg-blue-950/20 rounded-xl">
                                    <p class="text-xs font-bold leading-4 flex items-center gap-2">
                                        <CloudUpload class="h-4 w-4 text-blue-600" />
                                        {{ row.requirement.name }}
                                        <span class="text-[10px] font-semibold text-blue-500">({{ row.requirement.title }})</span>
                                        <Badge v-if="row.submission?.file_path" variant="outline" class="text-[9px] font-bold gap-0.5 border-emerald-300 text-emerald-700">
                                            <FileText class="h-3 w-3" /> File on record
                                        </Badge>
                                    </p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="grid gap-1">
                                            <Label class="text-xs flex items-center gap-1"><ClipboardCheck class="h-3 w-3 text-slate-400" /> Status</Label>
                                            <Select v-model="subStatus">
                                                <SelectTrigger class="text-xs h-8 bg-white dark:bg-slate-900"><SelectValue placeholder="Select status" /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem class="text-xs" value="Pending">Pending</SelectItem>
                                                    <SelectItem class="text-xs" value="Approved">Approved</SelectItem>
                                                    <SelectItem class="text-xs" value="Rejected">Rejected</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="grid gap-1">
                                            <Label class="text-xs flex items-center gap-1"><FileText class="h-3 w-3 text-slate-400" /> File (PDF)</Label>
                                            <input type="file" accept=".pdf"
                                                class="text-xs file:mr-2 file:rounded-md file:border-0 file:bg-blue-600 file:px-2.5 file:py-1.5 file:text-[11px] file:font-bold file:text-white hover:file:bg-blue-500 cursor-pointer"
                                                @change="onSubFileChange" />
                                            <p v-if="row.submission?.file_path && !subFile" class="text-[11px] text-slate-500">
                                                Naka-upload na. Mag-upload ulit para palitan.
                                            </p>
                                            <p class="text-xs text-red-500">{{ subErrors.file }}</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-1">
                                        <Label class="text-xs flex items-center gap-1"><ListFilter class="h-3 w-3 text-slate-400" /> Remarks</Label>
                                        <Input class="text-xs h-8 bg-white dark:bg-slate-900" v-model="subRemarks" placeholder="e.g. Needs revision on section 3" />
                                    </div>
                                    <div class="flex justify-end gap-2 pt-1">
                                        <Button variant="outline" size="sm" @click="cancelEdit">Cancel</Button>
                                        <Button class="bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-md shadow-blue-500/25 hover:from-blue-700 hover:to-blue-600" size="sm"
                                            :disabled="subProcessing" @click="saveSubmission(row)">
                                            <LoaderCircle v-if="subProcessing" class="h-3 w-3 animate-spin mr-1" />
                                            <Save v-else class="h-3.5 w-3.5 mr-1" />
                                            Save
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="shrink-0 flex justify-end px-5 py-3 border-t bg-card">
                        <Button variant="outline" size="sm" @click="showSubmissions = false">Close</Button>
                    </div>
                </DialogContent>
            </Dialog>

        </DialogContent>
    </Dialog>
</template>