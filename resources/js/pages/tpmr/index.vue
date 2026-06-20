<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ref, computed } from 'vue';
import {
    X, Upload, FileText, Trash2, ExternalLink,
    CheckCircle2, AlertCircle
} from 'lucide-vue-next';

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

const props = defineProps<{
    matrix: Record<string, Record<number, Report | null>>;
    regions: Record<string, string>;
    directors: Record<string, string>;
    months: Record<number, string>;
    year: number;
    currentMonth: number;
    stats: Stats;
    recentSubmissions: Report[];
    availableYears: number[];
}>();

// --- Year filter ---
const selectedYear = ref(props.year);
const changeYear = (y: number) => {
    router.get(route('tpmr.index'), { year: y }, { preserveScroll: false });
};

// --- Modal ---
const showModal = ref(false);

const form = useForm({
    region: '',
    month:  '',
    year:   props.year,
    notes:  '',
    pdf:    null as File | null,
});

const openModal = (region = '', monthName = '') => {
    form.reset();
    form.year   = props.year;
    form.region = region;
    form.month  = monthName;
    selectedFile.value = null;
    showModal.value = true;
};

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);

const onFileChange = (e: Event) => {
    const f = (e.target as HTMLInputElement).files?.[0] ?? null;
    selectedFile.value = f;
    form.pdf = f;  // ✅ fixed: was form.file
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

const deleteReport = (id: number) => {
    if (!confirm('Delete this report?')) return;
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
        month: 'short', day: 'numeric', year: 'numeric',
    });

const monthOptions = computed(() =>
    Object.entries(props.months).map(([num, name]) => ({ num: parseInt(num), name }))
);

const regionOptions = computed(() =>
    Object.entries(props.regions).map(([code, name]) => ({ code, name }))
);

const regionSubmittedCount = (code: string) =>
    Object.values(props.matrix[code] ?? {}).filter(r => r !== null).length;

const regionRate = (code: string) =>
    Math.round((regionSubmittedCount(code) / props.currentMonth) * 100);
</script>

