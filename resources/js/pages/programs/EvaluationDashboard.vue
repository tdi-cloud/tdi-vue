<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    BarChart3,
    Check,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Clock,
    Copy,
    FileText,
    Inbox,
    Loader2,
    MessageSquareText,
    Settings2,
    Sparkles,
    Star,
    Trash2,
    UserRound,
    Users2,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const { confirmDialog } = useConfirm();

interface Batch {
    id: number;
    batch: string;
    evaluation_form?: { id: number; batch_id: number; is_active: boolean } | null;
}

interface Program {
    id: number;
    program_code: string;
    batches?: Batch[];
}

const props = defineProps<{ program: Program }>();

const batchesWithForms = computed(() => (props.program.batches ?? []).filter((b) => b.evaluation_form));

/* ── Quick generate/manage per batch, without leaving this modal ─────────── */
const generatingBatchId = ref<number | null>(null);

function generateDefault(batch: Batch) {
    generatingBatchId.value = batch.id;
    router.post(
        route('batches.evaluation-form.store', batch.id),
        { mode: 'default' },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                generatingBatchId.value = null;
            },
        },
    );
}

function manageBatch(batch: Batch) {
    router.visit(route('batches.evaluation.edit', batch.id));
}

const filterBatchId = ref<string>('all');

const loading = ref(false);
const errorMsg = ref('');

const totalResponses = ref(0);
const avgBySection = ref<{ section_key: string; section_title: string; avg_rating: number }[]>([]);
const avgByFacilitator = ref<{ id: number; name: string; avg_rating: number }[]>([]);
const overallDistribution = ref<{ rating: number; total: number }[]>([]);
const responsesPerBatch = ref<{ batch_id: number; batch_label: string; total: number }[]>([]);
const findings = ref<{ intro: string; items: { label: string; text: string }[] } | null>(null);

const findingsCopied = ref(false);

/** Copies section "Program Evaluation Findings and Observations" as rich text. */
async function copyFindings() {
    if (!findings.value) return;

    const heading = 'Program Evaluation Findings and Observations';
    const items = findings.value.items.map((item) => `<li><b>${escapeHtml(item.label)}:</b> ${escapeHtml(item.text)}</li>`).join('');
    const html = `<p><b>${heading}</b></p><p>${escapeHtml(findings.value.intro)}</p><ul>${items}</ul>`;
    const plain = [heading, findings.value.intro, ...findings.value.items.map((item) => `• ${item.label}: ${item.text}`)].join('\n\n');

    await writeRichText(html, plain);

    findingsCopied.value = true;
    setTimeout(() => (findingsCopied.value = false), 2000);
}

async function writeRichText(html: string, plain: string) {
    if (typeof ClipboardItem !== 'undefined') {
        await navigator.clipboard.write([
            new ClipboardItem({
                'text/html': new Blob([html], { type: 'text/html' }),
                'text/plain': new Blob([plain], { type: 'text/plain' }),
            }),
        ]);
    } else {
        await navigator.clipboard.writeText(plain);
    }
}

function escapeHtml(text: string) {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const avgOverallRating = computed(() => {
    const overall = avgBySection.value.find((s) => s.section_key === 'overall');
    return overall ? Number(overall.avg_rating).toFixed(1) : '—';
});

/* ---- Chart theming: ApexCharts doesn't follow the app's `dark` class, so feed it theme-aware colors ---- */
const isDark = ref(document.documentElement.classList.contains('dark'));
let themeObserver: MutationObserver | null = null;

function syncIsDark() {
    isDark.value = document.documentElement.classList.contains('dark');
}

const chartTheme = computed(() =>
    isDark.value
        ? { text: '#cbd5e1', strongText: '#f1f5f9', grid: '#334155', surface: '#0a0a0a', tooltip: 'dark' as const }
        : { text: '#475569', strongText: '#334155', grid: '#f1f5f9', surface: '#ffffff', tooltip: 'light' as const },
);

// The overall rating is on a 1–10 scale, so it's left out of this 5-point chart.
const ratedSections = computed(() => avgBySection.value.filter((s) => s.section_key !== 'overall'));

const sectionBarOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, foreColor: chartTheme.value.text },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '55%' } },
    dataLabels: { enabled: true, formatter: (val: number) => Number(val).toFixed(2), style: { fontSize: '11px' } },
    xaxis: { categories: ratedSections.value.map((s) => s.section_title), max: 5, labels: { style: { fontSize: '10px' } } },
    colors: ['#e11d48'],
    grid: { borderColor: chartTheme.value.grid },
    tooltip: { theme: chartTheme.value.tooltip },
}));
const sectionBarSeries = computed(() => [{ name: 'Avg Rating', data: ratedSections.value.map((s) => Number(s.avg_rating)) }]);

const facilitatorBarOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, foreColor: chartTheme.value.text },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '55%' } },
    dataLabels: { enabled: true, formatter: (val: number) => Number(val).toFixed(2), style: { fontSize: '11px' } },
    xaxis: { categories: avgByFacilitator.value.map((f) => f.name), max: 5, labels: { style: { fontSize: '10px' } } },
    colors: ['#7c3aed'],
    grid: { borderColor: chartTheme.value.grid },
    tooltip: { theme: chartTheme.value.tooltip },
}));
const facilitatorBarSeries = computed(() => [{ name: 'Avg Rating', data: avgByFacilitator.value.map((f) => Number(f.avg_rating)) }]);

/** Same bands as the legend on the evaluation form's overall rating question. */
const OVERALL_RATING_BANDS = [
    { range: '1', label: 'Completely Unacceptable', min: 1, max: 1 },
    { range: '2', label: 'Poor', min: 2, max: 2 },
    { range: '3–4', label: 'Fair', min: 3, max: 4 },
    { range: '5', label: 'Passing', min: 5, max: 5 },
    { range: '6–7', label: 'Satisfactory', min: 6, max: 7 },
    { range: '8–9', label: 'Very Good', min: 8, max: 9 },
    { range: '10', label: 'Very Exceptional', min: 10, max: 10 },
];

const overallRatingBands = computed(() =>
    OVERALL_RATING_BANDS.map((band) => ({
        ...band,
        total: overallDistribution.value
            .filter((d) => Number(d.rating) >= band.min && Number(d.rating) <= band.max)
            .reduce((sum, d) => sum + Number(d.total), 0),
    })).filter((band) => band.total > 0),
);

const distributionBarOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, foreColor: chartTheme.value.text },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 6, borderRadiusApplication: 'end', dataLabels: { position: 'top' } } },
    dataLabels: { enabled: true, offsetY: -18, style: { fontSize: '11px', colors: [chartTheme.value.strongText] } },
    tooltip: { theme: chartTheme.value.tooltip },
    xaxis: {
        categories: overallRatingBands.value.map((b) => [b.range, b.label]),
        title: { text: 'Rating', style: { fontSize: '10px' } },
        labels: { style: { fontSize: '10px' } },
    },
    colors: ['#f59e0b'],
    grid: { borderColor: chartTheme.value.grid },
}));
const distributionBarSeries = computed(() => [{ name: 'Responses', data: overallRatingBands.value.map((b) => b.total) }]);

