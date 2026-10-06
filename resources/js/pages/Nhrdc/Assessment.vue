<script setup lang="ts">
import NhrdcSelfSignedCopyUpload from '@/components/NhrdcSelfSignedCopyUpload.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    ChevronDown,
    Circle,
    ClipboardCheck,
    FileCheck2,
    FileText,
    Loader2,
    Lock,
    MessageSquareText,
    Save,
    Users,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

// ── Interfaces ────────────────────────────────────────────────────────────────

interface Assessment {
    need_for_training: number;
    relevance_to_duties: number;
    meets_donor_requirements: number;
    completion_of_documents: number;
    requirements_total: number;
}

interface MyRating {
    id: number;
    communication_skills: number;
    alertness: number;
    judgement: number;
    self_confidence: number;
    emotional_stability: number;
    appearance: number;
    total: number;
}

interface Nominee {
    id: number;
    firstname: string;
    middle_name: string | null;
    surname: string;
    sex: 'male' | 'female' | 'other';
    position: string;
    agency: string;
    assessment: Assessment | null;
    my_rating: MyRating | null;
}

interface ForeignProgram {
    id: number;
    program_title: string;
    program_start: string;
    program_end: string;
    slots: number;
    organizing_sponsor: string;
    sponsor: { full_name: string | null } | null;
}

const props = defineProps<{
    program: ForeignProgram;
    nominees: Nominee[];
    hasSignedCopy: boolean;
}>();

const hasSignedCopy = ref(props.hasSignedCopy);

// ── Criteria definitions ─────────────────────────────────────────────────────

const REQUIREMENT_CRITERIA = [
    {
        key: 'need_for_training',
        label: "Nominee's Need for Training",
        max: 20,
        options: [
            { value: 20, label: 'Less than 10 hours of relevant training' },
            { value: 17, label: 'With 10 to 20 hours of relevant training' },
            { value: 15, label: 'With 21 to 30 hours relevant training' },
            { value: 10, label: 'With 31 to 40 hours of relevant training' },
        ],
    },
    {
        key: 'relevance_to_duties',
        label: 'Relevance of the Course to the Present Duties and Responsibilities',
        max: 30,
        options: [
            { value: 30, label: 'Relevant to present work assignment' },
            { value: 28, label: 'Relevant to other work assignment' },
            { value: 20, label: 'Not relevant to work assignment' },
        ],
    },
    {
        key: 'meets_donor_requirements',
        label: 'Nominee Meets Donor Requirements',
        max: 10,
        options: [
            { value: 10, label: 'Meets all requirements' },
            { value: 8, label: 'Lacks 1 requirement' },
            { value: 6, label: 'Lacks 2 requirements' },
            { value: 4, label: 'Lacks 3 or more requirements' },
        ],
    },
    {
        key: 'completion_of_documents',
        label: 'Completion of Documentary Requirements',
        max: 10,
        options: [
            { value: 10, label: 'Submits complete requirements' },
            { value: 8, label: 'Lacks 1 requirement' },
            { value: 6, label: 'Lacks 2 requirements' },
            { value: 4, label: 'Lacks 3 or more requirements' },
        ],
    },
] as const;

const INTERVIEW_CRITERIA = [
    { key: 'communication_skills', label: 'Communication Skills', max: 5 },
    { key: 'alertness', label: 'Alertness', max: 5 },
    { key: 'judgement', label: 'Judgement', max: 5 },
    { key: 'self_confidence', label: 'Self Confidence', max: 5 },
    { key: 'emotional_stability', label: 'Emotional Stability', max: 5 },
    { key: 'appearance', label: 'Appearance', max: 5 },
] as const;

const REQUIREMENTS_MAX = REQUIREMENT_CRITERIA.reduce((s, c) => s + c.max, 0);
const INTERVIEW_MAX = INTERVIEW_CRITERIA.reduce((s, c) => s + c.max, 0);
const GRAND_MAX = REQUIREMENTS_MAX + INTERVIEW_MAX;

type Scores = Record<string, number>;

// ── My rating draft state ────────────────────────────────────────────────────

