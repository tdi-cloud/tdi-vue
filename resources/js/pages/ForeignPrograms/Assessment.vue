<script setup lang="ts">
import NhrdcMemberModal from '@/components/NhrdcMemberModal.vue';
import NhrdcSignedCopyUpload from '@/components/NhrdcSignedCopyUpload.vue';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    ChevronDown,
    ClipboardCheck,
    FileText,
    GraduationCap,
    MessageSquareText,
    Pencil,
    Save,
    Settings,
    Trash2,
    UserCheck,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

const { confirmDialog } = useConfirm();

// ── Interfaces ────────────────────────────────────────────────────────────────

interface Assessment {
    id: number;
    need_for_training: number;
    relevance_to_duties: number;
    meets_donor_requirements: number;
    completion_of_documents: number;
    requirements_total: number;
    assessed_at: string | null;
}

interface InterviewRating {
    id: number;
    nhrdc_empcode: string;
    nhrdc_name: string | null;
    nhrdc_position: string | null;
    communication_skills: number;
    alertness: number;
    judgement: number;
    self_confidence: number;
    emotional_stability: number;
    appearance: number;
    total: number;
    rated_at: string | null;
}

interface NhrdcRosterMember {
    id: number;
    empcode: string;
    name: string | null;
    position: string | null;
    role: string;
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
    interview_ratings: InterviewRating[];
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

interface NhrdcSignature {
    nhrdc_empcode: string;
    uploaded_at: string;
}

const props = defineProps<{
    program: ForeignProgram;
    nominees: Nominee[];
    nhrdcSignatures: Record<string, NhrdcSignature>;
}>();

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

// ── Requirements section state (draft, saved by the admin/user) ────────────────

const expandedId = ref<number | null>(null);
const savingId = ref<number | null>(null);
const savedId = ref<number | null>(null);
const scores = reactive<Record<number, Scores>>({});

function blankScores(): Scores {
    const s: Scores = {};
    // Requirements start at full marks — the assessor deducts points as needed.
    for (const c of REQUIREMENT_CRITERIA) s[c.key] = c.max;
    return s;
}

function ensureScores(nominee: Nominee) {
    if (scores[nominee.id]) return;
    if (nominee.assessment) {
        const a = nominee.assessment;
        scores[nominee.id] = {
            need_for_training: Number(a.need_for_training),
            relevance_to_duties: Number(a.relevance_to_duties),
            meets_donor_requirements: Number(a.meets_donor_requirements),
            completion_of_documents: Number(a.completion_of_documents),
        };
    } else {
        scores[nominee.id] = blankScores();
    }
}

function reqTotal(id: number): number {
    const s = scores[id];
    if (!s) return 0;
    const sum = REQUIREMENT_CRITERIA.reduce((sum, c) => sum + (Number(s[c.key]) || 0), 0);
    return Math.round(sum * 100) / 100;
}

async function saveAssessment(nominee: Nominee) {
    savingId.value = nominee.id;
    savedId.value = null;
    try {
        const res = await axios.post(route('foreign-nominees.assessment.save', nominee.id), scores[nominee.id]);
        nominee.assessment = res.data;
        savedId.value = nominee.id;
        setTimeout(() => {
            if (savedId.value === nominee.id) savedId.value = null;
        }, 2000);
    } finally {
        savingId.value = null;
    }
}

// ── Interview section: NHRDC roster + per-rater ratings ─────────────────────────

const nhrdcRoster = ref<NhrdcRosterMember[]>([]);
const showNhrdcModal = ref(false);
const showSheetsModal = ref(false);
const signatures = reactive<Record<string, NhrdcSignature>>({ ...props.nhrdcSignatures });

async function fetchNhrdcRoster() {
    const { data } = await axios.get(route('nhrdc-members.index'));
    nhrdcRoster.value = data;
}
onMounted(fetchNhrdcRoster);

function hasSignedCopy(empcode: string): boolean {
    return !!signatures[empcode];
}

function onSignatureUploaded(empcode: string) {
    signatures[empcode] = { nhrdc_empcode: empcode, uploaded_at: new Date().toISOString() };
}

function onSignatureDeleted(empcode: string) {
    delete signatures[empcode];
}

function blankRatingDraft(): Scores {
    const s: Scores = {};
    for (const c of INTERVIEW_CRITERIA) s[c.key] = 0;
    return s;
}

const raterEmpcode = ref('');
const ratingDraft = reactive<Scores>(blankRatingDraft());
const savingRating = ref(false);
const savedRatingFor = ref<string | null>(null);

function existingRatingFor(nominee: Nominee, empcode: string): InterviewRating | null {
    return nominee.interview_ratings.find((r) => r.nhrdc_empcode === empcode) ?? null;
}

function selectRater(nominee: Nominee, empcode: string) {
    raterEmpcode.value = empcode;
    const existing = empcode ? existingRatingFor(nominee, empcode) : null;
    const fresh = existing
        ? {
              communication_skills: Number(existing.communication_skills),
              alertness: Number(existing.alertness),
              judgement: Number(existing.judgement),
              self_confidence: Number(existing.self_confidence),
              emotional_stability: Number(existing.emotional_stability),
              appearance: Number(existing.appearance),
          }
        : blankRatingDraft();
    Object.assign(ratingDraft, fresh);
}

function ratingDraftTotal(): number {
    const sum = INTERVIEW_CRITERIA.reduce((sum, c) => sum + (Number(ratingDraft[c.key]) || 0), 0);
    return Math.round(sum * 100) / 100;
}

// The HTML `max` attribute doesn't stop someone from typing a value past it,
// so clamp on every keystroke instead of relying on it.
function clampScore(raw: string, max: number): number {
    const n = Number(raw);
    if (Number.isNaN(n)) return 0;
    return Math.min(Math.max(n, 0), max);
}

function toggleExpand(nominee: Nominee) {
    ensureScores(nominee);
    expandedId.value = expandedId.value === nominee.id ? null : nominee.id;
    raterEmpcode.value = '';
    Object.assign(ratingDraft, blankRatingDraft());
}

async function saveRating(nominee: Nominee) {
    if (!raterEmpcode.value) return;
    savingRating.value = true;
    savedRatingFor.value = null;
    try {
        const res = await axios.post(route('foreign-nominees.interview-ratings.save', nominee.id), {
            nhrdc_empcode: raterEmpcode.value,
            ...ratingDraft,
        });
        const idx = nominee.interview_ratings.findIndex((r) => r.nhrdc_empcode === raterEmpcode.value);
        if (idx >= 0) {
            nominee.interview_ratings.splice(idx, 1, res.data);
        } else {
            nominee.interview_ratings.push(res.data);
        }
        savedRatingFor.value = raterEmpcode.value;
        setTimeout(() => {
            if (savedRatingFor.value === raterEmpcode.value) savedRatingFor.value = null;
        }, 2000);
        raterEmpcode.value = '';
        Object.assign(ratingDraft, blankRatingDraft());
    } finally {
        savingRating.value = false;
    }
}

function editRating(nominee: Nominee, rating: InterviewRating) {
    selectRater(nominee, rating.nhrdc_empcode);
}

async function removeRating(nominee: Nominee, rating: InterviewRating) {
    if (!(await confirmDialog(`Remove ${rating.nhrdc_name ?? 'this'}'s interview rating for this nominee?`))) return;
    await axios.delete(route('foreign-nominee-interview-ratings.destroy', rating.id));
    nominee.interview_ratings = nominee.interview_ratings.filter((r) => r.id !== rating.id);
    if (raterEmpcode.value === rating.nhrdc_empcode) {
        raterEmpcode.value = '';
        Object.assign(ratingDraft, blankRatingDraft());
    }
}

function onNhrdcRosterSelected(member: { empcode: string }) {
    const nominee = props.nominees.find((n) => n.id === expandedId.value);
    if (nominee) selectRater(nominee, member.empcode);
}

// ── Totals ────────────────────────────────────────────────────────────────────
// Each NHRDC member's interview rating stands on its own — it is combined
// with the (shared) Requirements score into that rater's own Grand Total.
// Ratings are never averaged or merged together into a single figure.

function ratingGrandTotal(nominee: Nominee, rating: InterviewRating): number {
    return (nominee.assessment?.requirements_total ?? 0) + rating.total;
}

function hasAnyScore(nominee: Nominee): boolean {
    return !!nominee.assessment || nominee.interview_ratings.length > 0;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function fullName(n: Nominee) {
    const mi = n.middle_name ? ` ${n.middle_name}` : '';
    return `${n.surname.toUpperCase()}, ${n.firstname}${mi}`;
}

function initials(n: Nominee) {
    return `${n.firstname?.[0] ?? ''}${n.surname?.[0] ?? ''}`.toUpperCase();
}

function avatarClasses(n: Nominee) {
    if (n.sex === 'female') return 'bg-gradient-to-br from-pink-500 to-rose-500';
    if (n.sex === 'other') return 'bg-gradient-to-br from-violet-500 to-indigo-500';
    return 'bg-gradient-to-br from-sky-500 to-blue-600';
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

function scoreColor(value: number, max: number) {
    const pct = max ? value / max : 0;
    if (pct >= 0.8) return 'border-emerald-400 text-emerald-700 dark:text-emerald-400 focus:ring-emerald-500';
    if (pct >= 0.5) return 'border-blue-400 text-blue-700 dark:text-blue-400 focus:ring-blue-500';
    return 'border-amber-400 text-amber-700 dark:text-amber-400 focus:ring-amber-500';
}

function gradeBadgeClass(n: Nominee) {
    if (!n.assessment) return 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    const pct = n.assessment.requirements_total / REQUIREMENTS_MAX;
    if (pct >= 0.8) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300';
    if (pct >= 0.5) return 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300';
    return 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300';
}
</script>

<template>
    <Head :title="`Nominee Assessment — ${program.program_title}`" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4">
            <!-- Back -->
            <button
                class="flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                @click="router.visit(route('foreign-programs.show', program.id))"
            >
                <ArrowLeft class="h-4 w-4" /> Back to Program
            </button>

            <!-- Hero: Assessment Sheet Header -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-700 via-blue-700 to-sky-600 p-6 text-white shadow-xl">
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div class="absolute -right-8 -top-8 h-48 w-48 rounded-full bg-white/5" />
                    <div class="absolute -bottom-12 -right-4 h-64 w-64 rounded-full bg-white/5" />
                </div>

                <div class="relative flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span
                            class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide"
                        >
                            <ClipboardCheck class="h-3.5 w-3.5" /> Nominee Assessment Sheet
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-1.5 text-xs font-bold transition-colors hover:bg-white/25"
                                @click="showSheetsModal = true"
                            >
                                <FileText class="h-3.5 w-3.5" /> Generate PDF
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-1.5 text-xs font-bold transition-colors hover:bg-white/25"
                                @click="showNhrdcModal = true"
                            >
                                <Settings class="h-3.5 w-3.5" /> Manage NHRDC Roster
                            </button>
                        </div>
                    </div>

                    <h1 class="text-xl font-bold leading-tight md:text-2xl">{{ program.program_title }}</h1>

                    <div class="mt-2 grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div class="rounded-xl bg-white/10 px-4 py-3">
                            <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-white/70">
                                <Calendar class="h-3 w-3" /> Duration
                            </p>
                            <p class="mt-1 text-sm font-bold">{{ formatDate(program.program_start) }} – {{ formatDate(program.program_end) }}</p>
                            <p v-if="durationDays" class="text-[11px] text-white/70">{{ durationDays }} day{{ durationDays > 1 ? 's' : '' }}</p>
                        </div>
                        <div class="rounded-xl bg-white/10 px-4 py-3">
                            <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-white/70">
                                <Building2 class="h-3 w-3" /> Sponsoring Donor
                            </p>
                            <p class="mt-1 text-sm font-bold">{{ sponsorDisplay }}</p>
                        </div>
                        <div class="rounded-xl bg-white/10 px-4 py-3">
                            <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-white/70">
                                <Users class="h-3 w-3" /> Slot(s)
                            </p>
                            <p class="mt-1 text-sm font-bold">{{ nominees.length }} of {{ program.slots }}</p>
                        </div>
                        <div class="rounded-xl bg-white/10 px-4 py-3">
                            <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-white/70">
                                <GraduationCap class="h-3 w-3" /> Grading Scale
                            </p>
                            <p class="mt-1 text-sm font-bold">
                                Requirements {{ REQUIREMENTS_MAX }} + Interview {{ INTERVIEW_MAX }} = {{ GRAND_MAX }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nominee list -->
            <div v-if="!nominees.length" class="rounded-2xl border border-dashed py-14 text-center text-sm text-muted-foreground">
                No nominees to assess yet.
            </div>

            <div v-else class="flex flex-col gap-3">
                <div v-for="n in nominees" :key="n.id" class="overflow-hidden rounded-2xl border bg-background shadow-sm">
                    <!-- Nominee summary row -->
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition-colors hover:bg-muted/30"
                        @click="toggleExpand(n)"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                :class="avatarClasses(n)"
                            >
                                {{ initials(n) }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold">{{ fullName(n) }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ n.position }} · {{ n.agency }}</p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <span class="rounded-full px-3 py-1.5 text-xs font-bold" :class="gradeBadgeClass(n)">
                                <template v-if="hasAnyScore(n)">
                                    Req {{ n.assessment?.requirements_total ?? 0 }}/{{ REQUIREMENTS_MAX }}
                                    <span class="font-normal">· {{ n.interview_ratings.length }} NHRDC</span>
                                </template>
                                <template v-else>Not yet assessed</template>
                            </span>
                            <ChevronDown class="h-4 w-4 text-muted-foreground transition-transform" :class="{ 'rotate-180': expandedId === n.id }" />
                        </div>
                    </button>

                    <!-- Assessment sheet -->
                    <div v-if="expandedId === n.id" class="flex flex-col gap-6 border-t bg-muted/10 px-5 py-5">
                        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                            <!-- Section I: Requirements -->
                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="flex items-center gap-1.5 text-sm font-bold">
                                        <ClipboardCheck class="h-4 w-4 text-blue-600" />
                                        I. Requirements Assessment
                                    </h3>
                                    <span class="text-xs font-bold text-blue-700 dark:text-blue-400">
                                        {{ reqTotal(n.id) }} / {{ REQUIREMENTS_MAX }}
                                    </span>
                                </div>

                                <div class="flex flex-col gap-4">
                                    <div v-for="c in REQUIREMENT_CRITERIA" :key="c.key" class="flex flex-col gap-1.5">
                                        <p class="text-xs font-semibold">
                                            {{ c.label }} <span class="font-normal text-muted-foreground">({{ c.max }} pts)</span>
                                        </p>
                                        <div class="flex flex-col gap-1.5">
                                            <label
                                                v-for="opt in c.options"
                                                :key="opt.value"
                                                class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-1.5 text-xs transition-colors"
                                                :class="
                                                    scores[n.id][c.key] === opt.value
                                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                                                        : 'hover:bg-muted/40'
                                                "
                                            >
                                                <input
                                                    type="radio"
                                                    :name="`req-${n.id}-${c.key}`"
                                                    :value="opt.value"
                                                    v-model.number="scores[n.id][c.key]"
                                                    class="h-3.5 w-3.5 shrink-0 accent-blue-600"
                                                />
                                                <span class="flex-1">{{ opt.label }}</span>
                                                <span class="shrink-0 font-bold tabular-nums">{{ opt.value }} pts</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-end gap-3">
                                    <span v-if="savedId === n.id" class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                                        <CheckCircle2 class="h-3.5 w-3.5" /> Saved
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-blue-700 disabled:opacity-60"
                                        :disabled="savingId === n.id"
                                        @click="saveAssessment(n)"
                                    >
                                        <Save class="h-3.5 w-3.5" /> {{ savingId === n.id ? 'Saving…' : 'Save Requirements' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Section II: Interview (multiple NHRDC raters) -->
                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="flex items-center gap-1.5 text-sm font-bold">
                                        <MessageSquareText class="h-4 w-4 text-indigo-600" />
                                        II. Interview
                                    </h3>
                                    <span class="text-xs font-bold text-indigo-700 dark:text-indigo-400">
                                        {{ n.interview_ratings.length }} NHRDC rating{{ n.interview_ratings.length === 1 ? '' : 's' }}
                                    </span>
                                </div>

                                <!-- Existing ratings -->
                                <div v-if="n.interview_ratings.length" class="mb-4 flex flex-col gap-2">
                                    <div
                                        v-for="rating in n.interview_ratings"
                                        :key="rating.id"
                                        class="flex items-center justify-between gap-2 rounded-lg border bg-background px-3 py-2"
                                    >
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-bold">{{ rating.nhrdc_name }}</p>
                                            <p class="truncate text-[11px] text-muted-foreground">{{ rating.nhrdc_position }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <span class="text-xs font-bold text-indigo-700 dark:text-indigo-400"
                                                >{{ rating.total }} / {{ INTERVIEW_MAX }}</span
                                            >
                                            <button
                                                type="button"
                                                class="p-1 text-muted-foreground transition-colors hover:text-blue-600"
                                                title="Edit rating"
                                                @click="editRating(n, rating)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                class="p-1 text-muted-foreground transition-colors hover:text-red-500"
                                                title="Remove rating"
                                                @click="removeRating(n, rating)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="mb-4 text-xs text-amber-700 dark:text-amber-400">
                                    No NHRDC member has rated this nominee's interview yet.
                                </p>

                                <!-- Add / edit a rating -->
                                <div
                                    class="rounded-xl border border-dashed border-indigo-300 bg-indigo-50/50 px-4 py-3 dark:border-indigo-800 dark:bg-indigo-950/20"
                                >
                                    <p
                                        class="mb-2 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-indigo-700 dark:text-indigo-400"
                                    >
                                        <UserCheck class="h-3 w-3" /> Add / Update NHRDC Rating
                                    </p>

                                    <select
                                        :value="raterEmpcode"
                                        class="mb-3 w-full rounded-lg border bg-background px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        @change="selectRater(n, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="">— Select NHRDC member —</option>
                                        <option v-for="m in nhrdcRoster" :key="m.empcode" :value="m.empcode">
                                            {{ m.name }} — {{ m.role }}{{ existingRatingFor(n, m.empcode) ? ' (already rated)' : '' }}
                                        </option>
                                    </select>
                                    <p v-if="!nhrdcRoster.length" class="-mt-2 mb-3 text-[11px] text-muted-foreground">
                                        No NHRDC members yet — click "Manage NHRDC Roster" above to add one.
                                    </p>

                                    <template v-if="raterEmpcode">
                                        <div class="grid grid-cols-1 gap-x-6 gap-y-2 md:grid-cols-2">
                                            <div v-for="c in INTERVIEW_CRITERIA" :key="c.key" class="grid grid-cols-[1fr_auto] items-center gap-x-4">
                                                <p class="text-xs font-semibold">{{ c.label }}</p>
                                                <div class="flex shrink-0 items-center justify-end gap-1.5">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        :max="c.max"
                                                        step="0.5"
                                                        :value="ratingDraft[c.key]"
                                                        @input="ratingDraft[c.key] = clampScore(($event.target as HTMLInputElement).value, c.max)"
                                                        class="w-16 rounded-lg border bg-background px-2 py-1 text-right text-sm font-bold tabular-nums focus:outline-none focus:ring-2"
                                                        :class="scoreColor(ratingDraft[c.key], c.max)"
                                                    />
                                                    <span class="text-xs font-normal text-muted-foreground">/ {{ c.max }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-center justify-between gap-3">
                                            <span class="text-xs font-bold text-indigo-700 dark:text-indigo-400">
                                                {{ ratingDraftTotal() }} / {{ INTERVIEW_MAX }}
                                            </span>
                                            <div class="flex items-center gap-3">
                                                <span
                                                    v-if="savedRatingFor === raterEmpcode"
                                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600"
                                                >
                                                    <CheckCircle2 class="h-3.5 w-3.5" /> Saved
                                                </span>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-indigo-700 disabled:opacity-60"
                                                    :disabled="savingRating"
                                                    @click="saveRating(n)"
                                                >
                                                    <Save class="h-3.5 w-3.5" /> {{ savingRating ? 'Saving…' : 'Save Rating' }}
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Grand Total per NHRDC — each rater's score stands on its own, never averaged together -->
                        <div class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-4 text-white">
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-white/70">Grand Total per NHRDC</p>

                            <div v-if="n.interview_ratings.length" class="divide-y divide-white/15">
                                <div
                                    v-for="rating in n.interview_ratings"
                                    :key="rating.id"
                                    class="flex items-center justify-between gap-3 py-2 first:pt-1 last:pb-0"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold">{{ rating.nhrdc_name }}</p>
                                        <p class="truncate text-[11px] text-white/70">{{ rating.nhrdc_position }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-xl font-extrabold leading-none">
                                            {{ ratingGrandTotal(n, rating) }}
                                            <span class="text-xs font-semibold text-white/70">/ {{ GRAND_MAX }}</span>
                                        </p>
                                        <p class="mt-0.5 text-[10px] text-white/70">
                                            Req {{ n.assessment?.requirements_total ?? 0 }} + Int {{ rating.total }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="mt-1 text-xs text-white/70">
                                No NHRDC rating yet — a grand total appears here per NHRDC member once they rate the interview.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <NhrdcMemberModal v-if="showNhrdcModal" @close="showNhrdcModal = false" @select="onNhrdcRosterSelected" @updated="fetchNhrdcRoster" />
        </Teleport>

        <!-- NHRDC Assessment Sheets: one PDF per NHRDC member, signed copy upload -->
        <Teleport to="body">
            <div
                v-if="showSheetsModal"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
                @click.self="showSheetsModal = false"
            >
                <div class="flex max-h-[80vh] w-full max-w-lg flex-col rounded-2xl bg-background shadow-2xl">
                    <!-- Header -->
                    <div class="flex shrink-0 items-center gap-3 border-b px-5 py-4">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600">
                            <FileText class="h-4 w-4 text-white" />
                        </div>
                        <div>
                            <h3 class="text-sm font-extrabold leading-none">NHRDC Assessment Sheets</h3>
                            <p class="mt-0.5 max-w-sm text-xs text-muted-foreground">
                                Each NHRDC member gets their own printable sheet listing every nominee. Interview columns are left blank until that
                                member rates a nominee. After it's signed, upload the scanned copy below.
                            </p>
                        </div>
                        <button
                            class="ml-auto shrink-0 text-muted-foreground transition-colors hover:text-foreground"
                            @click="showSheetsModal = false"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Roster -->
                    <div class="flex-1 overflow-y-auto px-5 py-4">
                        <div v-if="!nhrdcRoster.length" class="text-xs text-muted-foreground">
                            No NHRDC members yet — click "Manage NHRDC Roster" to add one.
                        </div>

                        <div v-else class="flex flex-col divide-y overflow-hidden rounded-xl border">
                            <div v-for="m in nhrdcRoster" :key="m.empcode" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold">
                                        {{ m.name }} <span class="font-normal text-muted-foreground">— {{ m.role }}</span>
                                    </p>
                                    <a
                                        :href="route('foreign-programs.nhrdc-assessment-pdf', [program.id, m.id])"
                                        target="_blank"
                                        rel="noopener"
                                        class="mt-0.5 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
                                    >
                                        <FileText class="h-3 w-3" /> Generate PDF
                                    </a>
                                </div>
                                <NhrdcSignedCopyUpload
                                    :program-id="program.id"
                                    :nhrdc-member-id="m.id"
                                    :has-file="hasSignedCopy(m.empcode)"
                                    @uploaded="onSignatureUploaded(m.empcode)"
                                    @deleted="onSignatureDeleted(m.empcode)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
