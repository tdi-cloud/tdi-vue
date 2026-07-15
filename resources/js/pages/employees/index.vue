<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    Search, Users, ChevronLeft, ChevronRight,
    X, CheckCircle2, Clock, Award, FileText,
    Building2, MapPin, Hash, Star, AlertCircle,
    ExternalLink,
    Download
} from 'lucide-vue-next';
import axios from 'axios';

interface Employee {
    id: number;
    EMPCODE: string;
    FIRSTNAME: string;
    LASTNAME: string;
    MI: string;
    POSITION: string;
    'OFFICE/DIVISION': string;
    'PLANTILLA STATUS': string;
    REGION: string;
    OFFICE: string;
    SEX: string;
    SG: string;
    name: string;
    initials: string;
    avatar_color: string;
    avatar: string | null;
    progress_stats: {
        total_programs: number;
        completed_programs: number;
        total_hours: number;
        hours_completed: number;
    };
    submission_stats: {
        total_requirements: number;
        approved_submissions: number;
    };
}

interface PaginatedEmployees {
    data: Employee[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
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
    employees: PaginatedEmployees;
    regions: string[];
    plantillaStatuses: string[];
    filters: {
        search?: string;
        region?: string;
        plantilla?: string;
        per_page?: string;
    };
}>();

// Filters
const search    = ref(props.filters.search ?? '');
const region    = ref(props.filters.region ?? 'all');
const plantilla = ref(props.filters.plantilla ?? 'all');
const perPage   = ref(props.filters.per_page ?? '10');

let debounce: ReturnType<typeof setTimeout>;
watch([search, region, plantilla, perPage], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('employees.index'), {
            search:    search.value || undefined,
            region:    region.value !== 'all' ? region.value : undefined,
            plantilla: plantilla.value !== 'all' ? plantilla.value : undefined,
            per_page:  perPage.value !== '10' ? perPage.value : undefined,
        }, { preserveScroll: true, preserveState: true, replace: true });
    }, 350);
});

// Detail Modal
const showModal   = ref(false);
const loading     = ref(false);
const progress    = ref<EmployeeProgress | null>(null);
const activeReqs  = ref<EnrolledProgram | null>(null);

const openDetails = async (emp: Employee) => {
    loading.value    = true;
    showModal.value  = true;
    progress.value   = null;
    activeReqs.value = null;

    try {
        const res = await axios.get(route('employees.progress', { empcode: emp.EMPCODE }));
        progress.value = res.data;
    } catch (e: any) {
        console.error('Error:', e.response?.status, e.response?.data);
    } finally {
        loading.value = false;
    }
};

const closeModal = () => {
    showModal.value  = false;
    progress.value   = null;
    activeReqs.value = null;
};

// Helpers
const initials = (emp: Employee) => {
    return ((emp.FIRSTNAME?.[0] ?? '') + (emp.LASTNAME?.[0] ?? ''))?.toUpperCase();
};

const fullName = (emp: Employee) => {
    return [emp.FIRSTNAME, emp.MI ? emp.MI + '.' : '', emp.LASTNAME]
        .filter(Boolean).join(' ')?.toUpperCase();
};

const plantillaColor = (status: string) => {
    const s = status?.toUpperCase();
    if (s === 'PERMANENT') return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if (s === 'JOB ORDER') return 'bg-amber-100 text-amber-700 border-amber-200';
    if (s === 'CTI')       return 'bg-blue-100 text-blue-700 border-blue-200';
    return 'bg-gray-100 text-gray-600 border-gray-200';
};

const plantillaDot = (status: string) => {
    const s = status?.toUpperCase();
    if (s === 'PERMANENT') return 'bg-emerald-500';
    if (s === 'JOB ORDER') return 'bg-amber-500';
    if (s === 'CTI')       return 'bg-blue-500';
    return 'bg-gray-400';
};

const avatarColor = (emp: Employee) => {
    const colors = [
        'bg-violet-500', 'bg-blue-500', 'bg-emerald-500',
        'bg-rose-500', 'bg-amber-500', 'bg-indigo-500',
        'bg-teal-500', 'bg-pink-500',
    ];
    const idx = (emp.EMPCODE?.charCodeAt(0) ?? 0) % colors?.length;
    return colors[idx];
};

const formatDate = (d?: string | null) => {
    if (!d) return '—';
    const date = new Date(d.includes('T') ? d : d + 'T00:00:00');
    return isNaN(date.getTime()) ? d : date.toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
};

const progressPercent = (num: number, denom: number) => (denom > 0 ? Math.min(100, Math.round((num / denom) * 100)) : 0);