const batchDonutOptions = computed(() => ({
    chart: {
        type: 'donut',
        foreColor: chartTheme.value.text,
        events: {
            // Clicking a slice opens a live view of who has submitted for that batch.
            dataPointSelection: (_event: unknown, _chartContext: unknown, config: { dataPointIndex: number }) => {
                const entry = responsesPerBatch.value[config.dataPointIndex];
                if (entry) openBatchResponses(entry.batch_id, entry.batch_label);
            },
        },
    },
    labels: responsesPerBatch.value.map((r) => `${r.batch_label} (${r.total})`),
    legend: { position: 'bottom', fontSize: '11px', labels: { colors: chartTheme.value.text } },
    colors: ['#3b82f6', '#8b5cf6', '#06b6d4', '#ef4444', '#10b981', '#f59e0b', '#9ca3af'],
    // Matches the card background so slices stay separated in both themes
    stroke: { width: 3, colors: [chartTheme.value.surface] },
    dataLabels: { enabled: true, formatter: (val: number) => val.toFixed(0) + '%' },
    tooltip: { theme: chartTheme.value.tooltip },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    name: { color: chartTheme.value.text },
                    value: { color: chartTheme.value.strongText },
                    total: { show: true, label: 'Total', fontSize: '12px', fontWeight: 700, color: chartTheme.value.text },
                },
            },
        },
    },
}));
const batchDonutSeries = computed(() => responsesPerBatch.value.map((r) => r.total));

let activeController: AbortController | null = null;

