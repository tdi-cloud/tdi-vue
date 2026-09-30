<script setup lang="ts">
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import axios from 'axios';
import {
    AlertCircle,
    Award,
    BookOpen,
    Building2,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CircleDashed,
    CircleX,
    Clock,
    Download,
    ExternalLink,
    FileCheck2,
    FileText,
    GraduationCap,
    Hash,
    MapPin,
    Search,
    Star,
    TrendingUp,
    UserCheck,
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
    if (status === 'Approved')
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
    if (status === 'Pending') return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';
    if (status === 'Rejected') return 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300';
    return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300';
};

// Icon + icon-chip tint per submission status, so status stays readable without relying on color alone.
const requirementStatusIcon = (status?: string) => {
    if (status === 'Approved') return { icon: CheckCircle2, chip: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' };
    if (status === 'Pending') return { icon: Clock, chip: 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' };
    if (status === 'Rejected') return { icon: CircleX, chip: 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400' };
    return { icon: CircleDashed, chip: 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500' };
};

// Same branching as the original status pill: completed → attendance Complete → Absent → everything else (pending).
const programStatus = (prog: EnrolledProgram) => {
    if (prog.is_completed) {
        return {
            icon: CheckCircle2,
            badge: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300',
            chip: 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-emerald-500/30',
            accent: 'bg-emerald-500',
        };
    }
    if (prog.attendance === 'Complete') {
        return {
            icon: UserCheck,
            badge: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300',
            chip: 'bg-gradient-to-br from-blue-400 to-blue-600 text-white shadow-blue-500/30',
            accent: 'bg-blue-500',
        };
    }
    if (prog.attendance === 'Absent') {
        return {
            icon: CircleX,
            badge: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300',
            chip: 'bg-gradient-to-br from-red-400 to-red-600 text-white shadow-red-500/30',
            accent: 'bg-red-500',
        };
    }
    return {
        icon: Clock,
        badge: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300',
        chip: 'bg-gradient-to-br from-amber-400 to-amber-500 text-white shadow-amber-500/30',
        accent: 'bg-amber-400',
    };
};

const requirementPercent = (prog: EnrolledProgram) =>
    prog.total_requirements > 0 ? Math.min(100, Math.round((prog.approved_submissions / prog.total_requirements) * 100)) : 0;

const toggleProgram = (prog: EnrolledProgram) => {
    activeReqs.value = activeReqs.value?.participant_id === prog.participant_id ? null : prog;
};

/* ---------- Completion ring ---------- */
const RING_RADIUS = 42;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;

const completionDashOffset = computed(() => {
    const rate = Math.min(100, Math.max(0, progress.value?.stats.completion_rate ?? 0));
    return RING_CIRCUMFERENCE * (1 - rate / 100);
});

const sectionLabelClass = 'flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400';
const sectionIconClass = 'flex h-6 w-6 items-center justify-center rounded-lg';
const kpiCardClass =
    'group/kpi relative overflow-hidden rounded-2xl border bg-gradient-to-br p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md dark:to-slate-900';
const kpiIconClass = 'relative flex h-9 w-9 items-center justify-center rounded-xl';
const kpiGlowClass = 'pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full blur-2xl transition-opacity group-hover/kpi:opacity-100';
const toolbarControlClass =
    'h-10 w-full rounded-xl border border-slate-200 bg-white text-sm text-slate-700 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:focus:border-violet-500 dark:focus:ring-violet-500/20';
const pagerButtonClass =
    'inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 font-semibold text-slate-600 shadow-sm transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none disabled:hover:border-slate-200 disabled:hover:bg-white disabled:hover:text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-violet-500/40 dark:hover:bg-violet-500/10 dark:hover:text-violet-300';
</script>

<template>
    <Teleport to="body">
        <Transition name="epm" appear>
            <div
                v-if="empcode"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-2 backdrop-blur-sm sm:p-4"
                @click.self="close"
            >
                <div
                    class="epm-panel relative flex max-h-[92vh] w-full max-w-7xl flex-col overflow-hidden rounded-3xl border border-white/60 bg-white shadow-[0_32px_80px_-16px_rgba(30,27,75,0.45)] dark:border-slate-800 dark:bg-slate-900"
                >
                    <!-- Always-visible close -->
                    <button
                        type="button"
                        class="absolute right-3 top-3 z-20 flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:right-4 sm:top-4"
                        aria-label="Close"
                        @click="close"
                    >
                        <X class="h-4 w-4" />
                    </button>

                    <!-- Loading -->
                    <div
                        v-if="loading"
                        class="flex flex-col items-center justify-center gap-3 bg-gradient-to-br from-blue-600 via-violet-600 to-purple-700 py-28"
                    >
                        <div class="h-9 w-9 animate-spin rounded-full border-[3px] border-white/25 border-t-white" />
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-white/80">Loading training profile</p>
                    </div>

                    <template v-else-if="progress">
                        <!-- Own scroll container so the native scrollbar is clipped by the
                         modal's rounded corners instead of poking past them. -->
                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <!-- ===== Hero ===== -->
                            <header class="relative overflow-hidden bg-gradient-to-br from-blue-600 via-violet-600 to-purple-700 text-white">
                                <span class="pointer-events-none absolute -left-16 -top-24 h-64 w-64 rounded-full bg-cyan-300/25 blur-3xl" />
                                <span class="pointer-events-none absolute -bottom-28 right-10 h-72 w-72 rounded-full bg-fuchsia-400/25 blur-3xl" />
                                <span class="pointer-events-none absolute -right-20 -top-20 h-60 w-60 rounded-full border border-white/10" />
                                <span class="pointer-events-none absolute -right-6 -top-6 h-32 w-32 rounded-full border border-white/10" />

                                <div class="relative flex flex-col gap-5 px-5 py-6 sm:flex-row sm:items-center sm:gap-7 sm:px-8 sm:py-8">
                                    <Avatar
                                        class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl shadow-xl shadow-violet-950/30 ring-4 ring-white/25 sm:h-24 sm:w-24"
                                        :class="progress.employee.avatar_color"
                                    >
                                        <AvatarImage v-if="progress.employee.avatar" :src="progress.employee.avatar" :alt="progress.employee.name" />
                                        <AvatarFallback
                                            class="flex h-full w-full items-center justify-center rounded-2xl bg-transparent text-2xl font-extrabold text-white sm:text-3xl"
                                        >
                                            {{ progress.employee.initials }}
                                        </AvatarFallback>
                                    </Avatar>

                                    <div class="min-w-0 flex-1 pr-8">
                                        <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/70">
                                            <FileText class="h-3.5 w-3.5 text-cyan-200" />
                                            Employee Training Profile
                                        </p>
                                        <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                                            <h2 class="text-2xl font-extrabold leading-tight tracking-tight drop-shadow-sm sm:text-3xl">
                                                {{ progress.employee.name?.toUpperCase() }}
                                            </h2>
                                            <span
                                                class="inline-flex w-fit items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-bold tracking-wide shadow-sm"
                                                :class="plantillaColor(progress.employee['PLANTILLA STATUS'])"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full" :class="plantillaDot(progress.employee['PLANTILLA STATUS'])" />
                                                {{ progress.employee['PLANTILLA STATUS'] }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm font-medium text-white/85 sm:text-base">{{ progress.employee.POSITION }}</p>

                                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-2.5 py-1 ring-1 ring-white/15">
                                                <Hash class="h-3.5 w-3.5 text-cyan-200" />
                                                <span class="font-mono font-semibold">{{ progress.employee.EMPCODE }}</span>
                                            </span>
                                            <span
                                                class="inline-flex min-w-0 items-center gap-1.5 rounded-lg bg-white/10 px-2.5 py-1 ring-1 ring-white/15"
                                            >
                                                <Building2 class="h-3.5 w-3.5 shrink-0 text-cyan-200" />
                                                <span class="truncate">{{ progress.employee['OFFICE/DIVISION'] }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-2.5 py-1 ring-1 ring-white/15">
                                                <MapPin class="h-3.5 w-3.5 text-cyan-200" />
                                                {{ progress.employee.REGION }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </header>

                            <!-- ===== Body ===== -->
                            <div class="grid grid-cols-1 items-start gap-8 p-4 sm:p-6 lg:grid-cols-12 lg:p-8">
                                <!-- Left column: overview + submissions -->
                                <div class="flex flex-col gap-8 lg:col-span-5 xl:col-span-4">
                                    <!-- Training Overview -->
                                    <section>
                                        <h3 :class="sectionLabelClass">
                                            <span :class="[sectionIconClass, 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400']">
                                                <TrendingUp class="h-3.5 w-3.5" />
                                            </span>
                                            Training Overview
                                        </h3>

                                        <div class="mt-3 grid grid-cols-2 gap-3">
                                            <!-- Programs attended -->
                                            <div
                                                :class="[
                                                    kpiCardClass,
                                                    'border-blue-100 from-blue-50 to-white dark:border-blue-500/15 dark:from-blue-500/10',
                                                ]"
                                            >
                                                <span :class="[kpiGlowClass, 'bg-blue-300/40 opacity-70 dark:bg-blue-500/20']" />
                                                <div class="relative flex items-start justify-between gap-2">
                                                    <span
                                                        class="text-[10px] font-bold uppercase tracking-wider text-blue-700/80 dark:text-blue-300/80"
                                                        >Programs Attended</span
                                                    >
                                                    <span :class="[kpiIconClass, 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300']">
                                                        <Award class="h-4 w-4" />
                                                    </span>
                                                </div>
                                                <p class="relative mt-2 text-3xl font-extrabold tabular-nums text-blue-700 dark:text-blue-300">
                                                    {{ progress.stats.programs_attended }}
                                                </p>
                                                <p class="relative text-xs text-slate-500 dark:text-slate-400">programs</p>
                                            </div>

                                            <!-- Programs completed -->
                                            <div
                                                :class="[
                                                    kpiCardClass,
                                                    'border-emerald-100 from-emerald-50 to-white dark:border-emerald-500/15 dark:from-emerald-500/10',
                                                ]"
                                            >
                                                <span :class="[kpiGlowClass, 'bg-emerald-300/40 opacity-70 dark:bg-emerald-500/20']" />
                                                <div class="relative flex items-start justify-between gap-2">
                                                    <span
                                                        class="text-[10px] font-bold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/80"
                                                        >Completed</span
                                                    >
                                                    <span
                                                        :class="[
                                                            kpiIconClass,
                                                            'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
                                                        ]"
                                                    >
                                                        <CheckCircle2 class="h-4 w-4" />
                                                    </span>
                                                </div>
                                                <p class="relative mt-2 text-3xl font-extrabold tabular-nums text-emerald-600 dark:text-emerald-300">
                                                    {{ progress.stats.programs_completed }}
                                                </p>
                                                <p class="relative text-xs text-slate-500 dark:text-slate-400">programs</p>
                                            </div>

                                            <!-- Learning hours -->
                                            <div
                                                :class="[
                                                    kpiCardClass,
                                                    'border-amber-100 from-amber-50 to-white dark:border-amber-500/15 dark:from-amber-500/10',
                                                ]"
                                            >
                                                <span :class="[kpiGlowClass, 'bg-amber-300/40 opacity-70 dark:bg-amber-500/20']" />
                                                <div class="relative flex items-start justify-between gap-2">
                                                    <span
                                                        class="text-[10px] font-bold uppercase tracking-wider text-amber-700/80 dark:text-amber-300/80"
                                                        >Learning Hours</span
                                                    >
                                                    <span
                                                        :class="[
                                                            kpiIconClass,
                                                            'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
                                                        ]"
                                                    >
                                                        <Clock class="h-4 w-4" />
                                                    </span>
                                                </div>
                                                <p class="relative mt-2 text-3xl font-extrabold tabular-nums text-amber-600 dark:text-amber-300">
                                                    {{ progress.stats.total_hours }}
                                                </p>
                                                <p class="relative text-xs text-slate-500 dark:text-slate-400">hours rendered</p>
                                            </div>

                                            <!-- Completion rate -->
                                            <div
                                                :class="[
                                                    kpiCardClass,
                                                    'border-violet-100 from-violet-50 to-white dark:border-violet-500/15 dark:from-violet-500/10',
                                                ]"
                                            >
                                                <span :class="[kpiGlowClass, 'bg-violet-300/40 opacity-70 dark:bg-violet-500/20']" />
                                                <div class="relative flex items-start justify-between gap-2">
                                                    <span
                                                        class="text-[10px] font-bold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/80"
                                                        >Completion Rate</span
                                                    >
                                                    <span
                                                        :class="[
                                                            kpiIconClass,
                                                            'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
                                                        ]"
                                                    >
                                                        <Star class="h-4 w-4" />
                                                    </span>
                                                </div>
                                                <p class="relative mt-2 text-3xl font-extrabold tabular-nums text-violet-600 dark:text-violet-300">
                                                    {{ progress.stats.completion_rate }}%
                                                </p>
                                                <p class="relative text-xs text-slate-500 dark:text-slate-400">progress</p>
                                            </div>
                                        </div>

                                        <!-- Completion ring -->
                                        <div
                                            class="mt-3 flex items-center gap-5 rounded-2xl border border-violet-100 bg-gradient-to-r from-violet-50 via-white to-white p-4 shadow-sm dark:border-violet-500/15 dark:from-violet-500/10 dark:via-slate-900 dark:to-slate-900"
                                        >
                                            <div class="relative h-24 w-24 shrink-0">
                                                <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" aria-hidden="true">
                                                    <defs>
                                                        <linearGradient id="epm-ring-gradient" x1="0" y1="0" x2="1" y2="1">
                                                            <stop offset="0%" stop-color="#8b5cf6" />
                                                            <stop offset="100%" stop-color="#6366f1" />
                                                        </linearGradient>
                                                    </defs>
                                                    <circle
                                                        cx="50"
                                                        cy="50"
                                                        :r="RING_RADIUS"
                                                        fill="none"
                                                        stroke-width="9"
                                                        class="stroke-violet-100 dark:stroke-violet-500/15"
                                                    />
                                                    <circle
                                                        v-if="progress.stats.completion_rate > 0"
                                                        cx="50"
                                                        cy="50"
                                                        :r="RING_RADIUS"
                                                        fill="none"
                                                        stroke="url(#epm-ring-gradient)"
                                                        stroke-width="9"
                                                        stroke-linecap="round"
                                                        class="ring-arc"
                                                        :stroke-dasharray="RING_CIRCUMFERENCE"
                                                        :stroke-dashoffset="completionDashOffset"
                                                    />
                                                </svg>
                                                <span
                                                    class="absolute inset-0 flex items-center justify-center text-xl font-extrabold tabular-nums text-violet-700 dark:text-violet-300"
                                                >
                                                    {{ progress.stats.completion_rate }}%
                                                </span>
                                            </div>
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/80">
                                                    Training Completion
                                                </p>
                                                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                    {{ progress.stats.programs_completed }} of {{ progress.stats.programs_attended }} programs
                                                </p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">completed to date</p>
                                            </div>
                                        </div>
                                    </section>

                                    <!-- Post-Training Submissions -->
                                    <section>
                                        <h3 :class="sectionLabelClass">
                                            <span
                                                :class="[
                                                    sectionIconClass,
                                                    'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
                                                ]"
                                            >
                                                <FileCheck2 class="h-3.5 w-3.5" />
                                            </span>
                                            Post-Training Submissions
                                        </h3>
                                        <div class="mt-3 grid grid-cols-2 gap-3">
                                            <div
                                                class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-4 text-center shadow-sm dark:border-emerald-500/15 dark:from-emerald-500/10 dark:to-slate-900"
                                            >
                                                <span
                                                    class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-md shadow-emerald-500/30"
                                                >
                                                    <CheckCircle2 class="h-5 w-5" />
                                                </span>
                                                <p class="mt-3 text-3xl font-extrabold tabular-nums text-emerald-700 dark:text-emerald-300">
                                                    {{ progress.stats.total_submissions }}
                                                </p>
                                                <p class="mt-0.5 text-xs font-semibold text-emerald-700/80 dark:text-emerald-300/80">Submitted</p>
                                            </div>
                                            <div
                                                class="rounded-2xl border border-red-100 bg-gradient-to-br from-red-50 to-white p-4 text-center shadow-sm dark:border-red-500/15 dark:from-red-500/10 dark:to-slate-900"
                                            >
                                                <span
                                                    class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-rose-400 to-red-600 text-white shadow-md shadow-red-500/30"
                                                >
                                                    <AlertCircle class="h-5 w-5" />
                                                </span>
                                                <p class="mt-3 text-3xl font-extrabold tabular-nums text-red-600 dark:text-red-300">
                                                    {{ progress.stats.not_approved }}
                                                </p>
                                                <p class="mt-0.5 text-xs font-semibold text-red-600/80 dark:text-red-300/80">Not Yet Approved</p>
                                            </div>
                                        </div>
                                    </section>
                                </div>

                                <!-- ===== Enrolled Programs ===== -->
                                <section class="lg:col-span-7 xl:col-span-8">
                                    <div class="flex items-center justify-between gap-3">
                                        <h3 :class="sectionLabelClass">
                                            <span
                                                :class="[
                                                    sectionIconClass,
                                                    'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400',
                                                ]"
                                            >
                                                <BookOpen class="h-3.5 w-3.5" />
                                            </span>
                                            Enrolled Programs
                                            <span
                                                v-if="progress.enrolled_programs?.length"
                                                class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] tracking-normal text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                                            >
                                                {{ progress.enrolled_programs.length }}
                                            </span>
                                        </h3>

                                        <a
                                            v-if="progress.enrolled_programs?.length"
                                            :href="route('employees.export', { empcode: progress.employee.EMPCODE })"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-emerald-500/25 transition hover:-translate-y-px hover:from-emerald-600 hover:to-emerald-700 hover:shadow-lg"
                                        >
                                            <Download class="h-3.5 w-3.5" /> Export CSV
                                        </a>
                                    </div>

                                    <!-- Search + Year filter -->
                                    <div v-if="progress.enrolled_programs?.length" class="mt-3 flex flex-col gap-2 sm:flex-row">
                                        <div class="relative flex-1">
                                            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-400" />
                                            <input
                                                v-model="programSearch"
                                                type="text"
                                                placeholder="Search enrolled programs..."
                                                :class="[toolbarControlClass, 'pl-10 pr-3']"
                                            />
                                        </div>
                                        <div class="relative sm:w-40">
                                            <select v-model="programYear" :class="[toolbarControlClass, 'appearance-none pl-3 pr-9 font-medium']">
                                                <option value="all">All Years</option>
                                                <option v-for="y in availableProgramYears" :key="y" :value="y">{{ y }}</option>
                                            </select>
                                            <ChevronDown
                                                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        v-if="progress.enrolled_programs?.length === 0"
                                        class="mt-3 flex flex-col items-center rounded-2xl border border-dashed border-violet-200 bg-violet-50/40 px-4 py-12 text-center dark:border-violet-500/20 dark:bg-violet-500/5"
                                    >
                                        <span
                                            class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-violet-500 dark:bg-violet-500/15 dark:text-violet-300"
                                        >
                                            <BookOpen class="h-5 w-5" />
                                        </span>
                                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No enrolled programs yet.</p>
                                    </div>

                                    <div
                                        v-else-if="filteredPrograms.length === 0"
                                        class="mt-3 flex flex-col items-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-12 text-center dark:border-slate-700 dark:bg-slate-800/30"
                                    >
                                        <span
                                            class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800"
                                        >
                                            <Search class="h-5 w-5" />
                                        </span>
                                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            No programs match the current search/year filter.
                                        </p>
                                    </div>

                                    <div v-else class="mt-3 flex flex-col gap-3">
                                        <div
                                            v-for="prog in paginatedPrograms"
                                            :key="prog.participant_id"
                                            role="button"
                                            tabindex="0"
                                            :aria-expanded="activeReqs?.participant_id === prog.participant_id"
                                            class="group relative cursor-pointer overflow-hidden rounded-2xl border bg-white p-4 pl-5 transition-all duration-200 focus:outline-none focus-visible:ring-4 focus-visible:ring-violet-500/15 dark:bg-slate-900 sm:p-5 sm:pl-6"
                                            :class="
                                                activeReqs?.participant_id === prog.participant_id
                                                    ? 'border-violet-200 shadow-lg shadow-violet-500/10 dark:border-violet-500/30'
                                                    : 'border-slate-200/80 shadow-sm hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md dark:border-slate-800 dark:hover:border-blue-500/30'
                                            "
                                            @click="toggleProgram(prog)"
                                            @keydown.enter.self="toggleProgram(prog)"
                                        >
                                            <!-- Status accent strip -->
                                            <span class="absolute inset-y-0 left-0 w-1" :class="programStatus(prog).accent" />

                                            <div class="flex gap-4">
                                                <span
                                                    class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-md sm:flex"
                                                    :class="programStatus(prog).chip"
                                                >
                                                    <GraduationCap class="h-5 w-5" />
                                                </span>

                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                                        <div class="min-w-0">
                                                            <a
                                                                :href="`/programs/${prog.program_id}`"
                                                                class="line-clamp-2 text-[15px] font-semibold leading-snug text-slate-900 transition-colors hover:text-blue-600 hover:underline dark:text-slate-100 dark:hover:text-blue-300"
                                                                @click.stop
                                                                target="_blank"
                                                            >
                                                                {{ prog.program_title }}
                                                            </a>
                                                            <p
                                                                v-if="prog.program_code || prog.batch_label"
                                                                class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-slate-500 dark:text-slate-400"
                                                            >
                                                                <span
                                                                    v-if="prog.program_code"
                                                                    class="font-mono text-blue-600/80 dark:text-blue-300/80"
                                                                    >{{ prog.program_code }}</span
                                                                >
                                                                <span
                                                                    v-if="prog.program_code && prog.batch_label"
                                                                    class="text-slate-300 dark:text-slate-600"
                                                                    >·</span
                                                                >
                                                                <span v-if="prog.batch_label">{{ prog.batch_label }}</span>
                                                            </p>
                                                        </div>
                                                        <span
                                                            class="inline-flex w-fit shrink-0 items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-bold tracking-wide"
                                                            :class="programStatus(prog).badge"
                                                        >
                                                            <component :is="programStatus(prog).icon" class="h-3.5 w-3.5" />
                                                            {{ prog.is_completed ? 'COMPLETED' : prog.attendance?.toUpperCase() }}
                                                        </span>
                                                    </div>

                                                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                                        <span
                                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2 py-1 dark:bg-blue-500/10"
                                                        >
                                                            <CalendarDays class="h-3.5 w-3.5 text-blue-500" />
                                                            {{ formatDate(prog.date_start) }} – {{ formatDate(prog.date_end) }}
                                                        </span>
                                                        <span
                                                            v-if="prog.hours"
                                                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-2 py-1 dark:bg-amber-500/10"
                                                        >
                                                            <Clock class="h-3.5 w-3.5 text-amber-500" /> {{ prog.hours }} hrs
                                                        </span>
                                                    </div>

                                                    <div v-if="prog.total_requirements > 0" class="mt-4">
                                                        <div class="mb-1.5 flex items-center justify-between text-xs">
                                                            <span class="font-medium text-slate-500 dark:text-slate-400">Requirements</span>
                                                            <span
                                                                class="font-bold tabular-nums"
                                                                :class="
                                                                    prog.approved_submissions >= prog.total_requirements
                                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                                        : 'text-amber-600 dark:text-amber-400'
                                                                "
                                                            >
                                                                {{ prog.approved_submissions }} / {{ prog.total_requirements }} Approved
                                                            </span>
                                                        </div>
                                                        <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                            <div
                                                                class="h-full rounded-full bg-gradient-to-r transition-[width] duration-700 ease-out"
                                                                :class="
                                                                    prog.approved_submissions >= prog.total_requirements
                                                                        ? 'from-emerald-400 to-emerald-500'
                                                                        : 'from-amber-300 to-amber-500'
                                                                "
                                                                :style="{ width: requirementPercent(prog) + '%' }"
                                                            />
                                                        </div>
                                                    </div>

                                                    <div
                                                        v-if="prog.requirements?.length > 0"
                                                        class="mt-3 flex items-center justify-between text-xs font-medium text-slate-400 transition-colors group-hover:text-violet-600 dark:group-hover:text-violet-300"
                                                    >
                                                        <span>
                                                            {{
                                                                activeReqs?.participant_id === prog.participant_id
                                                                    ? 'Hide requirements'
                                                                    : 'Click to view requirements'
                                                            }}
                                                        </span>
                                                        <ChevronDown
                                                            class="h-4 w-4 transition-transform duration-200"
                                                            :class="{ 'rotate-180': activeReqs?.participant_id === prog.participant_id }"
                                                        />
                                                    </div>

                                                    <!-- Requirements breakdown (expandable) -->
                                                    <Transition
                                                        enter-active-class="transition duration-200 ease-out"
                                                        enter-from-class="opacity-0 -translate-y-1"
                                                        leave-active-class="transition duration-150 ease-in"
                                                        leave-to-class="opacity-0"
                                                    >
                                                        <div
                                                            v-if="activeReqs?.participant_id === prog.participant_id && prog.requirements?.length > 0"
                                                            class="mt-4 rounded-xl border border-violet-100 bg-gradient-to-br from-violet-50/70 via-white to-blue-50/50 p-3 dark:border-violet-500/15 dark:from-violet-500/[0.07] dark:via-slate-900 dark:to-blue-500/[0.05] sm:p-4"
                                                        >
                                                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                                <p
                                                                    class="text-[11px] font-bold uppercase tracking-[0.14em] text-violet-700/80 dark:text-violet-300/80"
                                                                >
                                                                    Requirements
                                                                </p>
                                                                <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-semibold">
                                                                    <span
                                                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                                                        >All {{ prog.requirements?.length }}</span
                                                                    >
                                                                    <span
                                                                        class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                                                        >Approved {{ prog.approved_submissions }}</span
                                                                    >
                                                                    <span
                                                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                                                                        >Not Approved {{ prog.pending_submissions }}</span
                                                                    >
                                                                </div>
                                                            </div>

                                                            <ul
                                                                class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900"
                                                            >
                                                                <li
                                                                    v-for="req in prog.requirements"
                                                                    :key="req.id"
                                                                    class="flex flex-col gap-2 px-3 py-3 transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                                                >
                                                                    <div class="flex min-w-0 items-start gap-3">
                                                                        <span
                                                                            class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                                                                            :class="requirementStatusIcon(req.submission?.status).chip"
                                                                        >
                                                                            <component
                                                                                :is="requirementStatusIcon(req.submission?.status).icon"
                                                                                class="h-4 w-4"
                                                                            />
                                                                        </span>
                                                                        <div class="min-w-0">
                                                                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                                                {{ req.title }}
                                                                            </p>
                                                                            <p
                                                                                v-if="req.submission?.submitted_at"
                                                                                class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400"
                                                                            >
                                                                                Submitted {{ formatDate(req.submission?.submitted_at) }}
                                                                                <template v-if="req.submission.reviewed_by">
                                                                                    · Reviewed by
                                                                                    <span class="font-medium text-slate-600 dark:text-slate-300">{{
                                                                                        req.submission.reviewed_by
                                                                                    }}</span>
                                                                                </template>
                                                                            </p>
                                                                            <p v-else class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                                                                                Not yet submitted
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex shrink-0 items-center gap-2 pl-10 sm:pl-0">
                                                                        <span
                                                                            class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                                                            :class="submissionStatusColor(req.submission?.status)"
                                                                        >
                                                                            {{ req.submission?.status ?? 'Not Submitted' }}
                                                                        </span>

                                                                        <button
                                                                            v-if="req.submission?.file_path"
                                                                            type="button"
                                                                            class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20"
                                                                            title="View File"
                                                                            @click.stop="openFilePreview(req.submission.file_path, req.title)"
                                                                        >
                                                                            <ExternalLink class="h-3 w-3" /> View File
                                                                        </button>
                                                                    </div>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </Transition>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pagination -->
                                    <div
                                        v-if="filteredPrograms.length > programsPerPage"
                                        class="mt-4 flex flex-col items-center gap-3 text-xs sm:flex-row sm:justify-between"
                                    >
                                        <p class="text-slate-500 dark:text-slate-400">
                                            Showing
                                            <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200"
                                                >{{ (programPage - 1) * programsPerPage + 1 }}–{{
                                                    Math.min(programPage * programsPerPage, filteredPrograms.length)
                                                }}</span
                                            >
                                            of
                                            <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{
                                                filteredPrograms.length
                                            }}</span>
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <button type="button" :disabled="programPage <= 1" :class="pagerButtonClass" @click="programPage--">
                                                <ChevronLeft class="h-3.5 w-3.5" /> Prev
                                            </button>
                                            <span
                                                class="rounded-lg bg-violet-50 px-2.5 py-1.5 font-semibold tabular-nums text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"
                                                >Page {{ programPage }} of {{ totalProgramPages }}</span
                                            >
                                            <button
                                                type="button"
                                                :disabled="programPage >= totalProgramPages"
                                                :class="pagerButtonClass"
                                                @click="programPage++"
                                            >
                                                Next <ChevronRight class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div
                            class="flex items-center justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-3 dark:border-slate-800 dark:bg-slate-900 sm:px-8"
                        >
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-white"
                                @click="close"
                            >
                                Close
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>

    <FilePreviewModal
        :open="showFilePreview"
        :file-url="previewFile?.url ?? null"
        :title="previewFile?.title"
        :description="previewFile?.description"
        @update:open="showFilePreview = $event"
    />
</template>

<style scoped>
/* Modal entrance: overlay fades, panel rises and scales in slightly. */
.epm-enter-active,
.epm-leave-active {
    transition: opacity 0.2s ease;
}

.epm-enter-active .epm-panel,
.epm-leave-active .epm-panel {
    transition:
        transform 0.22s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.2s ease;
}

.epm-enter-from,
.epm-leave-to {
    opacity: 0;
}

.epm-enter-from .epm-panel,
.epm-leave-to .epm-panel {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}

/* Draw the completion ring from empty on first render (263.89 ≈ 2π × 42). */
.ring-arc {
    transition: stroke-dashoffset 0.6s ease;
    animation: ring-draw 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes ring-draw {
    from {
        stroke-dashoffset: 263.89;
    }
}

@media (prefers-reduced-motion: reduce) {
    .epm-enter-active,
    .epm-leave-active,
    .epm-enter-active .epm-panel,
    .epm-leave-active .epm-panel,
    .ring-arc {
        transition: none;
        animation: none;
    }
}
</style>