const expandedId = ref<number | null>(null);
const draft = reactive<Record<number, Scores>>({});
const savingId = ref<number | null>(null);
const savedId = ref<number | null>(null);

function blankDraft(): Scores {
    const s: Scores = {};
    for (const c of INTERVIEW_CRITERIA) s[c.key] = 0;
    return s;
}

function ensureDraft(nominee: Nominee) {
    if (draft[nominee.id]) return;
    if (nominee.my_rating) {
        const r = nominee.my_rating;
        draft[nominee.id] = {
            communication_skills: Number(r.communication_skills),
            alertness: Number(r.alertness),
            judgement: Number(r.judgement),
            self_confidence: Number(r.self_confidence),
            emotional_stability: Number(r.emotional_stability),
            appearance: Number(r.appearance),
        };
    } else {
        draft[nominee.id] = blankDraft();
    }
}

function toggleExpand(nominee: Nominee) {
    ensureDraft(nominee);
    expandedId.value = expandedId.value === nominee.id ? null : nominee.id;
}

function draftTotal(id: number): number {
    const s = draft[id];
    if (!s) return 0;
    const sum = INTERVIEW_CRITERIA.reduce((sum, c) => sum + (Number(s[c.key]) || 0), 0);
    return Math.round(sum * 100) / 100;
}

// The HTML `max` attribute doesn't stop someone from typing a value past it,
// so clamp on every keystroke instead of relying on it.
function clampScore(raw: string, max: number): number {
    const n = Number(raw);
    if (Number.isNaN(n)) return 0;
    return Math.min(Math.max(n, 0), max);
}

// Decimal-cast fields arrive from the backend as fixed-2dp strings (e.g. "18.00") —
// display them cleanly, only showing decimals when they're actually non-zero.
function fmt(value: number | string | null | undefined): string {
    const n = Number(value ?? 0);
    return Number.isInteger(n) ? String(n) : String(Math.round(n * 100) / 100);
}

function optionLabel(criterion: (typeof REQUIREMENT_CRITERIA)[number], value: number | string | null | undefined): string {
    const n = Number(value ?? NaN);
    return criterion.options.find((o) => o.value === n)?.label ?? '';
}

async function saveRating(nominee: Nominee) {
    savingId.value = nominee.id;
    savedId.value = null;
    try {
        const res = await axios.post(route('nhrdc.ratings.save', nominee.id), draft[nominee.id]);
        nominee.my_rating = res.data;
        savedId.value = nominee.id;
        setTimeout(() => {
            if (savedId.value === nominee.id) savedId.value = null;
        }, 2000);
    } finally {
        savingId.value = null;
    }
}

function myGrandTotal(nominee: Nominee): number | null {
    if (!nominee.assessment || !nominee.my_rating) return null;
    return nominee.assessment.requirements_total + nominee.my_rating.total;
}

function myGrandPercent(nominee: Nominee): number | null {
    const total = myGrandTotal(nominee);
    return total === null ? null : Math.round((Number(total) / GRAND_MAX) * 100);
}

type NomineeStatus = 'not-rated' | 'rated' | 'strong';

function nomineeStatus(nominee: Nominee): NomineeStatus {
    if (!nominee.my_rating) return 'not-rated';
    return nominee.my_rating.total / INTERVIEW_MAX >= 0.8 ? 'strong' : 'rated';
}

const STATUS_META: Record<NomineeStatus, { label: string; badge: string }> = {
    'not-rated': {
        label: 'Not Rated',
        badge: 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300',
    },
    rated: {
        label: 'Rated',
        badge: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300',
    },
    strong: {
        label: 'Strong',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
    },
};

function scoreColor(value: number, max: number) {
    const pct = max ? value / max : 0;
    if (pct >= 0.8) return 'border-emerald-300 text-emerald-700 dark:border-emerald-500/50 dark:text-emerald-400 focus:ring-emerald-500/30';
    if (pct >= 0.5) return 'border-indigo-300 text-indigo-700 dark:border-indigo-500/50 dark:text-indigo-300 focus:ring-indigo-500/30';
    return 'border-amber-300 text-amber-700 dark:border-amber-500/50 dark:text-amber-400 focus:ring-amber-500/30';
}

