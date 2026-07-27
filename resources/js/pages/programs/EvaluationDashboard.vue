<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import { BarChart3, Users2, Star, MessageSquareText, Loader2, ChevronLeft, ChevronRight, Inbox, Sparkles, Settings2, ClipboardCheck, X, UserRound, Clock } from 'lucide-vue-next';

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
    router.post(route('batches.evaluation-form.store', batch.id), { mode: 'default' }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { generatingBatchId.value = null; },
    });
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

const avgOverallRating = computed(() => {
    const overall = avgBySection.value.find((s) => s.section_key === 'overall');
    return overall ? Number(overall.avg_rating).toFixed(1) : '—';
});

const sectionBarOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false } },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '55%' } },
    dataLabels: { enabled: true, formatter: (val: number) => Number(val).toFixed(1), style: { fontSize: '11px' } },
    xaxis: { categories: avgBySection.value.map((s) => s.section_title), max: 5, labels: { style: { fontSize: '10px' } } },
    colors: ['#e11d48'],
    grid: { borderColor: '#f1f5f9' },
}));
const sectionBarSeries = computed(() => [{ name: 'Avg Rating', data: avgBySection.value.map((s) => Number(s.avg_rating)) }]);

const facilitatorBarOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false } },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '55%' } },
    dataLabels: { enabled: true, formatter: (val: number) => Number(val).toFixed(1), style: { fontSize: '11px' } },
    xaxis: { categories: avgByFacilitator.value.map((f) => f.name), max: 5, labels: { style: { fontSize: '10px' } } },
    colors: ['#7c3aed'],
    grid: { borderColor: '#f1f5f9' },
}));
const facilitatorBarSeries = computed(() => [{ name: 'Avg Rating', data: avgByFacilitator.value.map((f) => Number(f.avg_rating)) }]);

const distributionBarOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false } },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 6, borderRadiusApplication: 'end' } },
    dataLabels: { enabled: false },
    xaxis: { categories: overallDistribution.value.map((d) => `${d.rating}`), title: { text: 'Rating (1–10)', style: { fontSize: '10px' } } },
    colors: ['#f59e0b'],
    grid: { borderColor: '#f1f5f9' },
}));
const distributionBarSeries = computed(() => [{ name: 'Responses', data: overallDistribution.value.map((d) => d.total) }]);

