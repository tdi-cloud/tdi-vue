<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowUpRight,
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronDown,
    ClipboardList,
    Clock,
    CloudUpload,
    ExternalLink,
    FileCheck2,
    FileText,
    Gauge,
    History,
    LayoutGrid,
    Search,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const { confirmDialog } = useConfirm();

interface Report {
    id: number;
    region: string;
    month: string;
    year: number;
    file_name: string;
    file_path: string;
    submitted_at: string;
    notes: string | null;
    added_by: string;
}

interface Stats {
    totalRequired: number;
    submitted: number;
    pending: number;
    rate: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    matrix: Record<string, Record<number, Report | null>>;
    regions: Record<string, string>;
    directors: Record<string, string>;
    months: Record<number, string>;
    year: number;
    currentMonth: number;
    stats: Stats;
    recentSubmissions: Paginated<Report>;
    availableYears: number[];
    filters: { search: string };
}>();

// --- Year filter ---
const selectedYear = ref(props.year);
const changeYear = (y: number) => {
    router.get(route('tpmr.index'), { year: y, search: search.value || undefined }, { preserveScroll: false });
};

// --- Search (Recent Submissions) ---
const search = ref(props.filters.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            route('tpmr.index'),
            { year: props.year, search: value || undefined },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 400);
});

// --- Pagination ---
const goToPage = (url: string | null) => {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true, preserveState: true });
};

// --- Modal ---
const showModal = ref(false);

const form = useForm({
    region: '',
    month: '',
    year: props.year,
    notes: '',
    pdf: null as File | null,
});

const openModal = (region = '', monthName = '') => {
    form.reset();
    form.year = props.year;
    form.region = region;
    form.month = monthName;
    selectedFile.value = null;
    showModal.value = true;
};

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const isDraggingFile = ref(false);

const handleSelectedFile = (f: File | null) => {
    if (f && f.size > 10 * 1024 * 1024) {
        // 10MB
        form.errors.pdf = 'File is too large. Maximum size is 10MB.';
        selectedFile.value = null;
        form.pdf = null;
        if (fileInput.value) fileInput.value.value = ''; // reset the input
        return;
    }

    form.clearErrors('pdf');
    selectedFile.value = f;
    form.pdf = f;
};

const onFileChange = (e: Event) => {
    handleSelectedFile((e.target as HTMLInputElement).files?.[0] ?? null);
};

/** Dropped files bypass the input's `accept` attribute, so mirror it here. */
const onFileDrop = (e: DragEvent) => {
    isDraggingFile.value = false;
    const f = e.dataTransfer?.files?.[0] ?? null;

    if (f && f.type !== 'application/pdf' && !f.name.toLowerCase().endsWith('.pdf')) {
        form.errors.pdf = 'Only PDF files are accepted.';
        return;
    }

    handleSelectedFile(f);
};

const clearSelectedFile = () => {
    selectedFile.value = null;
    form.pdf = null;
    if (fileInput.value) fileInput.value.value = '';
};

const submit = () => {
    form.post(route('tpmr.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            selectedFile.value = null;
            form.reset();
        },
    });
};

const deleteReport = async (id: number) => {
    if (!(await confirmDialog('Delete this report?'))) return;
    router.delete(route('tpmr.destroy', id), { preserveScroll: true });
};

// Cell state
const cellState = (report: Report | null, monthNum: number): 'submitted' | 'pending' | 'future' => {
    if (report) return 'submitted';
    if (monthNum > props.currentMonth) return 'future';
    return 'pending';
};

const reportAt = (code: string | number, num: string | number): Report | null => props.matrix[String(code)]?.[parseInt(String(num))] ?? null;

const isCurrentMonth = (num: string | number) => parseInt(String(num)) === props.currentMonth;

const monthAbbr = (name: string) => name.slice(0, 3).toUpperCase();

const formatDate = (d: string) =>
    new Date(d.includes('T') ? d : d + 'T00:00:00').toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

const formatFileSize = (bytes: number) =>
    bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;

const monthOptions = computed(() => Object.entries(props.months).map(([num, name]) => ({ num: parseInt(num), name })));

const regionOptions = computed(() => Object.entries(props.regions).map(([code, name]) => ({ code, name })));

const regionCount = computed(() => Object.keys(props.regions).length);

const regionSubmittedCount = (code: string) => Object.values(props.matrix[code] ?? {}).filter((r) => r !== null).length;