async function fetchDashboard() {
    if (activeController) activeController.abort();
    const controller = new AbortController();
    activeController = controller;
    loading.value = true;
    errorMsg.value = '';

    try {
        const { data } = await axios.get(route('programs.evaluation-dashboard', props.program.id), {
            params: { batch_id: filterBatchId.value !== 'all' ? filterBatchId.value : undefined },
            signal: controller.signal,
        });

        totalResponses.value = data.total_responses;
        avgBySection.value = data.avg_by_section;
        avgByFacilitator.value = data.avg_by_facilitator;
        overallDistribution.value = data.overall_distribution;
        responsesPerBatch.value = data.responses_per_batch;
        findings.value = data.findings ?? null;
    } catch (err: any) {
        if (axios.isCancel(err) || err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return;
        console.error('Evaluation dashboard fetch failed:', err?.response?.data ?? err);
        errorMsg.value = `Failed to load dashboard data${err?.response?.status ? ` (${err.response.status})` : ''}.`;
    } finally {
        if (activeController === controller) {
            loading.value = false;
            activeController = null;
        }
    }
}

watch(filterBatchId, () => {
    fetchDashboard();
    fetchComments(1);
});

/* ── Comments (free-text answers), only when one batch is selected ────────── */
const selectedFormId = computed(() => {
    if (filterBatchId.value === 'all') return null;
    const batch = batchesWithForms.value.find((b) => String(b.id) === filterBatchId.value);
    return batch?.evaluation_form?.id ?? null;
});

const commentsLoading = ref(false);
const commentsPage = ref<any>(null);
let commentsController: AbortController | null = null;

async function fetchComments(page = 1) {
    if (!selectedFormId.value) {
        commentsPage.value = null;
        return;
    }
    if (commentsController) commentsController.abort();
    const controller = new AbortController();
    commentsController = controller;
    commentsLoading.value = true;

    try {
        const { data } = await axios.get(route('evaluation-forms.responses', selectedFormId.value), {
            params: { page, per_page: 100 },
            signal: controller.signal,
        });
        commentsPage.value = data;
    } catch (err: any) {
        if (axios.isCancel(err) || err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return;
        console.error('Failed to load responses:', err?.response?.data ?? err);
    } finally {
        if (commentsController === controller) {
            commentsLoading.value = false;
            commentsController = null;
        }
    }
}

function textAnswers(response: any) {
    return (response.answers ?? []).filter((a: any) => a.value_text && !a.question?.options && a.question?.type === 'text');
}

/**
 * Comments are grouped per question (not per respondent) so individual
 * respondents stay anonymous — each group is labeled with its form section
 * so admins can still tell where in the form the question lives.
 */
const groupedComments = computed(() => {
    const groups = new Map<string, { key: string; label: string; facilitatorName?: string; comments: string[] }>();

    for (const response of commentsPage.value?.data ?? []) {
        for (const a of textAnswers(response)) {
            const key = `${a.evaluation_question_id}-${a.evaluation_facilitator_id ?? 'none'}`;
            if (!groups.has(key)) {
                const sectionTitle = a.question?.section?.title;
                const label = sectionTitle ? `${sectionTitle} - ${a.question?.label ?? 'Question'}` : (a.question?.label ?? 'Question');
                groups.set(key, {
                    key,
                    label,
                    facilitatorName: a.facilitator?.name,
                    comments: [],
                });
            }
            groups.get(key)!.comments.push(a.value_text);
        }
    }

    return Array.from(groups.values());
});

/* ── Live view: who has submitted for a batch (from the donut chart) ──────── */

const showBatchPanel = ref(false);
const batchPanelTitle = ref('');
const batchPanelFormId = ref<number | null>(null);
const batchPanelLoading = ref(false);
const batchPanelData = ref<any>(null);
let batchPanelController: AbortController | null = null;

function openBatchResponses(batchId: number, batchLabel: string) {
    const batch = batchesWithForms.value.find((b) => b.id === batchId);
    const formId = batch?.evaluation_form?.id;
    if (!formId) return;

    batchPanelFormId.value = formId;
    batchPanelTitle.value = batchLabel;
    showBatchPanel.value = true;
    fetchBatchResponses(1);
}

async function fetchBatchResponses(page = 1) {
    if (!batchPanelFormId.value) return;
    if (batchPanelController) batchPanelController.abort();
    const controller = new AbortController();
    batchPanelController = controller;
    batchPanelLoading.value = true;

    try {
        const { data } = await axios.get(route('evaluation-forms.responses', batchPanelFormId.value), {
            params: { page, per_page: 20 },
            signal: controller.signal,
        });
        batchPanelData.value = data;
    } catch (err: any) {
        if (axios.isCancel(err) || err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return;
        console.error('Failed to load batch responses:', err?.response?.data ?? err);
    } finally {
        if (batchPanelController === controller) {
            batchPanelLoading.value = false;
            batchPanelController = null;
        }
    }
}

function closeBatchPanel() {
    showBatchPanel.value = false;
}

const deletingResponseId = ref<number | null>(null);

async function deleteResponse(response: any) {
    if (!batchPanelFormId.value) return;
    if (!(await confirmDialog(`Delete the evaluation response from "${response.respondent_name}"? This cannot be undone.`))) return;

    deletingResponseId.value = response.id;
    router.delete(route('evaluation-forms.responses.destroy', [batchPanelFormId.value, response.id]), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const page = batchPanelData.value?.current_page ?? 1;
            fetchBatchResponses(page);
            fetchDashboard();
            fetchComments(1);
        },
        onFinish: () => {
            deletingResponseId.value = null;
        },
    });
}

function isRecent(createdAt: string) {
    return Date.now() - new Date(createdAt).getTime() < 24 * 60 * 60 * 1000;
}

onMounted(() => {
    fetchDashboard();
    fetchComments(1);
    themeObserver = new MutationObserver(syncIsDark);
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
onBeforeUnmount(() => {
    themeObserver?.disconnect();
    if (activeController) activeController.abort();
    if (commentsController) commentsController.abort();
    if (batchPanelController) batchPanelController.abort();
});
</script>

<template>
    <div class="flex flex-col gap-5">
        <!-- Batches: generate or manage evaluation forms without leaving this modal -->
        <div class="overflow-hidden rounded-2xl border shadow-md">
            <div class="flex items-center gap-1.5 border-b bg-muted/40 px-5 py-3">
                <ClipboardCheck class="h-4 w-4 text-rose-600" />
                <p class="text-sm font-bold">Batches</p>
            </div>
            <div v-if="!program.batches?.length" class="px-5 py-6 text-center text-xs text-muted-foreground">This program has no batches yet.</div>
            <div v-else class="flex flex-col divide-y">
                <div v-for="batch in program.batches" :key="batch.id" class="flex items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold">{{ batch.batch }}</p>
                        <p class="mt-0.5 text-xs" :class="batch.evaluation_form ? 'text-emerald-600' : 'text-amber-600'">
                            {{ batch.evaluation_form ? 'Evaluation set up' : 'Not set up yet' }}
                        </p>
                    </div>
                    <div class="shrink-0">
                        <button
                            v-if="!batch.evaluation_form"
                            type="button"
                            :disabled="generatingBatchId === batch.id"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-rose-700 disabled:opacity-60"
                            @click="generateDefault(batch)"
                        >
                            <Loader2 v-if="generatingBatchId === batch.id" class="h-3.5 w-3.5 animate-spin" />
                            <Sparkles v-else class="h-3.5 w-3.5" />
                            Generate
                        </button>
                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors hover:bg-muted"
                            @click="manageBatch(batch)"
                        >
                            <Settings2 class="h-3.5 w-3.5" /> Manage
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex items-center gap-2">
            <label class="text-xs font-semibold text-muted-foreground">Batch</label>
            <select
                v-model="filterBatchId"
                class="rounded-lg border bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-400"
            >
                <option value="all">All Batches</option>
                <option v-for="b in batchesWithForms" :key="b.id" :value="String(b.id)">{{ b.batch }}</option>
            </select>
        </div>

        <div
            v-if="!batchesWithForms.length"
            class="flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center text-sm text-muted-foreground shadow-md"
        >
            <Inbox class="h-6 w-6 text-muted-foreground" />
            No evaluation forms have been set up for any batch in this program yet. Use "Generate" above to create one.
        </div>

        <template v-else>
            <p v-if="errorMsg" class="text-center text-xs text-red-600">{{ errorMsg }}</p>

            <!-- Stat tiles -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div
                    class="flex items-center gap-3 rounded-2xl border bg-gradient-to-br from-rose-50 to-white p-4 shadow-md dark:from-rose-950/30 dark:to-background"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-600 shadow-sm">
                        <Users2 class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Total Responses</p>
                        <p class="text-2xl font-bold">{{ totalResponses }}</p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-3 rounded-2xl border bg-gradient-to-br from-amber-50 to-white p-4 shadow-md dark:from-amber-950/30 dark:to-background"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 shadow-sm">
                        <Star class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Avg Overall Rating</p>
                        <p class="text-2xl font-bold">{{ avgOverallRating }} <span class="text-sm font-normal text-muted-foreground">/ 10</span></p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-3 rounded-2xl border bg-gradient-to-br from-indigo-50 to-white p-4 shadow-md dark:from-indigo-950/30 dark:to-background"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 shadow-sm">
                        <BarChart3 class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Facilitators Rated</p>
                        <p class="text-2xl font-bold">{{ avgByFacilitator.length }}</p>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-xl border p-4 shadow-md">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Average Rating per Section</p>
                    <VueApexCharts v-if="ratedSections.length" type="bar" height="240" :options="sectionBarOptions" :series="sectionBarSeries" />
                    <p v-else class="py-10 text-center text-xs text-muted-foreground">No rating data yet.</p>
                </div>
                <div class="rounded-xl border p-4 shadow-md">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Average Rating per Facilitator</p>
                    <VueApexCharts
                        v-if="avgByFacilitator.length"
                        type="bar"
                        height="240"
                        :options="facilitatorBarOptions"
                        :series="facilitatorBarSeries"
                    />
                    <p v-else class="py-10 text-center text-xs text-muted-foreground">No facilitator ratings yet.</p>
                </div>
                <div class="rounded-xl border p-4 shadow-md">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Overall Rating Distribution</p>
                    <VueApexCharts
                        v-if="overallRatingBands.length"
                        type="bar"
                        height="240"
                        :options="distributionBarOptions"
                        :series="distributionBarSeries"
                    />
                    <p v-else class="py-10 text-center text-xs text-muted-foreground">No overall ratings yet.</p>
                </div>
                <div class="rounded-xl border p-4 shadow-md">
                    <p class="mb-2 flex items-center justify-between gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <span>Responses per Batch</span>
                        <span v-if="responsesPerBatch.length" class="text-[10px] font-normal normal-case text-muted-foreground/70"
                            >Click a slice to see who submitted</span
                        >
                    </p>
                    <VueApexCharts
                        v-if="responsesPerBatch.length"
                        type="donut"
                        height="240"
                        :options="batchDonutOptions"
                        :series="batchDonutSeries"
                        class="cursor-pointer"
                    />
                    <p v-else class="py-10 text-center text-xs text-muted-foreground">No responses yet.</p>
                </div>
            </div>

            <p v-if="loading" class="text-center text-xs text-muted-foreground">Loading...</p>

            <!-- Program Evaluation Findings section of the After Activity Report, generated from the results above -->
            <div class="overflow-hidden rounded-2xl border shadow-md">
                <div class="flex items-center gap-1.5 border-b bg-muted/40 px-5 py-3">
                    <FileText class="h-4 w-4 text-rose-600" />
                    <p class="text-sm font-bold">Program Evaluation Findings and Observations</p>
                    <button
                        v-if="findings"
                        type="button"
                        class="ml-auto inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold transition-colors hover:bg-muted"
                        @click="copyFindings"
                    >
                        <Check v-if="findingsCopied" class="h-3.5 w-3.5 text-emerald-600" />
                        <Copy v-else class="h-3.5 w-3.5" />
                        {{ findingsCopied ? 'Copied' : 'Copy' }}
                    </button>
                </div>
                <div v-if="findings" class="flex flex-col gap-3 px-5 py-4 text-sm leading-relaxed">
                    <p class="text-justify">{{ findings.intro }}</p>
                    <ul v-if="findings.items.length" class="flex list-disc flex-col gap-2 pl-5">
                        <li v-for="item in findings.items" :key="item.label" class="text-justify">
                            <span class="font-bold">{{ item.label }}:</span> {{ item.text }}
                        </li>
                    </ul>
                </div>
                <p v-else class="px-5 py-8 text-center text-xs text-muted-foreground">No findings available yet.</p>
            </div>

            <!-- Comments (grouped per question — respondents stay anonymous) -->
            <div class="overflow-hidden rounded-2xl border shadow-md">
                <div class="flex items-center gap-1.5 border-b bg-muted/40 px-5 py-3">
                    <MessageSquareText class="h-4 w-4 text-rose-600" />
                    <p class="text-sm font-bold">Written Comments</p>
                </div>

                <div v-if="filterBatchId === 'all'" class="px-5 py-8 text-center text-xs text-muted-foreground">
                    Select a specific batch above to read written comments.
                </div>

                <div v-else class="flex max-h-[420px] flex-col divide-y overflow-y-auto">
                    <div v-if="commentsLoading" class="flex items-center justify-center gap-2 py-8 text-xs text-muted-foreground">
                        <Loader2 class="h-4 w-4 animate-spin" /> Loading...
                    </div>

                    <template v-else-if="groupedComments.length">
                        <div v-for="group in groupedComments" :key="group.key" class="px-5 py-3">
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-300">
                                {{ group.label
                                }}<span v-if="group.facilitatorName" class="font-normal text-muted-foreground"> — {{ group.facilitatorName }}</span>
                            </p>
                            <div class="mt-1.5 flex flex-col gap-1.5">
                                <p v-for="(comment, idx) in group.comments" :key="idx" class="rounded-lg bg-muted/50 px-2.5 py-1.5 text-xs">
                                    {{ comment }}
                                </p>
                            </div>
                        </div>

                        <div v-if="commentsPage.last_page > 1" class="flex items-center justify-between px-5 py-2.5 text-xs">
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded border px-2 py-1 disabled:opacity-40"
                                :disabled="commentsPage.current_page <= 1"
                                @click="fetchComments(commentsPage.current_page - 1)"
                            >
                                <ChevronLeft class="h-3 w-3" /> Previous
                            </button>
                            <span class="text-muted-foreground">Page {{ commentsPage.current_page }} of {{ commentsPage.last_page }}</span>
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded border px-2 py-1 disabled:opacity-40"
                                :disabled="commentsPage.current_page >= commentsPage.last_page"
                                @click="fetchComments(commentsPage.current_page + 1)"
                            >
                                Next <ChevronRight class="h-3 w-3" />
                            </button>
                        </div>
                    </template>

                    <p v-else class="px-5 py-8 text-center text-xs text-muted-foreground">No written comments yet for this batch.</p>
                </div>
            </div>
        </template>
    </div>

    <!-- Live view: who has submitted for a batch (opened from the donut chart) -->
    <Transition name="backdrop" appear>
        <div v-if="showBatchPanel" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4" @click.self="closeBatchPanel">
            <Transition name="pop" appear>
                <div class="flex max-h-[80vh] w-full max-w-md flex-col overflow-y-auto rounded-2xl bg-background shadow-2xl">
                    <div
                        class="sticky top-0 z-10 flex items-center gap-3 rounded-t-2xl bg-gradient-to-r from-rose-700 via-red-700 to-orange-600 px-5 py-4 text-white"
                    >
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/20 backdrop-blur">
                            <UserRound class="h-4 w-4 text-white" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-sm font-bold">{{ batchPanelTitle }}</h2>
                            <p class="text-xs text-white/75">{{ batchPanelData?.total ?? 0 }} response(s)</p>
                        </div>
                        <button type="button" class="text-white/80 transition-colors hover:text-white" @click="closeBatchPanel">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-4">
                        <div v-if="batchPanelLoading" class="flex items-center justify-center gap-2 py-8 text-xs text-muted-foreground">
                            <Loader2 class="h-4 w-4 animate-spin" /> Loading...
                        </div>

                        <div v-else-if="batchPanelData && batchPanelData.data.length" class="flex flex-col gap-2">
                            <div v-for="response in batchPanelData.data" :key="response.id" class="rounded-xl border px-3 py-2.5 shadow-md">
                                <div class="flex items-start gap-2.5">
                                    <div
                                        class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-950/50"
                                    >
                                        <UserRound class="h-4 w-4 text-rose-600" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold leading-tight">{{ response.respondent_name }}</p>
                                        <p class="flex items-center gap-1 text-xs text-muted-foreground">
                                            <Clock class="h-3 w-3 shrink-0" /> {{ new Date(response.created_at).toLocaleString() }}
                                        </p>
                                    </div>
                                    <span
                                        v-if="isRecent(response.created_at)"
                                        class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        New
                                    </span>
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-red-50 hover:text-red-600 disabled:opacity-40 dark:hover:bg-red-950/30"
                                        title="Delete this response"
                                        :disabled="deletingResponseId === response.id"
                                        @click="deleteResponse(response)"
                                    >
                                        <Loader2 v-if="deletingResponseId === response.id" class="h-3.5 w-3.5 animate-spin" />
                                        <Trash2 v-else class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <div v-if="batchPanelData.last_page > 1" class="flex items-center justify-between pt-2 text-xs">
                                <button
                                    type="button"
                                    class="flex items-center gap-1 rounded border px-2 py-1 disabled:opacity-40"
                                    :disabled="batchPanelData.current_page <= 1"
                                    @click="fetchBatchResponses(batchPanelData.current_page - 1)"
                                >
                                    <ChevronLeft class="h-3 w-3" /> Previous
                                </button>
                                <span class="text-muted-foreground">Page {{ batchPanelData.current_page }} of {{ batchPanelData.last_page }}</span>
                                <button
                                    type="button"
                                    class="flex items-center gap-1 rounded border px-2 py-1 disabled:opacity-40"
                                    :disabled="batchPanelData.current_page >= batchPanelData.last_page"
                                    @click="fetchBatchResponses(batchPanelData.current_page + 1)"
                                >
                                    Next <ChevronRight class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <p v-else class="py-8 text-center text-xs text-muted-foreground">No responses yet for this batch.</p>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.2s ease;
}
.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}
.pop-enter-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.pop-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}
.pop-enter-from {
    opacity: 0;
    transform: scale(0.94) translateY(8px);
}
.pop-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
</style>