function scoreBarColor(value: number, max: number) {
    const pct = max ? value / max : 0;
    if (pct >= 0.8) return 'bg-emerald-500';
    if (pct >= 0.5) return 'bg-indigo-500';
    return 'bg-amber-500';
}

function scorePercent(value: number, max: number): number {
    return max ? Math.min(Math.max((Number(value) / max) * 100, 0), 100) : 0;
}

// ── Overall progress (derived from the nominees prop only) ───────────────────

const ratedCount = computed(() => props.nominees.filter((n) => !!n.my_rating).length);
const ratedPercent = computed(() => (props.nominees.length ? Math.round((ratedCount.value / props.nominees.length) * 100) : 0));

// ── Helpers ───────────────────────────────────────────────────────────────────

function fullName(n: Nominee) {
    const mi = n.middle_name ? ` ${n.middle_name}` : '';
    return `${n.surname.toUpperCase()}, ${n.firstname}${mi}`;
}

function initials(n: Nominee) {
    return `${n.firstname?.[0] ?? ''}${n.surname?.[0] ?? ''}`.toUpperCase();
}

function avatarClasses(n: Nominee) {
    if (n.sex === 'female') return 'bg-gradient-to-br from-indigo-500 to-violet-600';
    if (n.sex === 'other') return 'bg-gradient-to-br from-slate-500 to-slate-700';
    return 'bg-gradient-to-br from-blue-600 to-indigo-700';
}

