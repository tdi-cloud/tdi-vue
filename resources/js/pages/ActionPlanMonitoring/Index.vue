<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, ChevronDown, Clock, FileText, Search, Target, Wallet, X, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Report {
    id: number;
    accomplishment: string;
    actual_output: string;
    date_completed: string | null;
    actual_cost: string | null;
    file_url: string;
    status: 'Pending' | 'Approved' | 'Rejected';
    remarks: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    submitted_at: string | null;
}

interface ActivityRow {
    id: number;
    employee_name: string;
    empcode: string;
    office: string | null;
    region: string | null;
    program_title: string | null;
    batch_label: string | null;
    activity: string;
    expected_output: string;
    schedule: string;
    target_date: string | null;
    budget: string | null;
    remarks: string | null;
    status: string;
    report: Report | null;
}

const props = defineProps<{
    activities: {
        data: ActivityRow[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    stats: {
        total: number;
        by_status: Record<string, number>;
        completion_rate: number;
        planned_budget: number;
        actual_cost: number;
    };
    statuses: string[];
    regions: string[];
    filters: { search?: string; status?: string; region?: string };
}>();

const STATUS_STYLES: Record<string, string> = {
    Upcoming: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    Ongoing: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    Overdue: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    'For Review': 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    'Needs Revision': 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    Completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
};

/* ---- Filters ---- */
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const region = ref(props.filters.region ?? '');

let debounce: ReturnType<typeof setTimeout>;
watch([search, status, region], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('action-plan-monitoring.index'),
            { search: search.value || undefined, status: status.value || undefined, region: region.value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const hasFilters = computed(() => !!(search.value || status.value || region.value));

function clearFilters() {
    search.value = '';
    status.value = '';
    region.value = '';
}

function setStatus(value: string) {
    status.value = status.value === value ? '' : value;
}

/* ---- Review ---- */
const expandedId = ref<number | null>(null);
const reviewForm = useForm({ status: '', remarks: '' });

function toggle(row: ActivityRow) {
    expandedId.value = expandedId.value === row.id ? null : row.id;
    reviewForm.reset();
    reviewForm.clearErrors();
}

function review(row: ActivityRow, decision: 'Approved' | 'Rejected') {
    if (!row.report) {
        return;
    }
    reviewForm.status = decision;
    reviewForm.patch(route('action-plan-monitoring.review', row.report.id), {
        preserveScroll: true,
        onSuccess: () => {
            reviewForm.reset();
        },
    });
}

function formatMoney(value: string | number | null): string {
    if (value === null || value === '') {
        return '—';
    }
    return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Laravel's pagination labels contain HTML entities for the arrows; show plain arrows instead. */
function pageLabel(label: string): string {
    if (label.includes('Previous')) {
        return '‹';
    }
    if (label.includes('Next')) {
        return '›';
    }
    return label;
}

function formatDate(value: string | null): string {
    return value ? new Date(value + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}
</script>

<template>
    <Head title="Action Plan Monitoring" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-600 shadow-sm"
                >
                    <Target class="h-5 w-5 text-white" />
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold">Action Plan Monitoring</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Track every participant's Re-Entry Action Plan activities against their schedule, and review implementation reports.
                    </p>
                </div>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl border bg-card p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Activities</p>
                    <p class="mt-1 text-2xl font-extrabold">{{ stats.total }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ stats.completion_rate }}% completed</p>
                </div>
                <button
                    type="button"
                    class="rounded-2xl border border-red-200 bg-card p-4 text-left transition hover:shadow-md dark:border-red-900"
                    :class="{ 'ring-2 ring-red-400': status === 'Overdue' }"
                    @click="setStatus('Overdue')"
                >
                    <p class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-red-600">
                        <AlertTriangle class="h-3.5 w-3.5" /> Overdue
                    </p>
                    <p class="mt-1 text-2xl font-extrabold text-red-600">{{ stats.by_status['Overdue'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Past target date, no report</p>
                </button>
                <button
                    type="button"
                    class="rounded-2xl border border-amber-200 bg-card p-4 text-left transition hover:shadow-md dark:border-amber-900"
                    :class="{ 'ring-2 ring-amber-400': status === 'For Review' }"
                    @click="setStatus('For Review')"
                >
                    <p class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-amber-600">
                        <Clock class="h-3.5 w-3.5" /> For Review
                    </p>
                    <p class="mt-1 text-2xl font-extrabold text-amber-600">{{ stats.by_status['For Review'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Implementation reports waiting</p>
                </button>
                <div class="rounded-2xl border bg-card p-4">
                    <p class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <Wallet class="h-3.5 w-3.5" /> Budget
                    </p>
                    <p class="mt-1 text-lg font-extrabold">{{ formatMoney(stats.planned_budget) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Planned · {{ formatMoney(stats.actual_cost) }} spent (approved)</p>
                </div>
            </div>

            <!-- Status chips -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="s in statuses"
                    :key="s"
                    type="button"
                    class="rounded-full px-3 py-1 text-xs font-bold transition"
                    :class="[
                        STATUS_STYLES[s],
                        status === s ? 'ring-2 ring-current ring-offset-1 ring-offset-background' : 'opacity-80 hover:opacity-100',
                    ]"
                    @click="setStatus(s)"
                >
                    {{ s }} · {{ stats.by_status[s] ?? 0 }}
                </button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[240px] max-w-sm flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search employee, empcode, program or activity..."
                        class="w-full rounded-lg border bg-background py-2 pl-9 pr-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                    />
                </div>
                <select v-model="region" class="rounded-lg border bg-background px-3 py-2 text-sm shadow-sm">
                    <option value="">All regions</option>
                    <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
                </select>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="flex items-center gap-1 px-2 py-1.5 text-xs text-muted-foreground hover:text-foreground"
                    @click="clearFilters"
                >
                    <X class="h-3.5 w-3.5" /> Clear
                </button>
            </div>

            <!-- List -->
            <div class="overflow-hidden rounded-2xl border bg-card">
                <div v-if="!activities.data.length" class="p-10 text-center text-sm text-muted-foreground">No activities match these filters.</div>

                <div v-for="row in activities.data" :key="row.id" class="border-b last:border-b-0">
                    <button type="button" class="flex w-full items-start gap-4 p-4 text-left transition hover:bg-muted/40" @click="toggle(row)">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="STATUS_STYLES[row.status]">{{
                                    row.status
                                }}</span>
                                <span class="text-sm font-bold">{{ row.employee_name }}</span>
                                <span class="text-xs text-muted-foreground">{{ row.empcode }} · {{ row.office }} · {{ row.region }}</span>
                            </div>
                            <p class="mt-1.5 text-sm font-semibold">{{ row.activity }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ row.program_title }} · {{ row.batch_label }}</p>
                        </div>
                        <div class="hidden shrink-0 text-right text-xs sm:block">
                            <p class="font-semibold">{{ row.schedule }}</p>
                            <p class="text-muted-foreground">Budget {{ formatMoney(row.budget) }}</p>
                        </div>
                        <ChevronDown
                            class="mt-1 h-4 w-4 shrink-0 text-muted-foreground transition"
                            :class="{ 'rotate-180': expandedId === row.id }"
                        />
                    </button>

                    <div v-if="expandedId === row.id" class="grid gap-4 border-t bg-muted/20 p-4 text-sm lg:grid-cols-2">
                        <div class="space-y-1.5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Planned</p>
                            <p><span class="font-semibold">Expected output:</span> {{ row.expected_output }}</p>
                            <p><span class="font-semibold">Schedule:</span> {{ row.schedule }}</p>
                            <p><span class="font-semibold">Budget:</span> {{ formatMoney(row.budget) }}</p>
                            <p v-if="row.remarks"><span class="font-semibold">Remarks:</span> {{ row.remarks }}</p>
                        </div>

                        <div v-if="row.report" class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Implementation report · {{ formatDate(row.report.submitted_at) }}
                                </p>
                                <a
                                    :href="row.report.file_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-violet-600 hover:underline"
                                >
                                    <FileText class="h-3.5 w-3.5" /> View proof
                                </a>
                            </div>
                            <p>{{ row.report.accomplishment }}</p>
                            <p><span class="font-semibold">Actual output:</span> {{ row.report.actual_output }}</p>
                            <p><span class="font-semibold">Completed:</span> {{ formatDate(row.report.date_completed) }}</p>
                            <p><span class="font-semibold">Actual cost:</span> {{ formatMoney(row.report.actual_cost) }}</p>
                            <p v-if="row.report.reviewed_by" class="text-xs text-muted-foreground">
                                {{ row.report.status }} by {{ row.report.reviewed_by }} on {{ formatDate(row.report.reviewed_at) }}
                                <span v-if="row.report.remarks"> — "{{ row.report.remarks }}"</span>
                            </p>

                            <div v-if="row.report.status === 'Pending'" class="mt-3 space-y-2 rounded-xl border bg-background p-3">
                                <textarea
                                    v-model="reviewForm.remarks"
                                    rows="2"
                                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                    placeholder="Note to the participant (required when returning for revision)"
                                ></textarea>
                                <p v-if="reviewForm.errors.remarks" class="text-xs text-red-600">{{ reviewForm.errors.remarks }}</p>
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        :disabled="reviewForm.processing"
                                        class="inline-flex items-center gap-1 rounded-lg border border-orange-300 px-3 py-1.5 text-xs font-bold text-orange-700 hover:bg-orange-50 disabled:opacity-60 dark:hover:bg-orange-950"
                                        @click="review(row, 'Rejected')"
                                    >
                                        <XCircle class="h-3.5 w-3.5" /> Return for revision
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="reviewForm.processing"
                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                        @click="review(row, 'Approved')"
                                    >
                                        <CheckCircle2 class="h-3.5 w-3.5" /> Approve
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex items-center text-sm text-muted-foreground">No implementation report submitted yet.</div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="activities.total > 0" class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <span class="text-muted-foreground">Showing {{ activities.from }}–{{ activities.to }} of {{ activities.total }}</span>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in activities.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs transition-colors"
                            :class="link.active ? 'border-violet-600 bg-violet-600 text-white' : 'text-muted-foreground hover:bg-muted'"
                        >
                            {{ pageLabel(link.label) }}
                        </Link>
                        <span
                            v-else
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs text-muted-foreground opacity-40"
                        >
                            {{ pageLabel(link.label) }}
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