const formatHours = (hours: number) => (Number.isInteger(hours) ? hours : Number(hours.toFixed(1)));

const submissionStatusColor = (status?: string) => {
    if (status === 'Approved') return 'bg-emerald-100 text-emerald-700';
    if (status === 'Pending')  return 'bg-amber-100 text-amber-700';
    if (status === 'Rejected') return 'bg-red-100 text-red-700';
    return 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <Head title="Employee Progress" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">

            <!-- Header -->
            <div>
                <h1 class="text-2xl font-extrabold">Employee Progress</h1>
                <p class="text-sm text-muted-foreground mt-0.5">Track individual employee training progress and achievements</p>
            </div>

            <!-- Search + Filters -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <!-- Search -->
                    <div class="relative flex-1 min-w-64">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by Name, Office, or Empcode..."
                            class="w-full border rounded-xl pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-background shadow-lg"
                        />
                    </div>

                    <!-- Region -->
                    <select v-model="region" class="border rounded-xl px-3 py-2.5 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-lg">
                        <option value="all">All Regions</option>
                        <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
                    </select>

                    <!-- Per page -->
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>Show</span>
                        <select v-model="perPage" class="border rounded-xl px-2 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-blue-500 w-16 shadow-lg">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <!-- Plantilla filter pills -->
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        @click="plantilla = 'all'"
                        class="px-3 py-1 rounded-full text-xs font-bold transition-colors"
                        :class="plantilla === 'all' ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                    >
                        ALL
                    </button>
                    <button
                        v-for="p in plantillaStatuses"
                        :key="p"
                        @click="plantilla = plantilla === p ? 'all' : p"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border transition-colors"
                        :class="plantilla === p ? plantillaColor(p) + ' ring-2 ring-offset-1' : 'bg-background text-muted-foreground border-border hover:bg-muted/50'"
                    >
                        <span class="h-2 w-2 rounded-full" :class="plantillaDot(p)" />
                        {{ p?.toUpperCase() }}
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-2xl border overflow-hidden shadow-sm bg-background">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-foreground text-background">
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Empcode</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Employee</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Plantilla</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Program Progress</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Hours Progress</th>
                            <th class="text-left font-bold px-4 py-3 text-xs uppercase tracking-wide">Submission Progress</th>
                            <th class="text-right font-bold px-4 py-3 text-xs uppercase tracking-wide">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="emp in employees.data"
                            :key="emp.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <td class="px-4 py-3 text-muted-foreground font-mono text-xs">{{ emp.EMPCODE }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <Avatar class="h-9 w-9 shrink-0 overflow-hidden rounded-full" :class="emp.avatar_color">
                                        <AvatarImage v-if="emp.avatar" :src="emp.avatar" :alt="emp.name" />
                                        <AvatarFallback class="flex h-full w-full items-center justify-center rounded-full bg-transparent text-xs font-bold text-white">
                                            {{ emp.initials }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <p class="font-bold text-sm leading-tight">{{ emp.name?.toUpperCase() }}</p>
                                        <p class="text-xs text-muted-foreground">{{ emp['OFFICE/DIVISION'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full border" :class="plantillaColor(emp['PLANTILLA STATUS'])">
                                    {{ emp['PLANTILLA STATUS'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 min-w-[140px]">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-semibold">
                                        {{ emp.progress_stats.completed_programs }}/{{ emp.progress_stats.total_programs }}
                                    </span>
                                    <span class="text-muted-foreground">{{ progressPercent(emp.progress_stats.completed_programs, emp.progress_stats.total_programs) }}%</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-emerald-500 transition-all"
                                        :style="{ width: progressPercent(emp.progress_stats.completed_programs, emp.progress_stats.total_programs) + '%' }"
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-3 min-w-[140px]">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-semibold">
                                        {{ formatHours(emp.progress_stats.hours_completed) }}/{{ formatHours(emp.progress_stats.total_hours) }} hrs
                                    </span>
                                    <span class="text-muted-foreground">{{ progressPercent(emp.progress_stats.hours_completed, emp.progress_stats.total_hours) }}%</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-blue-500 transition-all"
                                        :style="{ width: progressPercent(emp.progress_stats.hours_completed, emp.progress_stats.total_hours) + '%' }"
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-3 min-w-[140px]">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-semibold">
                                        {{ emp.submission_stats.approved_submissions }}/{{ emp.submission_stats.total_requirements }}
                                    </span>
                                    <span class="text-muted-foreground">{{ progressPercent(emp.submission_stats.approved_submissions, emp.submission_stats.total_requirements) }}%</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-violet-500 transition-all"
                                        :style="{ width: progressPercent(emp.submission_stats.approved_submissions, emp.submission_stats.total_requirements) + '%' }"
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    @click="openDetails(emp)"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border hover:bg-muted/50 transition-colors"
                                >
                                    <Users class="h-3.5 w-3.5" /> View Details
                                </button>
                            </td>
                        </tr>

                        <tr v-if="employees.data?.length === 0">
                            <td colspan="7" class="px-4 py-16 text-center text-muted-foreground">
                                <Users class="h-10 w-10 mx-auto mb-2 opacity-30" />
                                <p class="text-sm font-semibold">No employees found.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between text-sm">
                <p class="text-xs text-muted-foreground">
                    Showing {{ employees.from ?? 0 }}–{{ employees.to ?? 0 }} of {{ employees.total }}
                </p>
                <div class="flex items-center gap-1">
                    <template v-for="link in employees.links" :key="link.label">
                        
                        <a v-if="link.url"
                            :href="link.url"
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-muted text-muted-foreground'"
                            v-html="link.label.includes('Previous') ? '&lsaquo;' : link.label.includes('Next') ? '&rsaquo;' : link.label"
                        />
                        <span v-else
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-xs text-muted-foreground opacity-40"
                            v-html="link.label.includes('Previous') ? '&lsaquo;' : link.label.includes('Next') ? '&rsaquo;' : link.label"
                        />
                    </template>
                </div>
            </div>

        </div>

        <!-- ===== Employee Progress Modal ===== -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="closeModal"
        >
            <div class="bg-background rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

                <!-- Loading -->
                <div v-if="loading" class="flex items-center justify-center py-24">
                    <div class="h-8 w-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin" />
                </div>

                <template v-else-if="progress">

                    <!-- Modal Header -->
                    <div class="sticky top-0 z-10 bg-gradient-to-r from-violet-600 to-purple-700 text-white px-6 py-4 rounded-t-2xl flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            <span class="font-bold">Employee Training Details</span>
                        </div>
                        <button @click="closeModal" class="text-white/70 hover:text-white transition-colors">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-6 flex flex-col gap-6">

                        <!-- Employee Card -->
                        <div class="rounded-xl border p-4 flex items-center gap-4">
                            <Avatar class="h-16 w-16 shrink-0 overflow-hidden rounded-xl" :class="progress.employee.avatar_color">
                                <AvatarImage v-if="progress.employee.avatar" :src="progress.employee.avatar" :alt="progress.employee.name" />
                                <AvatarFallback class="flex h-full w-full items-center justify-center rounded-xl bg-transparent text-xl font-extrabold text-white">
                                    {{ progress.employee.initials }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h2 class="text-lg font-extrabold leading-tight">{{ progress.employee.name?.toUpperCase() }}</h2>
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full border"
                                        :class="plantillaColor(progress.employee['PLANTILLA STATUS'])">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="plantillaDot(progress.employee['PLANTILLA STATUS'])" />
                                        {{ progress.employee['PLANTILLA STATUS'] }}
                                    </span>
                                </div>
                                <p class="text-sm text-muted-foreground">{{ progress.employee.POSITION }}</p>
                                <div class="flex items-center gap-4 mt-1 text-xs text-muted-foreground flex-wrap">
                                    <span class="flex items-center gap-1"><Hash class="h-3 w-3" /> Emp Code: <strong class="text-foreground">{{ progress.employee.EMPCODE }}</strong></span>
                                    <span class="flex items-center gap-1"><Building2 class="h-3 w-3" /> {{ progress.employee['OFFICE/DIVISION'] }}</span>
                                    <span class="flex items-center gap-1"><MapPin class="h-3 w-3" /> {{ progress.employee.REGION }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Program Overview -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground flex items-center gap-1.5 mb-3">
                                <FileText class="h-3.5 w-3.5" /> Program Overview
                            </p>
                            <div class="grid grid-cols-2 md:grid-cols-2 gap-3">
                                <div class="rounded-xl border p-3 flex items-center justify-between">
                                    <div>
                                        <p class="text-2xl font-extrabold text-blue-600">{{ progress.stats.programs_attended }}</p>
                                        <p class="text-xs text-muted-foreground mt-0.5">Program(s)</p>
                                    </div>
                                    <div class="h-9 w-9 rounded-lg bg-blue-100 dark:bg-blue-950/40 flex items-center justify-center">
                                        <Award class="h-5 w-5 text-blue-600" />
                                    </div>
                                </div>
                                <div class="rounded-xl border p-3 flex items-center justify-between">
                                    <div>
                                        <p class="text-2xl font-extrabold text-emerald-600">{{ progress.stats.programs_completed }}</p>
                                        <p class="text-xs text-muted-foreground mt-0.5">Programs Completed</p>
                                    </div>
                                    <div class="h-9 w-9 rounded-lg bg-emerald-100 dark:bg-emerald-950/40 flex items-center justify-center">
                                        <CheckCircle2 class="h-5 w-5 text-emerald-600" />
                                    </div>
                                </div>
                                <div class="rounded-xl border p-3 flex items-center justify-between">
                                    <div>
                                        <p class="text-2xl font-extrabold text-amber-600">{{ progress.stats.total_hours }}</p>
                                        <p class="text-xs text-muted-foreground mt-0.5">Total Hours Rendered</p>
                                    </div>
                                    <div class="h-9 w-9 rounded-lg bg-amber-100 dark:bg-amber-950/40 flex items-center justify-center">
                                        <Clock class="h-5 w-5 text-amber-600" />
                                    </div>
                                </div>
                                <div class="rounded-xl border p-3 flex items-center justify-between">
                                    <div>
                                        <p class="text-2xl font-extrabold text-violet-600">{{ progress.stats.completion_rate }}%</p>
                                        <p class="text-xs text-muted-foreground mt-0.5">Completion Rate</p>
                                    </div>
                                    <div class="h-9 w-9 rounded-lg bg-violet-100 dark:bg-violet-950/40 flex items-center justify-center">
                                        <Star class="h-5 w-5 text-violet-600" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Post-Training Submissions -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground flex items-center gap-1.5 mb-3">
                                <FileText class="h-3.5 w-3.5" /> Post-Training Submissions
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900 p-4 text-center">
                                    <div class="h-10 w-10 mx-auto rounded-full bg-emerald-500 flex items-center justify-center mb-2">
                                        <CheckCircle2 class="h-5 w-5 text-white" />
                                    </div>
                                    <p class="text-3xl font-extrabold text-emerald-700 dark:text-emerald-400">{{ progress.stats.total_submissions }}</p>
                                    <p class="text-xs text-emerald-600 mt-0.5 font-semibold">Submitted</p>
                                </div>
                                <div class="rounded-xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900 p-4 text-center">
                                    <div class="h-10 w-10 mx-auto rounded-full bg-red-500 flex items-center justify-center mb-2">
                                        <AlertCircle class="h-5 w-5 text-white" />
                                    </div>
                                    <p class="text-3xl font-extrabold text-red-700 dark:text-red-400">{{ progress.stats.not_approved }}</p>
                                    <p class="text-xs text-red-600 mt-0.5 font-semibold">Not Yet Approved</p>
                                </div>
                            </div>

                            <!-- Completion Rate Bar -->
                            <div class="mt-3 flex items-center justify-between text-xs mb-1">
                                <span class="font-semibold">Completion Rate</span>
                                <span class="font-bold">{{ progress.stats.completion_rate }}%</span>
                            </div>
                            <div class="h-2.5 rounded-full bg-muted overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-emerald-500 transition-all"
                                    :style="{ width: progress.stats.completion_rate + '%' }"
                                />
                            </div>
                        </div>

                        <!-- Enrolled Programs -->
                        <div>

                            <div class="flex w-full items-center justify-between mb-3">
                                <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground flex items-center gap-1.5">
                                    <Award class="h-3.5 w-3.5" /> Enrolled Programs
                                </p>

                                
                                <a    v-if="progress.enrolled_programs?.length"
                                    :href="route('employees.export', { empcode: progress.employee.EMPCODE })"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors"
                                >
                                    <Download class="h-3.5 w-3.5" /> Export CSV
                                </a>
                            </div>
                            

                            <div v-if="progress.enrolled_programs?.length === 0" class="text-center py-8 text-muted-foreground">
                                <Award class="h-8 w-8 mx-auto mb-2 opacity-30" />
                                <p class="text-sm">No enrolled programs yet.</p>
                            </div>

                            <div v-else class="flex flex-col gap-2">
                                <div
                                    v-for="prog in progress.enrolled_programs"
                                    :key="prog.participant_id"
                                    class="rounded-xl border p-4 hover:border-blue-300 transition-colors cursor-pointer"
                                    @click="activeReqs = activeReqs?.participant_id === prog.participant_id ? null : prog"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <div class="h-10 w-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/40 flex items-center justify-center shrink-0">
                                                <Award class="h-5 w-5 text-emerald-600" />
                                            </div>
                                            <div class="min-w-0">
                                                <a :href="`/programs/${prog.program_id}`"
                                                    class="font-semibold text-sm leading-tight hover:text-blue-600 hover:underline transition-colors block truncate max-w-xs"
                                                    @click.stop
                                                    target="_blank"
                                                >
                                                    {{ prog.program_title }}
                                                </a>
                                                <p class="text-xs text-muted-foreground mt-0.5">
                                                    {{ formatDate(prog.date_start) }} – {{ formatDate(prog.date_end) }}
                                                    <span v-if="prog.hours"> · {{ prog.hours }} hrs</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <span
                                                v-if="prog.total_requirements > 0"
                                                class="text-xs font-semibold px-2 py-0.5 rounded-full flex items-center gap-1"
                                                :class="prog.approved_submissions >= prog.total_requirements
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-amber-100 text-amber-700'"
                                            >
                                                <CheckCircle2 class="h-3 w-3" />
                                                {{ prog.approved_submissions }}/{{ prog.total_requirements }} approved
                                            </span>
                                            <span
                                                class="text-xs font-bold px-2.5 py-1 rounded-full"
                                                :class="prog.is_completed
                                                    ? 'bg-emerald-600 text-white'
                                                    : prog.attendance === 'Complete'
                                                        ? 'bg-blue-100 text-blue-700'
                                                        : prog.attendance === 'Absent'
                                                            ? 'bg-red-100 text-red-700'
                                                            : 'bg-amber-100 text-amber-700'"
                                            >
                                                {{ prog.is_completed ? 'COMPLETED' : prog.attendance?.toUpperCase() }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Requirements breakdown (expandable) -->
                                    <div v-if="activeReqs?.participant_id === prog.participant_id && prog.requirements?.length > 0" class="mt-4 pt-4 border-t">
                                        <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground mb-2">Requirements</p>

                                        <!-- Tabs -->
                                        <div class="flex items-center gap-3 mb-3 text-xs font-semibold">
                                            <span class="text-muted-foreground">All {{ prog.requirements?.length }}</span>
                                            <span class="text-emerald-600">Approved {{ prog.approved_submissions }}</span>
                                            <span class="text-amber-600">Not Approved {{ prog.pending_submissions }}</span>
                                        </div>

                                        <div class="flex flex-col gap-2">
                                            <div
                                                v-for="req in prog.requirements"
                                                :key="req.id"
                                                class="flex items-center justify-between gap-3 p-2.5 rounded-lg bg-muted/30 text-sm"
                                            >
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <div class="h-6 w-6 rounded-full flex items-center justify-center shrink-0"
                                                        :class="req.submission?.status === 'Approved'
                                                            ? 'bg-emerald-100'
                                                            : req.submission?.status === 'Rejected'
                                                                ? 'bg-red-100'
                                                                : req.submission?.status === 'Pending'
                                                                    ? 'bg-amber-100'
                                                                    : 'bg-gray-100'"
                                                    >
                                                        <CheckCircle2 v-if="req.submission?.status === 'Approved'" class="h-3.5 w-3.5 text-emerald-600" />
                                                        <Clock v-else-if="req.submission?.status === 'Pending'" class="h-3.5 w-3.5 text-amber-600" />
                                                        <X v-else-if="req.submission?.status === 'Rejected'" class="h-3.5 w-3.5 text-red-500" />
                                                        <AlertCircle v-else class="h-3.5 w-3.5 text-gray-400" />
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="font-semibold truncate">{{ req.title }}</p>
                                                        <p v-if="req.submission?.submitted_at" class="text-[10px] text-muted-foreground">
                                                            Submitted: {{ formatDate(req.submission?.submitted_at) }}
                                                            <template v-if="req.submission.reviewed_by">
                                                                · Reviewed by {{ req.submission.reviewed_by }}
                                                            </template>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <span
                                                        class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"
                                                        :class="submissionStatusColor(req.submission?.status)"
                                                    >
                                                        {{ req.submission?.status ?? 'Not Submitted' }}
                                                        
                                                    
                                                    </span>
                                                    
                                                    <a v-if="req.submission?.file_path"
                                                        :href="`/storage/${req.submission.file_path}`"
                                                        target="_blank"
                                                        class="text-blue-600 hover:text-blue-700"
                                                        title="View File"
                                                        @click.stop
                                                    >
                                                        <ExternalLink class="h-3.5 w-3.5" />
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t text-right">
                        <button @click="closeModal" class="text-sm font-semibold text-muted-foreground hover:text-foreground transition-colors">
                            Close
                        </button>
                    </div>

                </template>
            </div>
        </div>

    </AppLayout>
</template>