const formatDate = (date?: string | null) => {
    if (!date) return '—';
    const d = date.includes('T') ? new Date(date) : new Date(date + 'T00:00:00');
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const durationDays = computed(() => {
    const start = new Date(props.program.program_start + 'T00:00:00');
    const end = new Date(props.program.program_end + 'T00:00:00');
    if (isNaN(start.getTime()) || isNaN(end.getTime())) return null;
    return Math.round((end.getTime() - start.getTime()) / 86400000) + 1;
});

const sponsorDisplay = computed(() => {
    const fullName = props.program.sponsor?.full_name;
    return fullName ? `${fullName} (${props.program.organizing_sponsor})` : props.program.organizing_sponsor;
});
</script>

<template>
    <Head :title="`Rate Interviews — ${program.program_title}`" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 sm:gap-6 sm:p-6">
            <!-- Back -->
            <button
                type="button"
                class="group -ml-2 inline-flex w-fit items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                @click="router.visit(route('nhrdc.programs.index'))"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" /> Back to Programs
            </button>

            <!-- Hero -->
            <section
                class="relative overflow-hidden rounded-2xl border border-indigo-950/20 bg-gradient-to-br from-slate-900 via-indigo-950 to-blue-950 p-5 text-white shadow-lg dark:border-slate-800 sm:p-7"
            >
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div
                        class="absolute inset-0 opacity-[0.05]"
                        style="
                            background-image:
                                linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px);
                            background-size: 32px 32px;
                            mask-image: linear-gradient(to left, black, transparent 75%);
                        "
                    />
                    <div class="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-blue-400/[0.08] blur-3xl" />
                    <div class="absolute -bottom-28 right-10 h-64 w-64 rounded-full border border-white/[0.06]" />
                    <div class="absolute -bottom-16 right-28 h-40 w-40 rounded-full border border-white/[0.05]" />
                </div>

                <div class="relative flex flex-col gap-5">
                    <div class="flex flex-col gap-2">
                        <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-indigo-200/80">
                            <ClipboardCheck class="h-3.5 w-3.5" /> NHRDC • Interview Assessment
                        </p>
                        <h1 class="break-words text-xl font-bold leading-snug tracking-tight sm:text-2xl">{{ program.program_title }}</h1>
                    </div>

                    <dl class="grid grid-cols-1 gap-2.5 sm:grid-cols-3 sm:gap-3">
                        <div class="rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 backdrop-blur-sm">
                            <dt class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-200/70">
                                <Calendar class="h-3 w-3" /> Duration
                            </dt>
                            <dd class="mt-1 text-sm font-semibold">
                                {{ formatDate(program.program_start) }} – {{ formatDate(program.program_end) }}
                            </dd>
                            <dd v-if="durationDays" class="text-[11px] text-white/60">{{ durationDays }} day{{ durationDays > 1 ? 's' : '' }}</dd>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 backdrop-blur-sm">
                            <dt class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-200/70">
                                <Building2 class="h-3 w-3" /> Sponsoring Donor
                            </dt>
                            <dd class="mt-1 break-words text-sm font-semibold">{{ sponsorDisplay }}</dd>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 backdrop-blur-sm">
                            <dt class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-200/70">
                                <Users class="h-3 w-3" /> Slot(s)
                            </dt>
                            <dd class="mt-1 text-sm font-semibold">{{ nominees.length }} of {{ program.slots }}</dd>
                            <dd class="text-[11px] text-white/60">nominee{{ nominees.length === 1 ? '' : 's' }} for interview</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <!-- Progress + assessment sheet -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
                <section
                    class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2"
                >
                    <div
                        class="pointer-events-none absolute -right-12 -top-12 h-36 w-36 rounded-full bg-indigo-500/[0.05] dark:bg-indigo-400/[0.06]"
                        aria-hidden="true"
                    />
                    <div class="relative">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                                Your Rating Progress
                            </p>
                            <span
                                class="rounded-md border px-2 py-0.5 text-[11px] font-semibold tabular-nums"
                                :class="
                                    nominees.length && ratedCount === nominees.length
                                        ? STATUS_META.strong.badge
                                        : ratedCount
                                          ? STATUS_META.rated.badge
                                          : STATUS_META['not-rated'].badge
                                "
                            >
                                {{ ratedPercent }}% complete
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                            <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-slate-50">{{ ratedCount }}</span>
                            <span class="tabular-nums"> / {{ nominees.length }}</span> nominees rated
                        </p>
                        <div
                            class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                            role="progressbar"
                            :aria-valuenow="ratedPercent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="Nominees rated"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="ratedPercent >= 100 ? 'bg-emerald-500' : 'bg-gradient-to-r from-indigo-500 to-blue-500'"
                                :style="{ width: ratedPercent + '%' }"
                            />
                        </div>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            <template v-if="nominees.length && ratedCount === nominees.length">All nominees have your rating.</template>
                            <template v-else>
                                {{ nominees.length - ratedCount }} nominee{{ nominees.length - ratedCount === 1 ? '' : 's' }} remaining
                            </template>
                        </p>
                    </div>
                </section>

                <!-- My assessment sheet: PDF + signed copy -->
                <section
                    class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-3"
                >
                    <div
                        class="pointer-events-none absolute inset-0 opacity-[0.035] dark:opacity-[0.05]"
                        style="
                            background-image: radial-gradient(currentColor 1px, transparent 1px);
                            background-size: 16px 16px;
                            mask-image: linear-gradient(to left, black, transparent 60%);
                        "
                        aria-hidden="true"
                    />
                    <div class="relative flex flex-col gap-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-indigo-100 bg-indigo-50 text-indigo-600 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300"
                            >
                                <FileText class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Documentation</p>
                                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">My Assessment Sheet</h2>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    Generate, review, and submit your signed assessment sheet.
                                </p>
                            </div>
                            <span
                                v-if="hasSignedCopy"
                                class="hidden shrink-0 items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold sm:inline-flex"
                                :class="STATUS_META.strong.badge"
                            >
                                <FileCheck2 class="h-3 w-3" /> Signed copy uploaded
                            </span>
                        </div>

                        <div
                            class="flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:flex-wrap sm:items-center"
                        >
                            <a
                                :href="route('nhrdc.programs.assessment-pdf', program.id)"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:ring-offset-2 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:ring-offset-slate-900"
                            >
                                <FileText class="h-3.5 w-3.5" /> Generate PDF
                            </a>
                            <span class="hidden h-5 w-px bg-slate-200 dark:bg-slate-700 sm:block" aria-hidden="true" />
                            <NhrdcSelfSignedCopyUpload
                                :program-id="program.id"
                                :has-file="hasSignedCopy"
                                @uploaded="hasSignedCopy = true"
                                @deleted="hasSignedCopy = false"
                            />
                        </div>
                        <p
                            v-if="hasSignedCopy"
                            class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 sm:hidden"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" /> Signed copy uploaded
                        </p>
                    </div>
                </section>
            </div>

            <!-- Nominee list -->
            <section class="flex flex-col gap-3">
                <div class="flex items-end justify-between gap-2 px-1">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Nominees</p>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">Interview Assessment</h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Select a nominee to rate</p>
                </div>

                <div
                    v-if="!nominees.length"
                    class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white py-14 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <Users class="h-7 w-7 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">No nominees to rate yet.</p>
                </div>

                <div
                    v-for="n in nominees"
                    :key="n.id"
                    class="overflow-hidden rounded-2xl border bg-white shadow-sm transition-all duration-200 dark:bg-slate-900"
                    :class="
                        expandedId === n.id
                            ? 'border-indigo-300 shadow-md ring-1 ring-indigo-500/10 dark:border-indigo-500/40'
                            : 'border-slate-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:hover:border-slate-700'
                    "
                >
                    <!-- Nominee summary row -->
                    <button
                        type="button"
                        class="flex w-full items-start gap-3 px-4 py-4 text-left transition-colors hover:bg-slate-50/70 focus:outline-none focus-visible:bg-slate-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500/40 dark:hover:bg-slate-800/40 dark:focus-visible:bg-slate-800/40 sm:items-center sm:gap-4 sm:px-5"
                        :aria-expanded="expandedId === n.id"
                        :aria-controls="`nominee-panel-${n.id}`"
                        @click="toggleExpand(n)"
                    >
                        <div
                            class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm ring-2 ring-white dark:ring-slate-900"
                            :class="avatarClasses(n)"
                        >
                            {{ initials(n) }}
                            <span
                                v-if="n.my_rating"
                                class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900"
                                aria-hidden="true"
                            >
                                <CheckCircle2 class="h-3 w-3 text-white" />
                            </span>
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-semibold text-slate-900 dark:text-slate-50">{{ fullName(n) }}</p>
                                <p class="mt-0.5 break-words text-xs text-slate-500 dark:text-slate-400">
                                    {{ n.position }}<span class="text-slate-300 dark:text-slate-600"> · </span>{{ n.agency }}
                                </p>
                                <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                    <span>
                                        Requirements
                                        <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                                            {{ n.assessment ? fmt(n.assessment.requirements_total) : '—' }}/{{ REQUIREMENTS_MAX }}
                                        </span>
                                    </span>
                                    <span>
                                        Interview
                                        <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                                            {{ n.my_rating ? fmt(n.my_rating.total) : '—' }}/{{ INTERVIEW_MAX }}
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <span
                                class="inline-flex w-fit shrink-0 items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-semibold tabular-nums"
                                :class="STATUS_META[nomineeStatus(n)].badge"
                            >
                                <Circle v-if="!n.my_rating" class="h-3 w-3" />
                                <CheckCircle2 v-else class="h-3 w-3" />
                                {{ STATUS_META[nomineeStatus(n)].label }}
                                <template v-if="n.my_rating"> · {{ fmt(n.my_rating.total) }}/{{ INTERVIEW_MAX }}</template>
                            </span>
                        </div>

                        <span
                            class="mt-2.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors dark:text-slate-500 sm:mt-0"
                            :class="expandedId === n.id ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300' : ''"
                        >
                            <ChevronDown class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': expandedId === n.id }" />
                        </span>
                    </button>

                    <!-- Rating sheet -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="-translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <div
                            v-if="expandedId === n.id"
                            :id="`nominee-panel-${n.id}`"
                            class="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-950/40 sm:gap-5 sm:p-5"
                        >
                            <div class="grid grid-cols-1 items-start gap-4 sm:gap-5 xl:grid-cols-2">
                                <!-- Requirements: read-only, encoded by admin -->
                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                                    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-50">
                                                <span
                                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300"
                                                >
                                                    <ClipboardCheck class="h-4 w-4" />
                                                </span>
                                                I. Requirements Assessment
                                            </h3>
                                            <p class="ml-9 mt-0.5 flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400">
                                                <Lock class="h-3 w-3" /> Read-only · encoded by admin
                                            </p>
                                        </div>
                                        <p class="text-right">
                                            <span class="text-lg font-bold tabular-nums text-slate-900 dark:text-slate-50">
                                                {{ n.assessment ? fmt(n.assessment.requirements_total) : '—' }}
                                            </span>
                                            <span class="text-xs font-medium text-slate-400 dark:text-slate-500"> / {{ REQUIREMENTS_MAX }}</span>
                                        </p>
                                    </div>

                                    <div v-if="n.assessment" class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                        <div
                                            v-for="c in REQUIREMENT_CRITERIA"
                                            :key="c.key"
                                            class="flex flex-col rounded-lg border border-slate-100 bg-slate-50/70 px-3.5 py-3 dark:border-slate-800 dark:bg-slate-800/40"
                                        >
                                            <p class="text-[11px] font-medium leading-snug text-slate-600 dark:text-slate-300">{{ c.label }}</p>
                                            <p class="mt-1.5">
                                                <span class="text-base font-bold tabular-nums text-slate-900 dark:text-slate-50">
                                                    {{ fmt((n.assessment as any)[c.key]) }}
                                                </span>
                                                <span class="text-xs text-slate-400 dark:text-slate-500"> / {{ c.max }}</span>
                                            </p>
                                            <p
                                                v-if="optionLabel(c, (n.assessment as any)[c.key])"
                                                class="mt-2 border-t border-slate-200/70 pt-2 text-[11px] leading-snug text-slate-500 dark:border-slate-700/70 dark:text-slate-400"
                                            >
                                                {{ optionLabel(c, (n.assessment as any)[c.key]) }}
                                            </p>
                                        </div>
                                    </div>
                                    <p
                                        v-else
                                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300"
                                    >
                                        Not yet encoded by the admin.
                                    </p>
                                </div>

                                <!-- Interview: my rating -->
                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                                    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-50">
                                                <span
                                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300"
                                                >
                                                    <MessageSquareText class="h-4 w-4" />
                                                </span>
                                                II. Interview — My Rating
                                            </h3>
                                            <p class="ml-9 mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                0 – 5 per criterion, in 0.5 steps
                                            </p>
                                        </div>
                                        <p class="text-right">
                                            <span class="text-lg font-bold tabular-nums text-indigo-700 dark:text-indigo-300">{{
                                                draftTotal(n.id)
                                            }}</span>
                                            <span class="text-xs font-medium text-slate-400 dark:text-slate-500"> / {{ INTERVIEW_MAX }}</span>
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-1 gap-2.5 md:grid-cols-2">
                                        <div
                                            v-for="c in INTERVIEW_CRITERIA"
                                            :key="c.key"
                                            class="rounded-lg border border-slate-100 bg-slate-50/70 px-3.5 py-2.5 transition-colors focus-within:border-indigo-200 focus-within:bg-white dark:border-slate-800 dark:bg-slate-800/40 dark:focus-within:border-indigo-500/30 dark:focus-within:bg-slate-800/70"
                                        >
                                            <div class="flex items-center justify-between gap-3">
                                                <label
                                                    :for="`score-${n.id}-${c.key}`"
                                                    class="text-xs font-semibold text-slate-700 dark:text-slate-200"
                                                >
                                                    {{ c.label }}
                                                </label>
                                                <div class="flex shrink-0 items-center gap-1.5">
                                                    <input
                                                        :id="`score-${n.id}-${c.key}`"
                                                        type="number"
                                                        min="0"
                                                        :max="c.max"
                                                        step="0.5"
                                                        inputmode="decimal"
                                                        :value="draft[n.id][c.key]"
                                                        @input="draft[n.id][c.key] = clampScore(($event.target as HTMLInputElement).value, c.max)"
                                                        class="h-9 w-16 rounded-lg border bg-white px-2 text-right text-sm font-bold tabular-nums transition-colors focus:outline-none focus:ring-2 dark:bg-slate-900"
                                                        :class="scoreColor(draft[n.id][c.key], c.max)"
                                                    />
                                                    <span class="text-xs text-slate-400 dark:text-slate-500">/ {{ c.max }}</span>
                                                </div>
                                            </div>
                                            <div
                                                class="mt-2 h-1 w-full overflow-hidden rounded-full bg-slate-200/70 dark:bg-slate-700/60"
                                                aria-hidden="true"
                                            >
                                                <div
                                                    class="h-full rounded-full transition-all duration-300"
                                                    :class="scoreBarColor(draft[n.id][c.key], c.max)"
                                                    :style="{ width: scorePercent(draft[n.id][c.key], c.max) + '%' }"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-4 flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-end sm:gap-3"
                                    >
                                        <Transition
                                            enter-active-class="transition duration-200"
                                            enter-from-class="opacity-0 translate-x-1"
                                            leave-active-class="transition duration-200"
                                            leave-to-class="opacity-0"
                                        >
                                            <span
                                                v-if="savedId === n.id"
                                                class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                                                role="status"
                                            >
                                                <CheckCircle2 class="h-3.5 w-3.5" /> Saved
                                            </span>
                                        </Transition>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition-all hover:bg-indigo-700 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:ring-offset-2 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:ring-offset-slate-900"
                                            :disabled="savingId === n.id"
                                            @click="saveRating(n)"
                                        >
                                            <Loader2 v-if="savingId === n.id" class="h-4 w-4 animate-spin" />
                                            <Save v-else class="h-4 w-4" />
                                            {{ savingId === n.id ? 'Saving…' : 'Save My Rating' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- My Grand Total -->
                            <div
                                class="relative overflow-hidden rounded-xl border border-indigo-950/30 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-4 text-white shadow-md dark:border-slate-700/60 sm:p-5"
                            >
                                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                                    <div class="absolute -right-10 -top-16 h-44 w-44 rounded-full bg-indigo-400/[0.08] blur-2xl" />
                                    <div class="absolute -bottom-16 right-24 h-36 w-36 rounded-full border border-white/[0.05]" />
                                </div>

                                <div class="relative">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-indigo-200/70">
                                        Final Assessment · My Grand Total
                                    </p>

                                    <div
                                        v-if="myGrandTotal(n) !== null"
                                        class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end sm:gap-6"
                                    >
                                        <div class="rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2.5">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Requirements</p>
                                            <p class="mt-0.5 tabular-nums">
                                                <span class="text-lg font-bold">{{ n.assessment?.requirements_total }}</span>
                                                <span class="text-xs text-white/50"> / {{ REQUIREMENTS_MAX }}</span>
                                            </p>
                                        </div>
                                        <div class="rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2.5">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-white/50">My Interview</p>
                                            <p class="mt-0.5 tabular-nums">
                                                <span class="text-lg font-bold">{{ n.my_rating?.total }}</span>
                                                <span class="text-xs text-white/50"> / {{ INTERVIEW_MAX }}</span>
                                            </p>
                                        </div>
                                        <div class="col-span-2 border-t border-white/10 pt-3 sm:col-span-1 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-200/70">Grand Total</p>
                                            <p class="tabular-nums">
                                                <span class="text-3xl font-extrabold tracking-tight">{{ myGrandTotal(n) }}</span>
                                                <span class="text-sm font-semibold text-white/50"> / {{ GRAND_MAX }}</span>
                                                <span
                                                    class="ml-2 rounded-md bg-emerald-400/15 px-1.5 py-0.5 align-middle text-[11px] font-semibold text-emerald-300"
                                                >
                                                    {{ myGrandPercent(n) }}%
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <p v-else class="mt-2 text-xs text-white/70">
                                        Appears once the admin encodes Requirements and you save your interview rating.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
