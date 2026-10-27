<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import { router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Clock,
    FileText,
    Loader2,
    Plus,
    RotateCcw,
    Send,
    Target,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ImplementationReport {
    accomplishment: string;
    actual_output: string;
    date_completed: string | null;
    actual_cost: string | null;
    file_url: string;
    status: 'Pending' | 'Approved' | 'Rejected';
    remarks: string | null;
    submitted_at: string | null;
}

interface Activity {
    id: number;
    activity: string;
    expected_output: string;
    start_date: string | null;
    target_date: string | null;
    schedule: string;
    budget: string | null;
    remarks: string | null;
    status: 'Upcoming' | 'Ongoing' | 'Overdue' | 'For Review' | 'Needs Revision' | 'Completed';
    added_after_submission: boolean;
    locked: boolean;
    report: ImplementationReport | null;
}

const props = defineProps<{
    batchId: number;
    activities: Activity[];
    canAdd: boolean;
}>();

const { confirmDialog } = useConfirm();

const STATUS_STYLES: Record<Activity['status'], string> = {
    Upcoming: 'bg-slate-100 text-slate-700',
    Ongoing: 'bg-blue-100 text-blue-700',
    Overdue: 'bg-red-100 text-red-700',
    'For Review': 'bg-amber-100 text-amber-800',
    'Needs Revision': 'bg-orange-100 text-orange-800',
    Completed: 'bg-emerald-100 text-emerald-700',
};

const completedCount = computed(() => props.activities.filter((a) => a.status === 'Completed').length);
const overdueCount = computed(() => props.activities.filter((a) => a.status === 'Overdue').length);
const progressPercent = computed(() => (props.activities.length ? Math.round((completedCount.value / props.activities.length) * 100) : 0));

function daysUntil(date: string | null): number | null {
    if (!date) {
        return null;
    }
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round((new Date(date + 'T00:00:00').getTime() - today.getTime()) / 86_400_000);
}

function dueLabel(activity: Activity): string {
    if (activity.report) {
        return '';
    }
    const days = daysUntil(activity.target_date);
    if (days === null) {
        return '';
    }
    if (days < 0) {
        return `Report overdue by ${-days} day${days === -1 ? '' : 's'}`;
    }
    if (days === 0) {
        return 'Report due today';
    }
    return `Report due in ${days} day${days === 1 ? '' : 's'}`;
}

function formatMoney(value: string | number | null): string {
    if (value === null || value === '') {
        return 'None';
    }
    return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(value: string | null): string {
    return value ? new Date(value + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
}

const canSubmitReport = (activity: Activity) => activity.report?.status !== 'Approved';

/* ---- Implementation report form (one open at a time) ---- */
const reportingId = ref<number | null>(null);
const reportForm = useForm({
    accomplishment: '',
    actual_output: '',
    date_completed: '',
    actual_cost: '' as string | number,
    file: null as File | null,
});

function openReport(activity: Activity) {
    reportForm.clearErrors();
    reportForm.accomplishment = activity.report?.accomplishment ?? '';
    reportForm.actual_output = activity.report?.actual_output ?? '';
    reportForm.date_completed = activity.report?.date_completed ?? '';
    reportForm.actual_cost = activity.report?.actual_cost ?? '';
    reportForm.file = null;
    reportingId.value = activity.id;
    addingActivity.value = false;
}

function onReportFile(event: Event) {
    reportForm.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submitReport(activity: Activity) {
    reportForm.post(route('programs.action-plan.report.submit', { batch: props.batchId, activity: activity.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            reportingId.value = null;
            reportForm.reset();
        },
    });
}

/* ---- Add activity ---- */
const addingActivity = ref(false);
const activityForm = useForm({
    activity: '',
    expected_output: '',
    start_date: '',
    target_date: '',
    budget: '' as string | number,
    remarks: '',
});

function openAddActivity() {
    activityForm.reset();
    activityForm.clearErrors();
    addingActivity.value = true;
    reportingId.value = null;
}

function submitActivity() {
    activityForm
        .transform((data) => ({ ...data, budget: data.budget === '' ? null : data.budget }))
        .post(route('programs.action-plan.activities.store', { batch: props.batchId }), {
            preserveScroll: true,
            onSuccess: () => {
                addingActivity.value = false;
                activityForm.reset();
            },
        });
}

async function removeActivity(activity: Activity) {
    if (!(await confirmDialog(`Remove "${activity.activity}" from your action plan?`))) {
        return;
    }
    router.delete(route('programs.action-plan.activities.destroy', { batch: props.batchId, activity: activity.id }), { preserveScroll: true });
}
</script>

<template>
    <div class="action-plan-tracker space-y-5">
        <!-- Summary -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700"
                        ><Target :size="20"
                    /></span>
                    <div>
                        <h2 class="text-lg font-extrabold text-[#1a2744]">My Action Plan</h2>
                        <p class="text-sm text-gray-500">
                            Submit an <strong>implementation report</strong> for each activity on or before its target date.
                        </p>
                    </div>
                </div>
                <button
                    v-if="canAdd"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white hover:bg-violet-700"
                    @click="openAddActivity"
                >
                    <Plus :size="14" /> Add activity
                </button>
            </div>

            <div v-if="activities.length" class="mt-4">
                <div class="mb-1.5 flex items-center justify-between text-xs font-semibold text-gray-600">
                    <span>{{ completedCount }} of {{ activities.length }} activities completed</span>
                    <span>{{ progressPercent }}%</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: progressPercent + '%' }"></div>
                </div>
                <p v-if="overdueCount" class="mt-3 flex items-center gap-1.5 text-sm font-semibold text-red-700">
                    <AlertTriangle :size="15" /> {{ overdueCount }} {{ overdueCount === 1 ? 'activity is' : 'activities are' }} past the target date
                    without an implementation report.
                </p>
            </div>
        </div>

        <!-- Add activity form -->
        <form v-if="addingActivity" class="rounded-2xl border-2 border-violet-200 bg-violet-50/40 p-5" @submit.prevent="submitActivity">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="font-bold text-[#1a2744]">New activity</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" @click="addingActivity = false"><X :size="18" /></button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="tracker-label">Activity <span class="text-red-500">*</span></span>
                    <textarea v-model="activityForm.activity" rows="2" class="tracker-input"></textarea>
                    <span v-if="activityForm.errors.activity" class="tracker-error">{{ activityForm.errors.activity }}</span>
                </label>
                <label class="block">
                    <span class="tracker-label">Expected Output <span class="text-red-500">*</span></span>
                    <textarea v-model="activityForm.expected_output" rows="2" class="tracker-input"></textarea>
                    <span v-if="activityForm.errors.expected_output" class="tracker-error">{{ activityForm.errors.expected_output }}</span>
                </label>
                <label class="block">
                    <span class="tracker-label">Start date <span class="text-red-500">*</span></span>
                    <input v-model="activityForm.start_date" type="date" class="tracker-input" />
                    <span v-if="activityForm.errors.start_date" class="tracker-error">{{ activityForm.errors.start_date }}</span>
                </label>
                <label class="block">
                    <span class="tracker-label">Target date (report due) <span class="text-red-500">*</span></span>
                    <input v-model="activityForm.target_date" type="date" :min="activityForm.start_date || undefined" class="tracker-input" />
                    <span v-if="activityForm.errors.target_date" class="tracker-error">{{ activityForm.errors.target_date }}</span>
                </label>
                <label class="block">
                    <span class="tracker-label">Budget (₱)</span>
                    <input v-model="activityForm.budget" type="number" min="0" step="0.01" placeholder="Leave blank if none" class="tracker-input" />
                    <span v-if="activityForm.errors.budget" class="tracker-error">{{ activityForm.errors.budget }}</span>
                </label>
                <label class="block">
                    <span class="tracker-label">Remarks</span>
                    <input v-model="activityForm.remarks" type="text" class="tracker-input" />
                </label>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100"
                    @click="addingActivity = false"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="activityForm.processing"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-60"
                >
                    <Loader2 v-if="activityForm.processing" :size="14" class="animate-spin" /> Add activity
                </button>
            </div>
        </form>

        <!-- Activities -->
        <div v-if="!activities.length" class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
            No activities yet. Activities from your Re-Entry Action Plan will appear here once you submit your report.
        </div>

        <article
            v-for="activity in activities"
            :key="activity.id"
            class="rounded-2xl border bg-white p-5 shadow-sm"
            :class="activity.status === 'Overdue' ? 'border-red-200' : 'border-gray-200'"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold leading-snug text-[#1a2744]">{{ activity.activity }}</h3>
                    <p class="mt-0.5 text-sm text-gray-600"><span class="font-semibold">Expected output:</span> {{ activity.expected_output }}</p>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold" :class="STATUS_STYLES[activity.status]">{{ activity.status }}</span>
            </div>

            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-gray-600">
                <span class="flex items-center gap-1"><CalendarClock :size="14" /> {{ activity.schedule }}</span>
                <span
                    >Budget: <strong>{{ formatMoney(activity.budget) }}</strong></span
                >
                <span
                    v-if="dueLabel(activity)"
                    class="flex items-center gap-1 font-semibold"
                    :class="activity.status === 'Overdue' ? 'text-red-700' : 'text-amber-700'"
                >
                    <Clock :size="14" /> {{ dueLabel(activity) }}
                </span>
            </div>
            <p v-if="activity.remarks" class="mt-2 text-xs text-gray-500">Remarks: {{ activity.remarks }}</p>

            <!-- Submitted report -->
            <div v-if="activity.report && reportingId !== activity.id" class="mt-4 rounded-xl bg-gray-50 p-4 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold text-gray-800">Implementation report · submitted {{ formatDate(activity.report.submitted_at) }}</p>
                    <a
                        :href="activity.report.file_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 text-xs font-bold text-[#1d3fc4] hover:underline"
                    >
                        <FileText :size="13" /> View proof
                    </a>
                </div>
                <p class="mt-1.5 text-gray-700">{{ activity.report.accomplishment }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    Actual output: {{ activity.report.actual_output }} · Completed {{ formatDate(activity.report.date_completed) }} · Actual cost:
                    {{ formatMoney(activity.report.actual_cost) }}
                </p>
                <p v-if="activity.report.status === 'Rejected' && activity.report.remarks" class="mt-2 rounded-lg bg-orange-50 p-2 text-orange-800">
                    <strong>Reviewer note:</strong> {{ activity.report.remarks }}
                </p>
                <p v-if="activity.report.status === 'Approved'" class="mt-2 flex items-center gap-1 font-semibold text-emerald-700">
                    <CheckCircle2 :size="15" /> Approved
                </p>
            </div>

            <!-- Report form -->
            <form
                v-if="reportingId === activity.id"
                class="mt-4 rounded-xl border border-blue-100 bg-blue-50/40 p-4"
                @submit.prevent="submitReport(activity)"
            >
                <h4 class="mb-3 font-bold text-[#1a2744]">Implementation Report</h4>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="tracker-label">What did you accomplish? <span class="text-red-500">*</span></span>
                        <textarea
                            v-model="reportForm.accomplishment"
                            rows="3"
                            class="tracker-input"
                            placeholder="Describe how the activity was carried out"
                        ></textarea>
                        <span v-if="reportForm.errors.accomplishment" class="tracker-error">{{ reportForm.errors.accomplishment }}</span>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="tracker-label">Actual output <span class="text-red-500">*</span></span>
                        <input
                            v-model="reportForm.actual_output"
                            type="text"
                            class="tracker-input"
                            :placeholder="`Planned: ${activity.expected_output}`"
                        />
                        <span v-if="reportForm.errors.actual_output" class="tracker-error">{{ reportForm.errors.actual_output }}</span>
                    </label>
                    <label class="block">
                        <span class="tracker-label">Date completed <span class="text-red-500">*</span></span>
                        <input v-model="reportForm.date_completed" type="date" class="tracker-input" />
                        <span v-if="reportForm.errors.date_completed" class="tracker-error">{{ reportForm.errors.date_completed }}</span>
                    </label>
                    <label class="block">
                        <span class="tracker-label">Actual cost (₱)</span>
                        <input
                            v-model="reportForm.actual_cost"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Leave blank if none"
                            class="tracker-input"
                        />
                        <span v-if="reportForm.errors.actual_cost" class="tracker-error">{{ reportForm.errors.actual_cost }}</span>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="tracker-label">
                            Proof of implementation (PDF or photo)
                            <span v-if="!activity.report" class="text-red-500">*</span>
                            <span v-else class="font-normal text-gray-400">— leave empty to keep your current file</span>
                        </span>
                        <span
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-600 hover:border-[#1d3fc4]"
                        >
                            <Upload :size="15" /> {{ reportForm.file?.name ?? 'Choose a file (max 20 MB)' }}
                            <input type="file" accept="application/pdf,image/jpeg,image/png" class="hidden" @change="onReportFile" />
                        </span>
                        <span v-if="reportForm.errors.file" class="tracker-error">{{ reportForm.errors.file }}</span>
                    </label>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100"
                        @click="reportingId = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="reportForm.processing"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#1d3fc4] px-4 py-2 text-sm font-bold text-white hover:bg-[#1535a8] disabled:opacity-60"
                    >
                        <Loader2 v-if="reportForm.processing" :size="14" class="animate-spin" />
                        <Send v-else :size="14" /> Submit report
                    </button>
                </div>
            </form>

            <!-- Actions -->
            <div v-if="reportingId !== activity.id" class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <button
                    v-if="activity.added_after_submission && !activity.locked"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:bg-red-50 hover:text-red-600"
                    @click="removeActivity(activity)"
                >
                    <Trash2 :size="13" /> Remove
                </button>
                <button
                    v-if="canSubmitReport(activity)"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold"
                    :class="activity.report ? 'bg-[#eef1fc] text-[#1d3fc4] hover:bg-[#dde4fb]' : 'bg-[#1d3fc4] text-white hover:bg-[#1535a8]'"
                    @click="openReport(activity)"
                >
                    <component :is="activity.report ? RotateCcw : Send" :size="13" />
                    {{ activity.report ? 'Update implementation report' : 'Submit implementation report' }}
                </button>
            </div>
        </article>
    </div>
</template>

<style scoped>
/* The program page is light-only; keep inputs light even when the app is in dark mode. */
.action-plan-tracker {
    --background: 0 0% 100%;
    --foreground: 0 0% 3.9%;
    --muted-foreground: 0 0% 45.1%;
    --border: 0 0% 80%;
    color-scheme: light;
}
.tracker-label {
    display: block;
    margin-bottom: 0.3rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #374151;
}
.tracker-input {
    width: 100%;
    border-radius: 0.55rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    padding: 0.55rem 0.8rem;
    font-size: 0.875rem;
    color: #111827;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}
.tracker-input:focus {
    outline: none;
    border-color: #1d3fc4;
    box-shadow: 0 0 0 3px rgba(29, 63, 196, 0.15);
}
.tracker-error {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #dc2626;
}
</style>