const regionRate = (code: string) => Math.round((regionSubmittedCount(code) / props.currentMonth) * 100);

/** Visual tone for a compliance percentage (display only). */
const rateTone = (rate: number): { text: string; bar: string } => {
    if (rate >= 80) return { text: 'text-emerald-700 dark:text-emerald-400', bar: 'bg-emerald-500' };
    if (rate >= 50) return { text: 'text-amber-700 dark:text-amber-400', bar: 'bg-amber-500' };
    if (rate > 0) return { text: 'text-orange-700 dark:text-orange-400', bar: 'bg-orange-500' };
    return { text: 'text-red-600 dark:text-red-400', bar: 'bg-red-500' };
};

// Progress bars grow from zero once the page has mounted.
const progressReady = ref(false);
onMounted(() => requestAnimationFrame(() => (progressReady.value = true)));

const inputClass =
    'h-10 w-full rounded-lg border bg-card px-3 text-sm text-foreground shadow-sm transition-colors placeholder:text-muted-foreground/70 hover:border-slate-300 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/15 dark:hover:border-slate-600 dark:focus:border-blue-500';

const fieldStateClass = (error?: string) =>
    error ? 'border-red-400 focus:border-red-500 focus:ring-red-500/15 dark:border-red-700' : 'border-border';
</script>

