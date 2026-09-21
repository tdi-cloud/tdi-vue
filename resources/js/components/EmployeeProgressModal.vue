<script setup lang="ts">
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import axios from 'axios';
import {
    AlertCircle,
    Award,
    BookOpen,
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Download,
    ExternalLink,
    FileText,
    Hash,
    MapPin,
    Search,
    Star,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Employee {
    EMPCODE: string;
    POSITION: string;
    'OFFICE/DIVISION': string;
    'PLANTILLA STATUS': string;
    REGION: string;
    name: string;
    initials: string;
    avatar_color: string;
    avatar: string | null;
}

interface Submission {
    id: number;
    status: string;
    file_path: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    reviewed_by: string | null;
    notes: string | null;
    remarks: string | null;
}

interface Requirement {
    id: number;
    title: string;
    description: string | null;
    required: string;
    submission: Submission | null;
}

interface EnrolledProgram {
    participant_id: number;
    batch_id: number;
    program_id: number;
    program_code: string;
    program_title: string;
    batch_label: string;
    date_start: string;
    date_end: string;
    hours: number;
    attendance: string;
    batch_status: string;
    is_completed: boolean;
    total_requirements: number;
    approved_submissions: number;
    pending_submissions: number;
    cover_image: string | null;
    requirements: Requirement[];
}

interface EmployeeStats {
    programs_attended: number;
    programs_completed: number;
    total_hours: number;
    total_submissions: number;
    not_approved: number;
    completion_rate: number;
}

interface EmployeeProgress {
    employee: Employee;
    stats: EmployeeStats;
    enrolled_programs: EnrolledProgram[];
}

const props = defineProps<{
    empcode: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const loading = ref(false);
const progress = ref<EmployeeProgress | null>(null);

/* ---------- Submission file preview ---------- */
const showFilePreview = ref(false);
const previewFile = ref<{ url: string; title?: string; description?: string } | null>(null);

const openFilePreview = (filePath: string, title?: string, description?: string) => {
    previewFile.value = { url: `/storage/${filePath}`, title, description };
    showFilePreview.value = true;
};

const activeReqs = ref<EnrolledProgram | null>(null);
const programSearch = ref('');
const programYear = ref('all');
const programPage = ref(1);
const programsPerPage = 5;

// Mula sa date_start ng bawat enrolled program — para sa Year filter dropdown.
const availableProgramYears = computed(() => {
    const years = new Set<string>();
    (progress.value?.enrolled_programs ?? []).forEach((p) => {
        if (p.date_start) years.add(String(new Date(p.date_start).getFullYear()));
    });
    return Array.from(years).sort().reverse();
});

const filteredPrograms = computed(() => {
    const q = programSearch.value.trim().toLowerCase();
    const programs = progress.value?.enrolled_programs ?? [];

    return programs.filter((p) => {
        if (programYear.value !== 'all' && String(new Date(p.date_start).getFullYear()) !== programYear.value) {
            return false;
        }
        if (!q) return true;
        return p.program_title.toLowerCase().includes(q) || p.program_code?.toLowerCase().includes(q) || p.batch_label?.toLowerCase().includes(q);
    });
});

const totalProgramPages = computed(() => Math.max(1, Math.ceil(filteredPrograms.value.length / programsPerPage)));

const paginatedPrograms = computed(() => {
    const start = (programPage.value - 1) * programsPerPage;
    return filteredPrograms.value.slice(start, start + programsPerPage);
});

// Bumalik sa page 1 tuwing nagbabago ang search/year filter, para hindi
// ma-stuck ang user sa isang page na wala nang laman.
watch([programSearch, programYear], () => {
    programPage.value = 1;
});

watch(
    () => props.empcode,
    async (code) => {
        progress.value = null;
        activeReqs.value = null;
        programSearch.value = '';
        programYear.value = 'all';
        programPage.value = 1;
        if (!code) return;

        loading.value = true;
        try {
            const res = await axios.get(route('employees.progress', { empcode: code }));
            progress.value = res.data;
        } catch (e: any) {
            console.error('Error:', e.response?.status, e.response?.data);
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

const close = () => emit('close');

// Helpers
const plantillaColor = (status: string) => {
    const s = status?.toUpperCase();
    if (s === 'PERMANENT') return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if (s === 'JOB ORDER') return 'bg-amber-100 text-amber-700 border-amber-200';
    if (s === 'CTI') return 'bg-blue-100 text-blue-700 border-blue-200';
    return 'bg-gray-100 text-gray-600 border-gray-200';
};

const plantillaDot = (status: string) => {
    const s = status?.toUpperCase();
    if (s === 'PERMANENT') return 'bg-emerald-500';
    if (s === 'JOB ORDER') return 'bg-amber-500';
    if (s === 'CTI') return 'bg-blue-500';
    return 'bg-gray-400';
};

const formatDate = (d?: string | null) => {
    if (!d) return '—';
    const date = new Date(d.includes('T') ? d : d + 'T00:00:00');
    return isNaN(date.getTime())
        ? d
        : date.toLocaleDateString('en-PH', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          });
};

const submissionStatusColor = (status?: string) => {
    if (status === 'Approved') return 'bg-emerald-100 text-emerald-700';
    if (status === 'Pending') return 'bg-amber-100 text-amber-700';
    if (status === 'Rejected') return 'bg-red-100 text-red-700';
    return 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <Teleport to="body">
        <div v-if="empcode" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" @click.self="close">
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-background shadow-2xl lg:max-w-6xl">
                <!-- Loading -->
                <div v-if="loading" class="flex items-center justify-center py-24">
                    <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent" />
                </div>

                <template v-else-if="progress">
                    <!-- Own scroll container so the native scrollbar is clipped by the
                     modal's rounded corners instead of poking past them. -->
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <!-- Modal Header -->
                        <div
                            class="sticky top-0 z-10 flex items-center justify-between rounded-t-2xl bg-gradient-to-r from-violet-600 to-purple-700 px-6 py-4 text-white"
                        >
                            <div class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                <span class="font-bold">Employee Training Details</span>
                            </div>
                            <button @click="close" class="text-white/70 transition-colors hover:text-white">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="flex flex-col gap-6 p-6">
                            <!-- Employee Card -->
                            <div class="flex items-center gap-4 rounded-xl border p-4">
                                <Avatar class="h-16 w-16 shrink-0 overflow-hidden rounded-xl" :class="progress.employee.avatar_color">
                                    <AvatarImage v-if="progress.employee.avatar" :src="progress.employee.avatar" :alt="progress.employee.name" />
                                    <AvatarFallback
                                        class="flex h-full w-full items-center justify-center rounded-xl bg-transparent text-xl font-extrabold text-white"
                                    >
                                        {{ progress.employee.initials }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-lg font-extrabold leading-tight">{{ progress.employee.name?.toUpperCase() }}</h2>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold"
                                            :class="plantillaColor(progress.employee['PLANTILLA STATUS'])"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full" :class="plantillaDot(progress.employee['PLANTILLA STATUS'])" />
                                            {{ progress.employee['PLANTILLA STATUS'] }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-muted-foreground">{{ progress.employee.POSITION }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                        <span class="flex items-center gap-1"
                                            ><Hash class="h-3 w-3" /> Emp Code:
                                            <strong class="text-foreground">{{ progress.employee.EMPCODE }}</strong></span
                                        >
                                        <span class="flex items-center gap-1"
                                            ><Building2 class="h-3 w-3" /> {{ progress.employee['OFFICE/DIVISION'] }}</span
                                        >
                                        <span class="flex items-center gap-1"><MapPin class="h-3 w-3" /> {{ progress.employee.REGION }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Program Overview + Post-Training Submissions on the left, Enrolled Programs on the right (large screens); stacked on small screens -->
                            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-5">
                                <div class="flex flex-col gap-6 lg:col-span-2">
                                    <!-- Program Overview -->
                                    <div>
                                        <p class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest text-muted-foreground">
                                            <FileText class="h-3.5 w-3.5" /> Program Overview
                                        </p>
                                        <div class="grid grid-cols-2 gap-3 md:grid-cols-2">
                                            <div class="flex items-center justify-between rounded-xl border p-3">
                                                <div>
                                                    <p class="text-2xl font-extrabold text-blue-600">{{ progress.stats.programs_attended }}</p>
                                                    <p class="mt-0.5 text-xs text-muted-foreground">Program(s)</p>
                                                </div>
                                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-950/40">
                                                    <Award class="h-5 w-5 text-blue-600" />
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl border p-3">
                                                <div>
                                                    <p class="text-2xl font-extrabold text-emerald-600">{{ progress.stats.programs_completed }}</p>
                                                    <p class="mt-0.5 text-xs text-muted-foreground">Programs Completed</p>
                                                </div>
                                                <div
                                                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-950/40"
                                                >
                                                    <CheckCircle2 class="h-5 w-5 text-emerald-600" />
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl border p-3">
                                                <div>
                                                    <p class="text-2xl font-extrabold text-amber-600">{{ progress.stats.total_hours }}</p>
                                                    <p class="mt-0.5 text-xs text-muted-foreground">Total Hours Rendered</p>
                                                </div>
                                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-950/40">
                                                    <Clock class="h-5 w-5 text-amber-600" />
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl border p-3">
                                                <div>
                                                    <p class="text-2xl font-extrabold text-violet-600">{{ progress.stats.completion_rate }}%</p>
                                                    <p class="mt-0.5 text-xs text-muted-foreground">Completion Rate</p>
                                                </div>
                                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-950/40">
                                                    <Star class="h-5 w-5 text-violet-600" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Post-Training Submissions -->
                                    <div>
                                        <p class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest text-muted-foreground">
                                            <FileText class="h-3.5 w-3.5" /> Post-Training Submissions
                                        </p>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div
                                                class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center dark:border-emerald-900 dark:bg-emerald-950/20"
                                            >
                                                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500">
                                                    <CheckCircle2 class="h-5 w-5 text-white" />
                                                </div>
                                                <p class="text-3xl font-extrabold text-emerald-700 dark:text-emerald-400">
                                                    {{ progress.stats.total_submissions }}
                                                </p>
                                                <p class="mt-0.5 text-xs font-semibold text-emerald-600">Submitted</p>
                                            </div>
                                            <div
                                                class="rounded-xl border border-red-200 bg-red-50 p-4 text-center dark:border-red-900 dark:bg-red-950/20"
                                            >
                                                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-red-500">
                                                    <AlertCircle class="h-5 w-5 text-white" />
                                                </div>
                                                <p class="text-3xl font-extrabold text-red-700 dark:text-red-400">
                                                    {{ progress.stats.not_approved }}
                                                </p>
                                                <p class="mt-0.5 text-xs font-semibold text-red-600">Not Yet Approved</p>
                                            </div>
                                        </div>

                                        <!-- Completion Rate Bar -->
                                        <div class="mb-1 mt-3 flex items-center justify-between text-xs">
                                            <span class="font-semibold">Completion Rate</span>
                                            <span class="font-bold">{{ progress.stats.completion_rate }}%</span>
                                        </div>
                                        <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                                            <div
                                                class="h-full rounded-full bg-emerald-500 transition-all"
                                                :style="{ width: progress.stats.completion_rate + '%' }"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Enrolled Programs -->
                                <div class="lg:col-span-3">
                                    <div class="mb-3 flex w-full items-center justify-between">
                                        <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest text-muted-foreground">
                                            <BookOpen class="h-3.5 w-3.5" /> Enrolled Programs
                                        </p>

                                        <a
                                            v-if="progress.enrolled_programs?.length"
                                            :href="route('employees.export', { empcode: progress.employee.EMPCODE })"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700"
                                        >
                                            <Download class="h-3.5 w-3.5" /> Export CSV
                                        </a>
                                    </div>

                                    <!-- Search + Year filter -->
                                    <div v-if="progress.enrolled_programs?.length" class="mb-3 flex items-center gap-2">
                                        <div class="relative flex-1">
                                            <Search class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                            <input
                                                v-model="programSearch"
                                                type="text"
                                                placeholder="Search enrolled programs..."
                                                class="w-full rounded-lg border bg-background py-2 pl-9 pr-3 text-xs focus:outline-none focus:ring-2 focus:ring-violet-500"
                                            />
                                        </div>
                                        <select
                                            v-model="programYear"
                                            class="shrink-0 rounded-lg border bg-background px-2 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-violet-500"
                                        >
                                            <option value="all">All Years</option>
                                            <option v-for="y in availableProgramYears" :key="y" :value="y">{{ y }}</option>
                                        </select>
                                    </div>

                                    <div v-if="progress.enrolled_programs?.length === 0" class="py-8 text-center text-muted-foreground">
                                        <BookOpen class="mx-auto mb-2 h-8 w-8 opacity-30" />
                                        <p class="text-sm">No enrolled programs yet.</p>
                                    </div>

                                    <div v-else-if="filteredPrograms.length === 0" class="py-8 text-center text-muted-foreground">
                                        <Search class="mx-auto mb-2 h-8 w-8 opacity-30" />
                                        <p class="text-sm">No programs match the current search/year filter.</p>
                                    </div>

                                    <div v-else class="flex flex-col gap-2">
                                        <div
                                            v-for="prog in paginatedPrograms"
                                            :key="prog.participant_id"
                                            class="cursor-pointer rounded-xl border p-4 transition-colors hover:border-blue-300"
                                            @click="activeReqs = activeReqs?.participant_id === prog.participant_id ? null : prog"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                                    <div
                                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-950/40"
                                                    >
                                                        <BookOpen class="h-5 w-5 text-emerald-600" />
                                                    </div>
                                                    <div class="min-w-0">
                                                        <a
                                                            :href="`/programs/${prog.program_id}`"
                                                            class="block max-w-xs truncate text-sm font-semibold leading-tight transition-colors hover:text-blue-600 hover:underline"
                                                            @click.stop
                                                            target="_blank"
                                                        >
                                                            {{ prog.program_title }}
                                                        </a>
                                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                                            {{ formatDate(prog.date_start) }} – {{ formatDate(prog.date_end) }}
                                                            <span v-if="prog.hours"> · {{ prog.hours }} hrs</span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex shrink-0 items-start gap-3">
                                                    <div v-if="prog.total_requirements > 0" class="flex flex-col items-center gap-1">
                                                        <span class="text-[9px] font-bold uppercase tracking-wide text-muted-foreground"
                                                            >Requirements</span
                                                        >
                                                        <span
                                                            class="flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                                            :class="
                                                                prog.approved_submissions >= prog.total_requirements
                                                                    ? 'bg-emerald-100 text-emerald-700'
                                                                    : 'bg-amber-100 text-amber-700'
                                                            "
                                                        >
                                                            <CheckCircle2 class="h-3 w-3" />
                                                            {{ prog.approved_submissions }}/{{ prog.total_requirements }} approved
                                                        </span>
                                                    </div>
                                                    <div class="flex flex-col items-center gap-1">
                                                        <span class="text-[9px] font-bold uppercase tracking-wide text-muted-foreground">Status</span>
                                                        <span
                                                            class="rounded-full px-2.5 py-1 text-xs font-bold"
                                                            :class="
                                                                prog.is_completed
                                                                    ? 'bg-emerald-600 text-white'
                                                                    : prog.attendance === 'Complete'
                                                                      ? 'bg-blue-100 text-blue-700'
                                                                      : prog.attendance === 'Absent'
                                                                        ? 'bg-red-100 text-red-700'
                                                                        : 'bg-amber-100 text-amber-700'
                                                            "
                                                        >
                                                            {{ prog.is_completed ? 'COMPLETED' : prog.attendance?.toUpperCase() }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Requirements breakdown (expandable) -->
                                            <div
                                                v-if="activeReqs?.participant_id === prog.participant_id && prog.requirements?.length > 0"
                                                class="mt-4 border-t pt-4"
                                            >
                                                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-muted-foreground">Requirements</p>

                                                <!-- Tabs -->
                                                <div class="mb-3 flex items-center gap-3 text-xs font-semibold">
                                                    <span class="text-muted-foreground">All {{ prog.requirements?.length }}</span>
                                                    <span class="text-emerald-600">Approved {{ prog.approved_submissions }}</span>
                                                    <span class="text-amber-600">Not Approved {{ prog.pending_submissions }}</span>
                                                </div>

                                                <div class="flex flex-col gap-2">
                                                    <div
                                                        v-for="req in prog.requirements"
                                                        :key="req.id"
                                                        class="flex items-center justify-between gap-3 rounded-lg bg-muted/30 p-2.5 text-sm"
                                                    >
                                                        <div class="flex min-w-0 items-center gap-2">
                                                            <div
                                                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
                                                                :class="
                                                                    req.submission?.status === 'Approved'
                                                                        ? 'bg-emerald-100'
                                                                        : req.submission?.status === 'Rejected'
                                                                          ? 'bg-red-100'
                                                                          : req.submission?.status === 'Pending'
                                                                            ? 'bg-amber-100'
                                                                            : 'bg-gray-100'
                                                                "
                                                            >
                                                                <CheckCircle2
                                                                    v-if="req.submission?.status === 'Approved'"
                                                                    class="h-3.5 w-3.5 text-emerald-600"
                                                                />
                                                                <Clock
                                                                    v-else-if="req.submission?.status === 'Pending'"
                                                                    class="h-3.5 w-3.5 text-amber-600"
                                                                />
                                                                <X
                                                                    v-else-if="req.submission?.status === 'Rejected'"
                                                                    class="h-3.5 w-3.5 text-red-500"
                                                                />
                                                                <AlertCircle v-else class="h-3.5 w-3.5 text-gray-400" />
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="truncate font-semibold">{{ req.title }}</p>
                                                                <p v-if="req.submission?.submitted_at" class="text-[10px] text-muted-foreground">
                                                                    Submitted: {{ formatDate(req.submission?.submitted_at) }}
                                                                    <template v-if="req.submission.reviewed_by">
                                                                        · Reviewed by {{ req.submission.reviewed_by }}
                                                                    </template>
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="flex shrink-0 items-center gap-2">
                                                            <span
                                                                class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                                                                :class="submissionStatusColor(req.submission?.status)"
                                                            >
                                                                {{ req.submission?.status ?? 'Not Submitted' }}
                                                            </span>

                                                            <button
                                                                v-if="req.submission?.file_path"
                                                                type="button"
                                                                class="text-blue-600 hover:text-blue-700"
                                                                title="View File"
                                                                @click.stop="openFilePreview(req.submission.file_path, req.title)"
                                                            >
                                                                <ExternalLink class="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pagination -->
                                    <div
                                        v-if="filteredPrograms.length > programsPerPage"
                                        class="mt-4 flex items-center justify-between border-t pt-3 text-xs"
                                    >
                                        <p class="text-muted-foreground">
                                            Showing {{ (programPage - 1) * programsPerPage + 1 }}–{{
                                                Math.min(programPage * programsPerPage, filteredPrograms.length)
                                            }}
                                            of {{ filteredPrograms.length }}
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                :disabled="programPage <= 1"
                                                class="inline-flex items-center gap-1 rounded-lg border px-2 py-1 font-semibold transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                                @click="programPage--"
                                            >
                                                <ChevronLeft class="h-3.5 w-3.5" /> Prev
                                            </button>
                                            <span class="font-semibold">Page {{ programPage }} of {{ totalProgramPages }}</span>
                                            <button
                                                type="button"
                                                :disabled="programPage >= totalProgramPages"
                                                class="inline-flex items-center gap-1 rounded-lg border px-2 py-1 font-semibold transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                                @click="programPage++"
                                            >
                                                Next <ChevronRight class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="border-t px-6 py-4 text-right">
                            <button @click="close" class="text-sm font-semibold text-muted-foreground transition-colors hover:text-foreground">
                                Close
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>

    <FilePreviewModal
        :open="showFilePreview"
        :file-url="previewFile?.url ?? null"
        :title="previewFile?.title"
        :description="previewFile?.description"
        @update:open="showFilePreview = $event"
    />
</template>