<template>
    <Head title="TPMR – Training Program Monitoring Reports" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">

            <!-- Header -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-emerald-600 flex items-center gap-1.5 mb-1">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 inline-block animate-pulse" />
                        Compliance Dashboard
                    </p>
                    <h1 class="text-3xl md:text-4xl font-extrabold leading-tight">Training Program Monitoring Reports</h1>
                    <p class="text-sm text-muted-foreground mt-1">Monthly regional submission tracker for training reports</p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <div class="border rounded-xl px-4 py-2 text-center shadow-sm bg-background">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Year</p>
                        <select
                            :value="selectedYear"
                            @change="changeYear(parseInt(($event.target as HTMLSelectElement).value))"
                            class="text-2xl font-extrabold bg-transparent border-none outline-none text-center cursor-pointer"
                        >
                            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <Button class="bg-foreground text-background hover:bg-foreground/90 gap-2 shadow-sm" @click="openModal()">
                        <Upload class="h-4 w-4" /> New Submission
                    </Button>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="rounded-2xl border bg-background p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground mb-2">Total Required</p>
                    <p class="text-4xl font-extrabold">{{ stats.totalRequired }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">slots</p>
                </div>
                <div class="rounded-2xl border bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-900 p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600 mb-2">Compliant</p>
                    <p class="text-4xl font-extrabold text-emerald-700 dark:text-emerald-400">{{ stats.submitted }}</p>
                    <p class="text-xs text-emerald-600/70 mt-0.5">submitted</p>
                </div>
                <div class="rounded-2xl border bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900 p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-600 mb-2">Pending</p>
                    <p class="text-4xl font-extrabold text-amber-700 dark:text-amber-400">{{ stats.pending }}</p>
                    <p class="text-xs text-amber-600/70 mt-0.5">overdue</p>
                </div>
                <div class="rounded-2xl bg-gradient-to-br from-blue-600 to-blue-800 text-background p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest opacity-60 mb-2">Compliance Rate</p>
                    <p class="text-4xl font-extrabold">{{ stats.rate }}%</p>
                    <div class="mt-2 h-1.5 rounded-full bg-white/20">
                        <div class="h-full rounded-full bg-emerald-400 transition-all" :style="{ width: stats.rate + '%' }" />
                    </div>
                </div>
            </div>

            <!-- Matrix -->
            <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h2 class="text-lg font-extrabold">Regional Compliance Matrix</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Each cell represents a regional admin's monthly submission status</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-muted-foreground">
                        <span class="flex items-center gap-1.5">
                            <span class="h-4 w-4 rounded bg-emerald-100 border border-emerald-300 flex items-center justify-center">
                                <CheckCircle2 class="h-2.5 w-2.5 text-emerald-600" />
                            </span> Submitted
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="h-4 w-4 rounded border-2 border-dashed border-amber-400 flex items-center justify-center">
                                <AlertCircle class="h-2.5 w-2.5 text-amber-500" />
                            </span> Pending
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="h-4 w-4 rounded bg-muted border border-border" />
                            Future
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/30">
                                <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wide text-muted-foreground min-w-[220px]">
                                    Region / Admin
                                </th>
                                <th
                                    v-for="(name, num) in months"
                                    :key="num"
                                    class="px-2 py-3 text-xs font-bold uppercase tracking-wide text-center w-16"
                                    :class="parseInt(String(num)) === currentMonth ? 'text-blue-600' : 'text-muted-foreground'"
                                >
                                    {{ monthAbbr(name) }}
                                </th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-muted-foreground text-center w-16">
                                    Rate
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(regionName, code) in regions"
                                :key="code"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <!-- Region label -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-blue-700 text-background flex items-center justify-center text-[10px] font-extrabold shrink-0">
                                            {{ code }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-sm leading-tight">{{ regionName }}</p>
                                            <p class="text-xs text-muted-foreground">{{ directors[code] ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Month cells -->
                                <td
                                    v-for="(name, num) in months"
                                    :key="num"
                                    class="px-2 py-3 text-center"
                                >
                                    <!-- Submitted -->
                                    <template v-if="cellState(matrix[code]?.[parseInt(String(num))] ?? null, parseInt(String(num))) === 'submitted'">
                                        
                                        <a :href="`/storage/${matrix[code][parseInt(String(num))]!.file_path}`"
                                            target="_blank"
                                            :title="`${regionName} – ${name}\nSubmitted: ${formatDate(matrix[code][parseInt(String(num))]!.submitted_at)}\nBy: ${matrix[code][parseInt(String(num))]!.added_by}\n\nClick to view file`"
                                            class="h-9 w-9 mx-auto rounded-xl bg-emerald-100 border border-emerald-300 dark:bg-emerald-950/40 dark:border-emerald-700 flex items-center justify-center hover:scale-110 hover:bg-emerald-200 dark:hover:bg-emerald-900/60 transition-transform"
                                        >
                                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                                        </a>
                                    </template>

                                    <!-- Pending — clickable to submit -->
                                    <template v-else-if="cellState(matrix[code]?.[parseInt(String(num))] ?? null, parseInt(String(num))) === 'pending'">
                                        <button
                                            @click="openModal(String(code), name)"
                                            :title="`Click to submit ${name} report for ${regionName}`"
                                            class="h-9 w-9 mx-auto rounded-xl border-2 border-dashed border-amber-400 dark:border-amber-600 flex items-center justify-center hover:bg-amber-50 dark:hover:bg-amber-950/30 hover:scale-110 transition-all"
                                        >
                                            <AlertCircle class="h-4 w-4 text-amber-500" />
                                        </button>
                                    </template>

                                    <!-- Future -->
                                    <template v-else>
                                        <div class="h-9 w-9 mx-auto rounded-xl bg-muted border border-border" />
                                    </template>
                                </td>

                                <!-- Rate -->
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="text-xs font-bold px-2 py-1 rounded-full"
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400': regionRate(String(code)) >= 80,
                                            'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400': regionRate(String(code)) > 0 && regionRate(String(code)) < 80,
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
            <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-lg font-bold">Recent Submissions</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Latest uploaded training monitoring reports</p>
                </div>

                <div class="divide-y">
                    <div
                        v-for="r in recentSubmissions"
                        :key="r.id"
                        class="flex items-center gap-4 px-6 py-4 hover:bg-muted/20 transition-colors group"
                    >
                        <div class="h-12 w-12 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 flex items-center justify-center shrink-0">
                            <FileText class="h-6 w-6 text-red-500" />
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold text-sm">{{ regions[r.region] ?? r.region }}</p>
                                <span class="text-xs text-muted-foreground">·</span>
                                <p class="text-xs text-muted-foreground">{{ r.month }} {{ r.year }}</p>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                    COMPLIANT
                                </span>
                            </div>
                            
                            <a :href="`/storage/${r.file_path}`"
                                target="_blank"
                                class="flex items-center gap-1 text-xs text-blue-600 hover:underline mt-0.5"
                            >
                                <ExternalLink class="h-3 w-3" /> {{ r.file_name }}
                                <span class="text-muted-foreground">· by {{ r.added_by }}</span>
                            </a>
                        </div>

                        <div class="text-right shrink-0">
                            <p class="text-xs text-muted-foreground">Submitted</p>
                            <p class="text-sm font-semibold">{{ formatDate(r.submitted_at) }}</p>
                        </div>
                        <button
                            @click="deleteReport(r.id)"
                            class="p-1.5 rounded-lg text-muted-foreground hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors opacity-0 group-hover:opacity-100"
                            title="Delete"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div v-if="recentSubmissions.length === 0" class="px-6 py-12 text-center text-muted-foreground">
                        <FileText class="h-10 w-10 mx-auto mb-2 opacity-30" />
                        <p class="text-sm font-semibold">No submissions yet for {{ year }}.</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===== Submit Modal ===== -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showModal = false"
        >
            <div class="bg-background rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

                <div class="flex items-center justify-between px-6 py-5 border-b">
                    <div>
                        <h2 class="text-lg font-bold">Submit Signed TPMR</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Upload the Training Program Monitoring PDF</p>
                    </div>
                    <button @click="showModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-5">

                    <!-- Region + Month + Year -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Region</label>
                            <select v-model="form.region" class="border border-border rounded-xl px-3 py-2.5 text-sm bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="" disabled>Select region...</option>
                                <option v-for="opt in regionOptions" :key="opt.code" :value="opt.code">
                                    {{ opt.code }} – {{ opt.name }}
                                </option>
                            </select>
                            <span v-if="form.errors.region" class="text-xs text-red-500">{{ form.errors.region }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Month</label>
                            <select v-model="form.month" class="border border-border rounded-xl px-3 py-2.5 text-sm bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                            Year <span class="normal-case font-normal text-muted-foreground">(defaults to viewed year — change if submitting for a different year)</span>
                        </label>
                        <input
                            v-model.number="form.year"
                            type="number"
                            min="2000"
                            max="2100"
                            placeholder="e.g. 2025"
                            class="border border-border rounded-xl px-3 py-2.5 text-sm bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <span v-if="form.errors.year" class="text-xs text-red-500">{{ form.errors.year }}</span>
                    </div>

                    <!-- Notes -->
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Notes <span class="normal-case font-normal">(Optional)</span></label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Brief summary of training activities..."
                            class="border border-border rounded-xl px-3 py-2.5 text-sm bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-foreground resize-none"
                        />
                    </div>

                    <!-- File Upload -->
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">PDF Report</label>
                        <div
                            class="border-2 border-dashed border-border rounded-xl p-6 flex flex-col items-center gap-2 cursor-pointer hover:border-foreground/40 transition-colors"
                            :class="selectedFile ? 'border-emerald-400 bg-emerald-50 dark:bg-emerald-950/20' : ''"
                            @click="fileInput?.click()"
                        >
                            <div class="h-12 w-12 rounded-xl flex items-center justify-center"
                                :class="selectedFile ? 'bg-emerald-100 dark:bg-emerald-950/40' : 'bg-muted'"
                            >
                                <Upload class="h-6 w-6" :class="selectedFile ? 'text-emerald-600' : 'text-muted-foreground'" />
                            </div>
                            <p class="text-sm font-semibold text-center" :class="selectedFile ? 'text-emerald-700 dark:text-emerald-400' : ''">
                                {{ selectedFile ? selectedFile.name : 'Click to choose a PDF file' }}
                            </p>
                            <p class="text-xs text-muted-foreground">PDF only, max ~2GB</p>
                        </div>
                        <input ref="fileInput" type="file" accept=".pdf" class="hidden" @change="onFileChange" />
                        <span v-if="form.errors.pdf" class="text-xs text-red-500">{{ form.errors.pdf }}</span>
                    </div>

                </div>

                <div class="flex justify-end gap-3 px-6 py-4 border-t">
                    <Button variant="outline" @click="showModal = false">Cancel</Button>
                    <Button
                        class="bg-foreground text-background hover:bg-foreground/90"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        <Upload v-if="!form.processing" class="h-4 w-4 mr-1.5" />
                        {{ form.processing ? 'Submitting...' : 'Submit Report' }}
                    </Button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>