<template>
    <Head title="TPMR – Training Program Monitoring Reports" />

    <AppLayout>
        <div class="tdi-dashboard relative flex flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Barely visible accent wash behind the header -->
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-x-0 top-0 z-0 h-72 bg-[radial-gradient(60%_80%_at_85%_0%,rgba(37,99,235,0.07),transparent_70%)] dark:bg-[radial-gradient(60%_80%_at_85%_0%,rgba(59,130,246,0.09),transparent_70%)]"
            />

            <!-- Header -->
            <header class="relative flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
                <div class="min-w-0">
                    <div class="mb-3 flex flex-wrap items-center gap-2.5">
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-400"
                        >
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60 motion-reduce:animate-none"
                                />
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                            </span>
                            Live Monitoring
                        </span>
                        <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Compliance Dashboard</span>
                    </div>
                    <h1 class="text-2xl font-bold leading-tight tracking-tight text-foreground md:text-3xl">Training Program Monitoring Reports</h1>
                    <p class="mt-1.5 text-sm text-muted-foreground">Regional submission compliance and monitoring overview</p>
                </div>

                <div class="flex w-full shrink-0 items-center gap-3 sm:w-auto">
                    <label
                        class="relative flex h-11 cursor-pointer items-center gap-2.5 rounded-xl border bg-card pl-3 pr-3 shadow-sm transition-all focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/15 hover:border-blue-300 dark:hover:border-blue-800"
                    >
                        <CalendarDays class="h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                        <span class="flex flex-col leading-none">
                            <span class="text-[9px] font-bold uppercase tracking-[0.14em] text-muted-foreground">Year</span>
                            <select
                                :value="selectedYear"
                                @change="changeYear(parseInt(($event.target as HTMLSelectElement).value))"
                                aria-label="Select year"
                                class="-ml-0.5 cursor-pointer appearance-none border-none bg-transparent pr-5 text-sm font-bold tabular-nums text-foreground outline-none"
                            >
                                <option v-for="y in availableYears" :key="y" :value="y" class="bg-card text-foreground">{{ y }}</option>
                            </select>
                        </span>
                        <ChevronDown class="pointer-events-none absolute right-3 h-4 w-4 text-muted-foreground" />
                    </label>

                    <Button
                        class="h-11 flex-1 gap-2 rounded-xl bg-blue-700 px-5 font-semibold text-white shadow-sm shadow-blue-700/25 transition-all hover:bg-blue-800 hover:shadow-md hover:shadow-blue-700/25 active:scale-[0.98] dark:bg-blue-600 dark:hover:bg-blue-500 sm:flex-none"
                        @click="openModal()"
                    >
                        <Upload class="h-4 w-4" /> New Submission
                    </Button>
                </div>
            </header>

            <!-- Stat Cards -->
            <div class="relative grid grid-cols-1 gap-4 min-[420px]:grid-cols-2 lg:grid-cols-4">
                <!-- Total Required -->
                <div class="tdi-card tdi-accent-navy tdi-accent-to-royal relative isolate flex flex-col overflow-hidden rounded-xl border p-5">
                    <div class="flex items-start justify-between gap-3">
                        <p class="pt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground">Total Required</p>
                        <span class="tdi-icon-chip flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                            <ClipboardList class="h-4 w-4" />
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-extrabold tabular-nums tracking-tight text-foreground md:text-4xl">{{ stats.totalRequired }}</p>
                    <p class="text-xs text-muted-foreground">reporting slots</p>
                    <div class="mt-auto pt-4">
                        <p class="border-t pt-3 text-[11px] text-muted-foreground">
                            {{ regionCount }} regions × {{ currentMonth }} {{ currentMonth === 1 ? 'month' : 'months' }}
                        </p>
                    </div>
                </div>

                <!-- Compliant -->
                <div class="tdi-card tdi-accent-emerald relative isolate flex flex-col overflow-hidden rounded-xl border p-5">
                    <div class="flex items-start justify-between gap-3">
                        <p class="pt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground">Compliant</p>
                        <span class="tdi-icon-chip flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                            <CheckCircle2 class="h-4 w-4" />
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-extrabold tabular-nums tracking-tight text-emerald-700 dark:text-emerald-400 md:text-4xl">
                        {{ stats.submitted }}
                    </p>
                    <p class="text-xs text-muted-foreground">submitted</p>
                    <div class="mt-auto pt-4">
                        <p class="flex items-center gap-1.5 border-t pt-3 text-[11px] text-muted-foreground">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Reports received
                        </p>
                    </div>
                </div>

                <!-- Pending -->
                <div class="tdi-card tdi-accent-amber relative isolate flex flex-col overflow-hidden rounded-xl border p-5">
                    <div class="flex items-start justify-between gap-3">
                        <p class="pt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground">Pending</p>
                        <span class="tdi-icon-chip flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                            <Clock class="h-4 w-4" />
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-extrabold tabular-nums tracking-tight text-amber-700 dark:text-amber-400 md:text-4xl">
                        {{ stats.pending }}
                    </p>
                    <p class="text-xs text-muted-foreground">overdue</p>
                    <div class="mt-auto pt-4">
                        <p class="flex items-center gap-1.5 border-t pt-3 text-[11px] text-muted-foreground">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500" /> Awaiting submission
                        </p>
                    </div>
                </div>

                <!-- Compliance Rate -->
                <div
                    class="relative isolate flex flex-col overflow-hidden rounded-xl border border-blue-900/30 bg-gradient-to-br from-blue-700 via-blue-800 to-[#0b2a5b] p-5 text-white shadow-[0_10px_30px_-12px_rgba(29,78,216,0.55)] transition-shadow duration-300 hover:shadow-[0_16px_36px_-14px_rgba(29,78,216,0.7)] dark:border-blue-700/40 dark:from-blue-800 dark:via-blue-900 dark:to-[#0a1f44]"
                >
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute -right-10 -top-12 -z-10 h-36 w-36 rounded-full bg-sky-300/20 blur-2xl"
                    />
                    <div class="flex items-start justify-between gap-3">
                        <p class="pt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-blue-100/80">Compliance Rate</p>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-inset ring-white/15">
                            <Gauge class="h-4 w-4 text-white" />
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-extrabold tabular-nums tracking-tight md:text-4xl">
                        {{ stats.rate }}<span class="ml-0.5 text-xl font-bold text-blue-200">%</span>
                    </p>
                    <p class="text-xs text-blue-100/70">overall compliance</p>
                    <div class="mt-auto pt-4">
                        <div
                            class="h-2 overflow-hidden rounded-full bg-white/15"
                            role="progressbar"
                            :aria-valuenow="stats.rate"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="Compliance rate"
                        >
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-emerald-300 to-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.6)] transition-[width] duration-1000 ease-out motion-reduce:transition-none"
                                :style="{ width: (progressReady ? Math.min(stats.rate, 100) : 0) + '%' }"
                            />
                        </div>
                        <p class="mt-2 text-[11px] tabular-nums text-blue-100/70">{{ stats.submitted }} of {{ stats.totalRequired }} slots filled</p>
                    </div>
                </div>
            </div>

            <!-- Matrix -->
            <section class="tdi-card tdi-accent-navy tdi-accent-to-sky relative isolate overflow-hidden rounded-xl border">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b px-5 py-4 md:px-6">
                    <div class="flex items-center gap-3">
                        <span class="tdi-icon-chip flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                            <LayoutGrid class="h-4 w-4" />
                        </span>
                        <div>
                            <h2 class="text-base font-bold tracking-tight text-foreground md:text-lg">Regional Compliance Matrix</h2>
                            <p class="text-xs text-muted-foreground">Monthly submission status by region · {{ year }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-1 rounded-lg border bg-muted/60 p-1 text-[11px] font-medium text-muted-foreground">
                        <span class="flex items-center gap-1.5 rounded-md px-2 py-1">
                            <span
                                class="flex h-4 w-4 items-center justify-center rounded border border-emerald-200 bg-emerald-50 dark:border-emerald-800/70 dark:bg-emerald-950/50"
                            >
                                <Check class="h-2.5 w-2.5 text-emerald-600 dark:text-emerald-400" :stroke-width="3" />
                            </span>
                            Submitted
                        </span>
                        <span class="flex items-center gap-1.5 rounded-md px-2 py-1">
                            <span
                                class="flex h-4 w-4 items-center justify-center rounded border border-dashed border-amber-400 bg-amber-50/60 dark:border-amber-600 dark:bg-amber-950/30"
                            >
                                <AlertCircle class="h-2.5 w-2.5 text-amber-600 dark:text-amber-400" />
                            </span>
                            Pending
                        </span>
                        <span class="flex items-center gap-1.5 rounded-md px-2 py-1">
                            <span class="h-4 w-4 rounded border border-border bg-muted" />
                            Future
                        </span>
                    </div>
                </div>

                <div class="max-h-[65vh] overflow-auto">
                    <table class="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th
                                    class="sticky left-0 top-0 z-30 min-w-[230px] border-b bg-card px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground shadow-[inset_-1px_0_0_hsl(var(--border))] md:min-w-[260px]"
                                >
                                    Region / Admin
                                </th>
                                <th
                                    v-for="(name, num) in months"
                                    :key="num"
                                    class="sticky top-0 z-20 min-w-[54px] border-b px-1.5 py-3 text-center text-[10px] font-bold uppercase tracking-[0.12em]"
                                    :class="
                                        isCurrentMonth(num)
                                            ? 'bg-blue-50 text-blue-700 dark:bg-[#12224a] dark:text-blue-300'
                                            : 'bg-card text-muted-foreground'
                                    "
                                    :title="isCurrentMonth(num) ? 'Current month' : undefined"
                                >
                                    <span class="inline-flex flex-col items-center gap-1">
                                        <span
                                            v-if="isCurrentMonth(num)"
                                            class="rounded bg-blue-600 px-1 py-px text-[8px] font-bold leading-none tracking-wider text-white dark:bg-blue-500"
                                        >
                                            NOW
                                        </span>
                                        {{ monthAbbr(name) }}
                                    </span>
                                    <span
                                        v-if="isCurrentMonth(num)"
                                        class="absolute inset-x-1.5 bottom-0 h-0.5 rounded-full bg-blue-600 dark:bg-blue-400"
                                    />
                                </th>
                                <th
                                    class="sticky top-0 z-20 min-w-[88px] border-b bg-card px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground"
                                >
                                    Rate
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(regionName, code) in regions" :key="code" class="group [&:last-child>td]:border-b-0">
                                <!-- Region label -->
                                <td
                                    class="sticky left-0 z-10 border-b border-border/70 bg-card px-4 py-2.5 shadow-[inset_-1px_0_0_hsl(var(--border))] transition-colors group-hover:bg-muted"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-14 shrink-0 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-[10px] font-extrabold tracking-wide text-blue-800 transition-colors group-hover:border-blue-300 dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-300 dark:group-hover:border-blue-800"
                                        >
                                            {{ code }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold leading-tight text-foreground">{{ regionName }}</p>
                                            <p class="mt-0.5 truncate text-xs text-muted-foreground">{{ directors[code] || '—' }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Month cells -->
                                <td
                                    v-for="(name, num) in months"
                                    :key="num"
                                    class="border-b border-border/70 px-1.5 py-2.5 text-center transition-colors"
                                    :class="isCurrentMonth(num) ? 'bg-blue-50/60 dark:bg-blue-500/[0.07]' : 'group-hover:bg-muted'"
                                >
                                    <!-- Submitted -->
                                    <template v-if="cellState(reportAt(code, num), parseInt(String(num))) === 'submitted'">
                                        <a
                                            :href="`/storage/${reportAt(code, num)!.file_path}`"
                                            target="_blank"
                                            rel="noopener"
                                            :title="`${regionName} – ${name}\nSubmitted: ${formatDate(reportAt(code, num)!.submitted_at)}\nBy: ${reportAt(code, num)!.added_by}\n\nClick to view file`"
                                            :aria-label="`View ${name} report for ${regionName}`"
                                            class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 shadow-sm transition-all duration-150 hover:scale-110 hover:border-emerald-300 hover:bg-emerald-100 hover:shadow-[0_4px_14px_-3px_rgba(16,185,129,0.45)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60 motion-reduce:hover:scale-100 dark:border-emerald-800/70 dark:bg-emerald-950/50 dark:text-emerald-400 dark:hover:border-emerald-700 dark:hover:bg-emerald-900/60"
                                        >
                                            <Check class="h-4 w-4" :stroke-width="2.75" />
                                        </a>
                                    </template>

                                    <!-- Pending — clickable to submit -->
                                    <template v-else-if="cellState(reportAt(code, num), parseInt(String(num))) === 'pending'">
                                        <button
                                            type="button"
                                            @click="openModal(String(code), name)"
                                            :title="`Click to submit ${name} report for ${regionName}`"
                                            :aria-label="`Submit ${name} report for ${regionName}`"
                                            class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg border-[1.5px] border-dashed border-amber-300 bg-amber-50/50 text-amber-600 transition-all duration-150 hover:scale-110 hover:border-amber-400 hover:bg-amber-100 hover:shadow-[0_4px_14px_-3px_rgba(245,158,11,0.45)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/60 motion-reduce:hover:scale-100 dark:border-amber-700/70 dark:bg-amber-950/20 dark:text-amber-400 dark:hover:border-amber-600 dark:hover:bg-amber-950/50"
                                        >
                                            <AlertCircle class="h-4 w-4" />
                                        </button>
                                    </template>

                                    <!-- Future -->
                                    <template v-else>
                                        <div
                                            :title="`${name} – upcoming`"
                                            class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg border border-border/60 bg-muted/60"
                                        >
                                            <span class="h-1 w-1 rounded-full bg-muted-foreground/25" />
                                        </div>
                                    </template>
                                </td>

                                <!-- Rate -->
                                <td class="border-b border-border/70 px-4 py-2.5 transition-colors group-hover:bg-muted">
                                    <div class="mx-auto flex w-16 flex-col items-stretch gap-1.5">
                                        <span class="text-right text-xs font-bold tabular-nums" :class="rateTone(regionRate(String(code))).text">
                                            {{ regionRate(String(code)) }}%
                                        </span>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-200/80 dark:bg-white/10">
                                            <div
                                                class="h-full rounded-full transition-[width] duration-700 ease-out motion-reduce:transition-none"
                                                :class="rateTone(regionRate(String(code))).bar"
                                                :style="{ width: (progressReady ? Math.min(regionRate(String(code)), 100) : 0) + '%' }"
                                            />
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Recent Submissions -->
            <section class="tdi-card tdi-accent-royal tdi-accent-to-emerald relative isolate overflow-hidden rounded-xl border">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b px-5 py-4 md:px-6">
                    <div class="flex items-center gap-3">
                        <span class="tdi-icon-chip flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                            <History class="h-4 w-4" />
                        </span>
                        <div>
                            <h2 class="text-base font-bold tracking-tight text-foreground md:text-lg">Recent Submissions</h2>
                            <p class="text-xs text-muted-foreground">Latest uploaded training monitoring reports</p>
                        </div>
                    </div>

                    <!-- Search box -->
                    <div class="relative w-full sm:w-80">
                        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search region, month, file, or uploader..."
                            aria-label="Search submissions"
                            class="h-10 w-full rounded-lg border border-border bg-card pl-10 pr-10 text-sm text-foreground shadow-sm transition-colors placeholder:text-muted-foreground/70 hover:border-slate-300 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/15 dark:hover:border-slate-600 dark:focus:border-blue-500"
                        />
                        <button
                            v-if="search"
                            type="button"
                            @click="search = ''"
                            aria-label="Clear search"
                            class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <div class="divide-y divide-border/70">
                    <div
                        v-for="r in recentSubmissions.data"
                        :key="r.id"
                        class="group flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-muted sm:flex-row sm:items-center sm:gap-4 md:px-6"
                    >
                        <div class="flex min-w-0 flex-1 items-start gap-3.5 sm:items-center">
                            <div
                                class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-400"
                            >
                                <FileText class="h-5 w-5" />
                                <span
                                    class="absolute -bottom-1.5 rounded bg-red-600 px-1 text-[8px] font-bold leading-[12px] tracking-wide text-white ring-2 ring-card dark:bg-red-500"
                                >
                                    PDF
                                </span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span
                                        class="rounded-md bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-blue-800 ring-1 ring-inset ring-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:ring-blue-900"
                                    >
                                        {{ r.region }}
                                    </span>
                                    <p class="text-sm font-semibold text-foreground">{{ regions[r.region] ?? r.region }}</p>
                                    <span class="text-muted-foreground/50">·</span>
                                    <p class="text-xs font-medium text-muted-foreground">{{ r.month }} {{ r.year }}</p>
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold tracking-wide text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-400"
                                    >
                                        <CheckCircle2 class="h-3 w-3" /> COMPLIANT
                                    </span>
                                </div>

                                <div class="mt-1 flex min-w-0 flex-wrap items-center gap-x-1.5 text-xs">
                                    <a
                                        :href="`/storage/${r.file_path}`"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex min-w-0 max-w-full items-center gap-1 font-medium text-blue-700 underline-offset-2 transition-colors hover:text-blue-800 hover:underline dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        <ExternalLink class="h-3 w-3 shrink-0" />
                                        <span class="truncate">{{ r.file_name }}</span>
                                    </a>
                                    <span class="text-muted-foreground">· by {{ r.added_by }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 pl-[3.625rem] sm:justify-end sm:pl-0">
                            <div class="sm:text-right">
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">Submitted</p>
                                <p class="text-sm font-semibold tabular-nums text-foreground">{{ formatDate(r.submitted_at) }}</p>
                            </div>
                            <div class="flex items-center gap-1">
                                <a
                                    :href="`/storage/${r.file_path}`"
                                    target="_blank"
                                    rel="noopener"
                                    title="Open file"
                                    aria-label="Open file"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/40 dark:hover:text-blue-400"
                                >
                                    <ArrowUpRight class="h-4 w-4" />
                                </a>
                                <button
                                    type="button"
                                    @click="deleteReport(r.id)"
                                    title="Delete"
                                    aria-label="Delete report"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground transition-all hover:bg-red-50 hover:text-red-600 focus-visible:opacity-100 dark:hover:bg-red-950/40 dark:hover:text-red-400 sm:opacity-0 sm:group-hover:opacity-100"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="recentSubmissions.data.length === 0" class="flex flex-col items-center px-6 py-14 text-center">
                        <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-full border bg-muted">
                            <FileText class="h-5 w-5 text-muted-foreground" />
                        </span>
                        <p class="text-sm font-semibold text-foreground">
                            {{ search ? `No results for "${search}".` : `No submissions yet for ${year}.` }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ search ? 'Try a different region, month, or file name.' : 'Submitted reports will appear here.' }}
                        </p>
                    </div>
                </div>

                <!-- Pagination footer -->
                <div
                    v-if="recentSubmissions.total > 0"
                    class="flex flex-wrap items-center justify-between gap-3 border-t bg-muted/40 px-5 py-3.5 md:px-6"
                >
                    <p class="text-xs text-muted-foreground">
                        Showing
                        <span class="font-semibold tabular-nums text-foreground">{{ recentSubmissions.from }}–{{ recentSubmissions.to }}</span>
                        of
                        <span class="font-semibold tabular-nums text-foreground">{{ recentSubmissions.total }}</span>
                    </p>

                    <div class="flex flex-wrap items-center gap-1" v-if="recentSubmissions.last_page > 1">
                        <button
                            v-for="(link, i) in recentSubmissions.links"
                            :key="i"
                            type="button"
                            :disabled="!link.url"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                            class="h-8 min-w-[32px] rounded-lg border px-2.5 text-xs font-semibold tabular-nums transition-colors"
                            :class="[
                                link.active
                                    ? 'border-blue-700 bg-blue-700 text-white shadow-sm dark:border-blue-600 dark:bg-blue-600'
                                    : 'border-border bg-card text-foreground',
                                !link.url
                                    ? 'cursor-not-allowed opacity-40'
                                    : link.active
                                      ? 'cursor-default'
                                      : 'cursor-pointer hover:border-blue-300 hover:text-blue-700 dark:hover:border-blue-800 dark:hover:text-blue-300',
                            ]"
                        />
                    </div>
                </div>
            </section>

            <!-- ===== Submit Modal ===== -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 backdrop-blur-[2px] sm:items-center sm:p-4"
                    @click.self="showModal = false"
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="tpmr-modal-title"
                        class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border bg-card shadow-2xl sm:rounded-2xl"
                    >
                        <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5">
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-300"
                                >
                                    <Upload class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 id="tpmr-modal-title" class="text-base font-bold tracking-tight text-foreground sm:text-lg">
                                        Submit Signed TPMR
                                    </h2>
                                    <p class="text-xs text-muted-foreground">Upload the Training Program Monitoring PDF</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="showModal = false"
                                aria-label="Close"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="flex flex-1 flex-col gap-6 overflow-y-auto px-5 py-5 sm:px-6">
                            <!-- Report details -->
                            <section class="flex flex-col gap-4">
                                <h3 class="flex items-center gap-3 text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground">
                                    Report Details <span class="h-px flex-1 bg-border" />
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <label for="tpmr-region" class="text-xs font-semibold text-foreground">Region</label>
                                        <div class="relative">
                                            <select
                                                id="tpmr-region"
                                                v-model="form.region"
                                                class="appearance-none pr-9"
                                                :class="[inputClass, fieldStateClass(form.errors.region)]"
                                            >
                                                <option value="" disabled>Select region...</option>
                                                <option v-for="opt in regionOptions" :key="opt.code" :value="opt.code">
                                                    {{ opt.code }} – {{ opt.name }}
                                                </option>
                                            </select>
                                            <ChevronDown
                                                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                            />
                                        </div>
                                        <p
                                            v-if="form.errors.region"
                                            class="flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400"
                                        >
                                            <AlertCircle class="h-3.5 w-3.5 shrink-0" /> {{ form.errors.region }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label for="tpmr-month" class="text-xs font-semibold text-foreground">Month</label>
                                        <div class="relative">
                                            <select
                                                id="tpmr-month"
                                                v-model="form.month"
                                                class="appearance-none pr-9"
                                                :class="[inputClass, fieldStateClass(form.errors.month)]"
                                            >
                                                <option value="" disabled>Select month...</option>
                                                <option v-for="opt in monthOptions" :key="opt.num" :value="opt.name">
                                                    {{ opt.name }}
                                                </option>
                                            </select>
                                            <ChevronDown
                                                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                            />
                                        </div>
                                        <p
                                            v-if="form.errors.month"
                                            class="flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400"
                                        >
                                            <AlertCircle class="h-3.5 w-3.5 shrink-0" /> {{ form.errors.month }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label for="tpmr-year" class="text-xs font-semibold text-foreground">Year</label>
                                    <input
                                        id="tpmr-year"
                                        v-model.number="form.year"
                                        type="number"
                                        min="2000"
                                        max="2100"
                                        placeholder="e.g. 2025"
                                        class="tabular-nums"
                                        :class="[inputClass, fieldStateClass(form.errors.year)]"
                                    />
                                    <p v-if="form.errors.year" class="flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400">
                                        <AlertCircle class="h-3.5 w-3.5 shrink-0" /> {{ form.errors.year }}
                                    </p>
                                    <p v-else class="text-[11px] text-muted-foreground">
                                        Defaults to the viewed year — change it if submitting for a different year.
                                    </p>
                                </div>
                            </section>

                            <!-- Additional information -->
                            <section class="flex flex-col gap-4">
                                <h3 class="flex items-center gap-3 text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground">
                                    Additional Information <span class="h-px flex-1 bg-border" />
                                </h3>
                                <div class="flex flex-col gap-1.5">
                                    <label for="tpmr-notes" class="text-xs font-semibold text-foreground">
                                        Notes <span class="font-normal text-muted-foreground">(Optional)</span>
                                    </label>
                                    <textarea
                                        id="tpmr-notes"
                                        v-model="form.notes"
                                        rows="3"
                                        placeholder="Brief summary of training activities..."
                                        class="h-auto resize-none py-2.5"
                                        :class="[inputClass, 'border-border']"
                                    />
                                </div>
                            </section>

                            <!-- Document -->
                            <section class="flex flex-col gap-4">
                                <h3 class="flex items-center gap-3 text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground">
                                    Document <span class="h-px flex-1 bg-border" />
                                </h3>
                                <div class="flex flex-col gap-1.5">
                                    <span class="text-xs font-semibold text-foreground">PDF Report</span>

                                    <div
                                        v-if="!selectedFile"
                                        role="button"
                                        tabindex="0"
                                        aria-label="Choose a PDF file"
                                        class="group flex cursor-pointer flex-col items-center gap-3 rounded-xl border-[1.5px] border-dashed px-6 py-7 text-center transition-all focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-500/15 [&_*]:pointer-events-none"
                                        :class="
                                            isDraggingFile
                                                ? 'border-blue-500 bg-blue-50 dark:border-blue-500 dark:bg-blue-950/30'
                                                : form.errors.pdf
                                                  ? 'border-red-300 bg-red-50/40 hover:border-red-400 dark:border-red-800 dark:bg-red-950/10'
                                                  : 'border-border bg-muted/40 hover:border-blue-400 hover:bg-blue-50/50 dark:hover:border-blue-700 dark:hover:bg-blue-950/20'
                                        "
                                        @click="fileInput?.click()"
                                        @keydown.enter.prevent="fileInput?.click()"
                                        @keydown.space.prevent="fileInput?.click()"
                                        @dragenter.prevent="isDraggingFile = true"
                                        @dragover.prevent="isDraggingFile = true"
                                        @dragleave.prevent="isDraggingFile = false"
                                        @drop.prevent="onFileDrop"
                                    >
                                        <span
                                            class="flex h-12 w-12 items-center justify-center rounded-full bg-card shadow-sm ring-1 ring-border transition-all duration-200 group-hover:-translate-y-0.5 motion-reduce:group-hover:translate-y-0"
                                            :class="
                                                isDraggingFile
                                                    ? 'text-blue-600 dark:text-blue-400'
                                                    : 'text-muted-foreground group-hover:text-blue-600 dark:group-hover:text-blue-400'
                                            "
                                        >
                                            <CloudUpload class="h-5 w-5" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-foreground">
                                                {{ isDraggingFile ? 'Drop to attach your PDF' : 'Drag & drop your PDF here' }}
                                            </p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">
                                                or <span class="font-semibold text-blue-700 dark:text-blue-400">click to browse</span>
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full border bg-card px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
                                        >
                                            PDF • Maximum 10 MB
                                        </span>
                                    </div>

                                    <div
                                        v-else
                                        class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/30"
                                    >
                                        <span
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400"
                                        >
                                            <FileCheck2 class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-foreground" :title="selectedFile.name">
                                                {{ selectedFile.name }}
                                            </p>
                                            <p class="text-xs text-emerald-700/90 dark:text-emerald-400/90">
                                                PDF • {{ formatFileSize(selectedFile.size) }} ·
                                                <button
                                                    type="button"
                                                    class="font-semibold underline-offset-2 hover:underline"
                                                    @click="fileInput?.click()"
                                                >
                                                    Replace
                                                </button>
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            aria-label="Remove file"
                                            title="Remove file"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-emerald-700/70 transition-colors hover:bg-emerald-100 hover:text-emerald-800 dark:text-emerald-400/70 dark:hover:bg-emerald-900/40 dark:hover:text-emerald-300"
                                            @click="clearSelectedFile"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <input ref="fileInput" type="file" accept=".pdf" class="hidden" @change="onFileChange" />
                                    <p v-if="form.errors.pdf" class="flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400">
                                        <AlertCircle class="h-3.5 w-3.5 shrink-0" /> {{ form.errors.pdf }}
                                    </p>
                                </div>
                            </section>
                        </div>

                        <div class="flex justify-end gap-2.5 border-t bg-muted/40 px-5 py-4 sm:px-6">
                            <Button variant="outline" class="rounded-lg" @click="showModal = false">Cancel</Button>
                            <Button
                                class="gap-1.5 rounded-lg bg-blue-700 font-semibold text-white shadow-sm shadow-blue-700/25 hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500"
                                :disabled="form.processing"
                                @click="submit"
                            >
                                <Upload v-if="!form.processing" class="h-4 w-4" />
                                {{ form.processing ? 'Submitting...' : 'Submit Report' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>
