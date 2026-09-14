<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, ExternalLink, FileText, Search, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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

const onFileChange = (e: Event) => {
    const f = (e.target as HTMLInputElement).files?.[0] ?? null;

    if (f && f.size > 10 * 1024 * 1024) {
        // 10MB
        form.errors.pdf = 'File is too large. Maximum size is 10MB.';
        selectedFile.value = null;
        form.pdf = null;
        if (fileInput.value) fileInput.value.value = ''; // i-reset ang input
        return;
    }

    form.clearErrors('pdf');
    selectedFile.value = f;
    form.pdf = f;
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

const monthAbbr = (name: string) => name.slice(0, 3).toUpperCase();

const formatDate = (d: string) =>
    new Date(d.includes('T') ? d : d + 'T00:00:00').toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

const monthOptions = computed(() => Object.entries(props.months).map(([num, name]) => ({ num: parseInt(num), name })));

const regionOptions = computed(() => Object.entries(props.regions).map(([code, name]) => ({ code, name })));

const regionSubmittedCount = (code: string) => Object.values(props.matrix[code] ?? {}).filter((r) => r !== null).length;

const regionRate = (code: string) => Math.round((regionSubmittedCount(code) / props.currentMonth) * 100);
</script>

<template>
    <Head title="TPMR – Training Program Monitoring Reports" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header-->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="mb-1 flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest text-emerald-600">
                        <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-500" />
                        Compliance Dashboard
                    </p>
                    <h1 class="text-3xl font-extrabold leading-tight md:text-4xl">Training Program Monitoring Reports</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Monthly regional submission tracker for training reports</p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <div class="rounded-xl border bg-background px-4 py-2 text-center shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Year</p>
                        <select
                            :value="selectedYear"
                            @change="changeYear(parseInt(($event.target as HTMLSelectElement).value))"
                            class="cursor-pointer border-none bg-transparent text-center text-2xl font-extrabold outline-none"
                        >
                            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <Button class="gap-2 bg-foreground text-background shadow-sm hover:bg-foreground/90" @click="openModal()">
                        <Upload class="h-4 w-4" /> New Submission
                    </Button>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-2xl border bg-background p-5 shadow-sm">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Total Required</p>
                    <p class="text-4xl font-extrabold">{{ stats.totalRequired }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">slots</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/30">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-emerald-600">Compliant</p>
                    <p class="text-4xl font-extrabold text-emerald-700 dark:text-emerald-400">{{ stats.submitted }}</p>
                    <p class="mt-0.5 text-xs text-emerald-600/70">submitted</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/30">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-amber-600">Pending</p>
                    <p class="text-4xl font-extrabold text-amber-700 dark:text-amber-400">{{ stats.pending }}</p>
                    <p class="mt-0.5 text-xs text-amber-600/70">overdue</p>
                </div>
                <div class="rounded-2xl bg-gradient-to-br from-blue-600 to-blue-800 p-5 text-background shadow-sm">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest opacity-60">Compliance Rate</p>
                    <p class="text-4xl font-extrabold">{{ stats.rate }}%</p>
                    <div class="mt-2 h-1.5 rounded-full bg-white/20">
                        <div class="h-full rounded-full bg-emerald-400 transition-all" :style="{ width: stats.rate + '%' }" />
                    </div>
                </div>
            </div>

            <!-- Matrix -->
            <div class="overflow-hidden rounded-2xl border bg-background shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b px-6 py-4">
                    <div>
                        <h2 class="text-lg font-extrabold">Regional Compliance Matrix</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">Each cell represents a regional admin's monthly submission status</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-muted-foreground">
                        <span class="flex items-center gap-1.5">
                            <span class="flex h-4 w-4 items-center justify-center rounded border border-emerald-300 bg-emerald-100">
                                <CheckCircle2 class="h-2.5 w-2.5 text-emerald-600" />
                            </span>
                            Submitted
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="flex h-4 w-4 items-center justify-center rounded border-2 border-dashed border-amber-400">
                                <AlertCircle class="h-2.5 w-2.5 text-amber-500" />
                            </span>
                            Pending
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="h-4 w-4 rounded border border-border bg-muted" />
                            Future
                        </span>
                    </div>
                </div>

                <div class="max-h-[65vh] overflow-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/30">
                                <th
                                    class="sticky top-0 z-10 min-w-[220px] border-b bg-background px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-muted-foreground"
                                >
                                    Region / Admin
                                </th>
                                <th
                                    v-for="(name, num) in months"
                                    :key="num"
                                    class="sticky top-0 z-10 w-16 border-b bg-background px-2 py-3 text-center text-xs font-bold uppercase tracking-wide"
                                    :class="parseInt(String(num)) === currentMonth ? 'text-blue-600' : 'text-muted-foreground'"
                                >
                                    {{ monthAbbr(name) }}
                                </th>
                                <th
                                    class="sticky top-0 z-10 w-16 border-b bg-background px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-muted-foreground"
                                >
                                    Rate
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(regionName, code) in regions" :key="code" class="transition-colors hover:bg-muted/20">
                                <!-- Region label -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-700 text-[10px] font-extrabold text-background"
                                        >
                                            {{ code }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold leading-tight">{{ regionName }}</p>
                                            <p class="text-xs text-muted-foreground">{{ directors[code] ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Month cells -->
                                <td v-for="(name, num) in months" :key="num" class="px-2 py-3 text-center">
                                    <!-- Submitted -->
                                    <template v-if="cellState(matrix[code]?.[parseInt(String(num))] ?? null, parseInt(String(num))) === 'submitted'">
                                        <a
                                            :href="`/storage/${matrix[code][parseInt(String(num))]!.file_path}`"
                                            target="_blank"
                                            :title="`${regionName} – ${name}\nSubmitted: ${formatDate(matrix[code][parseInt(String(num))]!.submitted_at)}\nBy: ${matrix[code][parseInt(String(num))]!.added_by}\n\nClick to view file`"
                                            class="mx-auto flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-300 bg-emerald-100 transition-transform hover:scale-110 hover:bg-emerald-200 dark:border-emerald-700 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/60"
                                        >
                                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                                        </a>
                                    </template>

                                    <!-- Pending — clickable to submit -->
                                    <template
                                        v-else-if="cellState(matrix[code]?.[parseInt(String(num))] ?? null, parseInt(String(num))) === 'pending'"
                                    >
                                        <button
                                            @click="openModal(String(code), name)"
                                            :title="`Click to submit ${name} report for ${regionName}`"
                                            class="mx-auto flex h-9 w-9 items-center justify-center rounded-xl border-2 border-dashed border-amber-400 transition-all hover:scale-110 hover:bg-amber-50 dark:border-amber-600 dark:hover:bg-amber-950/30"
                                        >
                                            <AlertCircle class="h-4 w-4 text-amber-500" />
                                        </button>
                                    </template>

                                    <!-- Future -->
                                    <template v-else>
                                        <div class="mx-auto h-9 w-9 rounded-xl border border-border bg-muted" />
                                    </template>
                                </td>

                                <!-- Rate -->
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-bold"
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400':
                                                regionRate(String(code)) >= 80,
                                            'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400':
                                                regionRate(String(code)) > 0 && regionRate(String(code)) < 80,
                                            'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400': regionRate(String(code)) === 0,
                                        }"
                                    >
                                        {{ regionRate(String(code)) }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Submissions -->
            <div class="overflow-hidden rounded-2xl border bg-background shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b px-6 py-4">
                    <div>
                        <h2 class="text-lg font-bold">Recent Submissions</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">Latest uploaded training monitoring reports</p>
                    </div>

                    <!-- Search box -->
                    <div class="relative w-full sm:w-72">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search region, file, by..."
                            class="w-full rounded-xl border border-border bg-background py-2 pl-9 pr-9 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <button
                            v-if="search"
                            @click="search = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="divide-y">
                    <div
                        v-for="r in recentSubmissions.data"
                        :key="r.id"
                        class="group flex items-center gap-4 px-6 py-4 transition-colors hover:bg-muted/20"
                    >
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30"
                        >
                            <FileText class="h-6 w-6 text-red-500" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold">{{ regions[r.region] ?? r.region }}</p>
                                <span class="text-xs text-muted-foreground">·</span>
                                <p class="text-xs text-muted-foreground">{{ r.month }} {{ r.year }}</p>
                                <span
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                                >
                                    COMPLIANT
                                </span>
                            </div>

                            <a
                                :href="`/storage/${r.file_path}`"
                                target="_blank"
                                class="mt-0.5 flex items-center gap-1 text-xs text-blue-600 hover:underline"
                            >
                                <ExternalLink class="h-3 w-3" /> {{ r.file_name }}
                                <span class="text-muted-foreground">· by {{ r.added_by }}</span>
                            </a>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-xs text-muted-foreground">Submitted</p>
                            <p class="text-sm font-semibold">{{ formatDate(r.submitted_at) }}</p>
                        </div>
                        <button
                            @click="deleteReport(r.id)"
                            class="rounded-lg p-1.5 text-muted-foreground opacity-0 transition-colors hover:bg-red-50 hover:text-red-600 group-hover:opacity-100 dark:hover:bg-red-950/30"
                            title="Delete"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Empty state -->
                    <div v-if="recentSubmissions.data.length === 0" class="px-6 py-12 text-center text-muted-foreground">
                        <FileText class="mx-auto mb-2 h-10 w-10 opacity-30" />
                        <p class="text-sm font-semibold">
                            {{ search ? `No results for "${search}".` : `No submissions yet for ${year}.` }}
                        </p>
                    </div>
                </div>

                <!-- Pagination footer -->
                <div v-if="recentSubmissions.total > 0" class="flex flex-wrap items-center justify-between gap-4 border-t px-6 py-4">
                    <p class="text-xs text-muted-foreground">
                        Showing {{ recentSubmissions.from }}–{{ recentSubmissions.to }} of {{ recentSubmissions.total }}
                    </p>

                    <div class="flex items-center gap-1" v-if="recentSubmissions.last_page > 1">
                        <button
                            v-for="(link, i) in recentSubmissions.links"
                            :key="i"
                            :disabled="!link.url"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                            class="h-8 min-w-[34px] rounded-lg border px-2 text-xs font-semibold transition-colors"
                            :class="[
                                link.active
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-border bg-background text-foreground hover:bg-muted',
                                !link.url ? 'cursor-not-allowed opacity-40' : 'cursor-pointer',
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Submit Modal ===== -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-background shadow-2xl">
                <div class="flex items-center justify-between border-b px-6 py-5">
                    <div>
                        <h2 class="text-lg font-bold">Submit Signed TPMR</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">Upload the Training Program Monitoring PDF</p>
                    </div>
                    <button @click="showModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex flex-col gap-5 p-6">
                    <!-- Region + Month + Year -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Region</label>
                            <select
                                v-model="form.region"
                                class="rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="" disabled>Select region...</option>
                                <option v-for="opt in regionOptions" :key="opt.code" :value="opt.code">{{ opt.code }} – {{ opt.name }}</option>
                            </select>
                            <span v-if="form.errors.region" class="text-xs text-red-500">{{ form.errors.region }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Month</label>
                            <select
                                v-model="form.month"
                                class="rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="" disabled>Select month...</option>
                                <option v-for="opt in monthOptions" :key="opt.num" :value="opt.name">
                                    {{ opt.name }}
                                </option>
                            </select>
                            <span v-if="form.errors.month" class="text-xs text-red-500">{{ form.errors.month }}</span>
                        </div>
                    </div>

                    <!-- Year (separate row so it's prominent) -->
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                            Year
                            <span class="font-normal normal-case text-muted-foreground"
                                >(defaults to viewed year — change if submitting for a different year)</span
                            >
                        </label>
                        <input
                            v-model.number="form.year"
                            type="number"
                            min="2000"
                            max="2100"
                            placeholder="e.g. 2025"
                            class="rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <span v-if="form.errors.year" class="text-xs text-red-500">{{ form.errors.year }}</span>
                    </div>

                    <!-- Notes -->
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground"
                            >Notes <span class="font-normal normal-case">(Optional)</span></label
                        >
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Brief summary of training activities..."
                            class="resize-none rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-foreground"
                        />
                    </div>

                    <!-- File Upload -->
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">PDF Report</label>
                        <div
                            class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-border p-6 transition-colors hover:border-foreground/40"
                            :class="selectedFile ? 'border-emerald-400 bg-emerald-50 dark:bg-emerald-950/20' : ''"
                            @click="fileInput?.click()"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl"
                                :class="selectedFile ? 'bg-emerald-100 dark:bg-emerald-950/40' : 'bg-muted'"
                            >
                                <Upload class="h-6 w-6" :class="selectedFile ? 'text-emerald-600' : 'text-muted-foreground'" />
                            </div>
                            <p class="text-center text-sm font-semibold" :class="selectedFile ? 'text-emerald-700 dark:text-emerald-400' : ''">
                                {{ selectedFile ? selectedFile.name : 'Click to choose a PDF file' }}
                            </p>
                            <p class="text-xs text-muted-foreground">PDF only, max 10MB</p>
                        </div>
                        <input ref="fileInput" type="file" accept=".pdf" class="hidden" @change="onFileChange" />
                        <span v-if="form.errors.pdf" class="text-xs text-red-500">{{ form.errors.pdf }}</span>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t px-6 py-4">
                    <Button variant="outline" @click="showModal = false">Cancel</Button>
                    <Button class="bg-foreground text-background hover:bg-foreground/90" :disabled="form.processing" @click="submit">
                        <Upload v-if="!form.processing" class="mr-1.5 h-4 w-4" />
                        {{ form.processing ? 'Submitting...' : 'Submit Report' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