const batchDonutOptions = computed(() => ({
    chart: {
        type: 'donut',
        events: {
            // Clicking a slice opens a live view of who has submitted for that batch.
            dataPointSelection: (_event: unknown, _chartContext: unknown, config: { dataPointIndex: number }) => {
                const entry = responsesPerBatch.value[config.dataPointIndex];
                if (entry) openBatchResponses(entry.batch_id, entry.batch_label);
            },
        },
    },
    labels: responsesPerBatch.value.map((r) => r.batch_label),
    legend: { position: 'bottom', fontSize: '11px' },
    colors: ['#3b82f6', '#8b5cf6', '#06b6d4', '#ef4444', '#10b981', '#f59e0b', '#9ca3af'],
    stroke: { width: 3, colors: ['#ffffff'] },
    dataLabels: { enabled: true, formatter: (val: number) => val.toFixed(0) + '%' },
    plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px', fontWeight: 700 } } } } },
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
    if (!selectedFormId.value) { commentsPage.value = null; return; }
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
 * respondents stay anonymous — only the question label and facilitator (for
 * facilitator-section questions) are shown alongside each comment.
 */
const groupedComments = computed(() => {
    const groups = new Map<string, { key: string; label: string; facilitatorName?: string; comments: string[] }>();

    for (const response of commentsPage.value?.data ?? []) {
        for (const a of textAnswers(response)) {
            const key = `${a.evaluation_question_id}-${a.evaluation_facilitator_id ?? 'none'}`;
            if (!groups.has(key)) {
                groups.set(key, {
                    key,
                    label: a.question?.label ?? 'Question',
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

function isRecent(createdAt: string) {
    return Date.now() - new Date(createdAt).getTime() < 24 * 60 * 60 * 1000;
}

onMounted(() => {
    fetchDashboard();
    fetchComments(1);
});
onBeforeUnmount(() => {
    if (activeController) activeController.abort();
    if (commentsController) commentsController.abort();
    if (batchPanelController) batchPanelController.abort();
});
</script>

<template>
    <div class="flex flex-col gap-5">

        <!-- Batches: generate or manage evaluation forms without leaving this modal -->
        <div class="rounded-2xl border overflow-hidden">
            <div class="px-5 py-3 border-b bg-muted/40 flex items-center gap-1.5">
                <ClipboardCheck class="h-4 w-4 text-rose-600" />
                <p class="text-sm font-bold">Batches</p>
            </div>
            <div v-if="!program.batches?.length" class="px-5 py-6 text-xs text-muted-foreground text-center">
                This program has no batches yet.
            </div>
            <div v-else class="flex flex-col divide-y">
                <div v-for="batch in program.batches" :key="batch.id" class="flex items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold truncate">{{ batch.batch }}</p>
                        <p class="text-xs mt-0.5" :class="batch.evaluation_form ? 'text-emerald-600' : 'text-amber-600'">
                            {{ batch.evaluation_form ? 'Evaluation set up' : 'Not set up yet' }}
                        </p>
                    </div>
                    <div class="shrink-0">
                        <button
                            v-if="!batch.evaluation_form"
                            type="button"
                            :disabled="generatingBatchId === batch.id"
                            class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white disabled:opacity-60 transition-colors"
                            @click="generateDefault(batch)"
                        >
                            <Loader2 v-if="generatingBatchId === batch.id" class="h-3.5 w-3.5 animate-spin" />
                            <Sparkles v-else class="h-3.5 w-3.5" />
                            Generate
                        </button>
                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg border hover:bg-muted transition-colors"
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
            <select v-model="filterBatchId" class="border rounded-lg px-2 py-1.5 text-xs bg-background focus:outline-none focus:ring-2 focus:ring-rose-400">
                <option value="all">All Batches</option>
                <option v-for="b in batchesWithForms" :key="b.id" :value="String(b.id)">{{ b.batch }}</option>
            </select>
        </div>

        <div v-if="!batchesWithForms.length" class="rounded-2xl border border-dashed py-12 text-center text-sm text-muted-foreground flex flex-col items-center gap-2">
            <Inbox class="h-6 w-6 text-muted-foreground" />
            No evaluation forms have been set up for any batch in this program yet. Use "Generate" above to create one.
        </div>

        <template v-else>
            <p v-if="errorMsg" class="text-xs text-red-600 text-center">{{ errorMsg }}</p>

            <!-- Stat tiles -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-2xl border bg-gradient-to-br from-rose-50 to-white dark:from-rose-950/30 dark:to-background p-4 flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-rose-600 flex items-center justify-center shadow-sm shrink-0">
                        <Users2 class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Total Responses</p>
                        <p class="text-2xl font-bold">{{ totalResponses }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border bg-gradient-to-br from-amber-50 to-white dark:from-amber-950/30 dark:to-background p-4 flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-amber-500 flex items-center justify-center shadow-sm shrink-0">
                        <Star class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Avg Overall Rating</p>
                        <p class="text-2xl font-bold">{{ avgOverallRating }} <span class="text-sm font-normal text-muted-foreground">/ 10</span></p>
                    </div>
                </div>
                <div class="rounded-2xl border bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-950/30 dark:to-background p-4 flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-indigo-600 flex items-center justify-center shadow-sm shrink-0">
                        <BarChart3 class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Facilitators Rated</p>
                        <p class="text-2xl font-bold">{{ avgByFacilitator.length }}</p>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-xl border p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2">Average Rating per Section</p>
                    <VueApexCharts v-if="avgBySection.length" type="bar" height="240" :options="sectionBarOptions" :series="sectionBarSeries" />
                    <p v-else class="text-xs text-muted-foreground text-center py-10">No rating data yet.</p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2">Average Rating per Facilitator</p>
                    <VueApexCharts v-if="avgByFacilitator.length" type="bar" height="240" :options="facilitatorBarOptions" :series="facilitatorBarSeries" />
                    <p v-else class="text-xs text-muted-foreground text-center py-10">No facilitator ratings yet.</p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2">Overall Rating Distribution (1–10)</p>
                    <VueApexCharts v-if="overallDistribution.length" type="bar" height="240" :options="distributionBarOptions" :series="distributionBarSeries" />
                    <p v-else class="text-xs text-muted-foreground text-center py-10">No overall ratings yet.</p>
                </div>
                <div v-if="filterBatchId === 'all'" class="rounded-xl border p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2 flex items-center justify-between gap-1.5">
                        <span>Responses per Batch</span>
                        <span v-if="responsesPerBatch.length" class="normal-case font-normal text-[10px] text-muted-foreground/70">Click a slice to see who submitted</span>
                    </p>
                    <VueApexCharts v-if="responsesPerBatch.length" type="donut" height="240" :options="batchDonutOptions" :series="batchDonutSeries" class="cursor-pointer" />
                    <p v-else class="text-xs text-muted-foreground text-center py-10">No responses yet.</p>
                </div>
            </div>

            <p v-if="loading" class="text-xs text-muted-foreground text-center">Loading...</p>

            <!-- Comments (grouped per question — respondents stay anonymous) -->
            <div class="rounded-2xl border overflow-hidden">
                <div class="px-5 py-3 border-b bg-muted/40 flex items-center gap-1.5">
                    <MessageSquareText class="h-4 w-4 text-rose-600" />
                    <p class="text-sm font-bold">Written Comments</p>
                    <span class="ml-auto text-[10px] text-muted-foreground">Anonymous — no names shown</span>
                </div>

                <div v-if="filterBatchId === 'all'" class="px-5 py-8 text-center text-xs text-muted-foreground">
                    Select a specific batch above to read written comments.
                </div>

                <div v-else class="flex flex-col divide-y max-h-[420px] overflow-y-auto">
                    <div v-if="commentsLoading" class="flex items-center justify-center gap-2 text-xs text-muted-foreground py-8">
                        <Loader2 class="h-4 w-4 animate-spin" /> Loading...
                    </div>

                    <template v-else-if="groupedComments.length">
                        <div v-for="group in groupedComments" :key="group.key" class="px-5 py-3">
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-300">
                                {{ group.label }}<span v-if="group.facilitatorName" class="font-normal text-muted-foreground"> — {{ group.facilitatorName }}</span>
                            </p>
                            <div class="mt-1.5 flex flex-col gap-1.5">
                                <p v-for="(comment, idx) in group.comments" :key="idx" class="text-xs bg-muted/50 rounded-lg px-2.5 py-1.5">
                                    {{ comment }}
                                </p>
                            </div>
                        </div>

                        <div v-if="commentsPage.last_page > 1" class="flex items-center justify-between px-5 py-2.5 text-xs">
                            <button type="button" class="flex items-center gap-1 px-2 py-1 rounded border disabled:opacity-40" :disabled="commentsPage.current_page <= 1" @click="fetchComments(commentsPage.current_page - 1)">
                                <ChevronLeft class="h-3 w-3" /> Previous
                            </button>
                            <span class="text-muted-foreground">Page {{ commentsPage.current_page }} of {{ commentsPage.last_page }}</span>
                            <button type="button" class="flex items-center gap-1 px-2 py-1 rounded border disabled:opacity-40" :disabled="commentsPage.current_page >= commentsPage.last_page" @click="fetchComments(commentsPage.current_page + 1)">
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
        <div
            v-if="showBatchPanel"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4"
            @click.self="closeBatchPanel"
        >
            <Transition name="pop" appear>
                <div class="bg-background rounded-2xl shadow-2xl w-full max-w-md max-h-[80vh] overflow-y-auto flex flex-col">
                    <div class="sticky top-0 z-10 bg-gradient-to-r from-rose-700 via-red-700 to-orange-600 text-white px-5 py-4 rounded-t-2xl flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-white/20 backdrop-blur flex items-center justify-center shrink-0">
                            <UserRound class="h-4 w-4 text-white" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="font-bold text-sm truncate">{{ batchPanelTitle }}</h2>
                            <p class="text-xs text-white/75">{{ batchPanelData?.total ?? 0 }} response(s)</p>
                        </div>
                        <button type="button" class="text-white/80 hover:text-white transition-colors" @click="closeBatchPanel">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-4">
                        <div v-if="batchPanelLoading" class="flex items-center justify-center gap-2 text-xs text-muted-foreground py-8">
                            <Loader2 class="h-4 w-4 animate-spin" /> Loading...
                        </div>

                        <div v-else-if="batchPanelData && batchPanelData.data.length" class="flex flex-col gap-2">
                            <div v-for="response in batchPanelData.data" :key="response.id" class="rounded-xl border px-3 py-2.5">
                                <div class="flex items-start gap-2.5">
                                    <div class="h-7 w-7 rounded-full bg-rose-100 dark:bg-rose-950/50 flex items-center justify-center shrink-0 mt-0.5">
                                        <UserRound class="h-4 w-4 text-rose-600" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-sm leading-tight truncate">{{ response.respondent_name }}</p>
                                        <p class="text-xs text-muted-foreground flex items-center gap-1">
                                            <Clock class="h-3 w-3 shrink-0" /> {{ new Date(response.created_at).toLocaleString() }}
                                        </p>
                                    </div>
                                    <span v-if="isRecent(response.created_at)" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 shrink-0">
                                        New
                                    </span>
                                </div>
                            </div>

                            <div v-if="batchPanelData.last_page > 1" class="flex items-center justify-between pt-2 text-xs">
                                <button type="button" class="flex items-center gap-1 px-2 py-1 rounded border disabled:opacity-40" :disabled="batchPanelData.current_page <= 1" @click="fetchBatchResponses(batchPanelData.current_page - 1)">
                                    <ChevronLeft class="h-3 w-3" /> Previous
                                </button>
                                <span class="text-muted-foreground">Page {{ batchPanelData.current_page }} of {{ batchPanelData.last_page }}</span>
                                <button type="button" class="flex items-center gap-1 px-2 py-1 rounded border disabled:opacity-40" :disabled="batchPanelData.current_page >= batchPanelData.last_page" @click="fetchBatchResponses(batchPanelData.current_page + 1)">
                                    Next <ChevronRight class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <p v-else class="text-xs text-muted-foreground text-center py-8">No responses yet for this batch.</p>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.backdrop-enter-active,
.backdrop-leave-active { transition: opacity 0.2s ease; }
.backdrop-enter-from,
.backdrop-leave-to     { opacity: 0; }
.pop-enter-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
.pop-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.pop-enter-from   { opacity: 0; transform: scale(0.94) translateY(8px); }
.pop-leave-to     { opacity: 0; transform: scale(0.97); }
</style>
