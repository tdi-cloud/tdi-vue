<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft, ClipboardCheck, Calendar, Building2, Users,
    ChevronDown, Save, CheckCircle2, MessageSquareText, FileText,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import NhrdcSelfSignedCopyUpload from '@/components/NhrdcSelfSignedCopyUpload.vue';

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
    { key: 'need_for_training', label: "Nominee's Need for Training", max: 20 },
    { key: 'relevance_to_duties', label: 'Relevance of the Course to the Present Duties and Responsibilities', max: 30 },
    { key: 'meets_donor_requirements', label: 'Nominee Meets Donor Requirements', max: 10 },
    { key: 'completion_of_documents', label: 'Completion of Documentary Requirements', max: 10 },
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
const draft      = reactive<Record<number, Scores>>({});
const savingId   = ref<number | null>(null);
const savedId    = ref<number | null>(null);

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

async function saveRating(nominee: Nominee) {
    savingId.value = nominee.id;
    savedId.value = null;
    try {
        const res = await axios.post(route('nhrdc.ratings.save', nominee.id), draft[nominee.id]);
        nominee.my_rating = res.data;
        savedId.value = nominee.id;
        setTimeout(() => { if (savedId.value === nominee.id) savedId.value = null; }, 2000);
    } finally {
        savingId.value = null;
    }
}

function myGrandTotal(nominee: Nominee): number | null {
    if (!nominee.assessment || !nominee.my_rating) return null;
    return nominee.assessment.requirements_total + nominee.my_rating.total;
}

function hasAnyScore(nominee: Nominee): boolean {
    return !!nominee.assessment || !!nominee.my_rating;
}

function gradeBadgeClass(nominee: Nominee) {
    if (!nominee.my_rating) return 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    const pct = nominee.my_rating.total / INTERVIEW_MAX;
    if (pct >= 0.8) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300';
    if (pct >= 0.5) return 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300';
    return 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300';
}

function scoreColor(value: number, max: number) {
    const pct = max ? value / max : 0;
    if (pct >= 0.8) return 'border-emerald-400 text-emerald-700 dark:text-emerald-400 focus:ring-emerald-500';
    if (pct >= 0.5) return 'border-blue-400 text-blue-700 dark:text-blue-400 focus:ring-blue-500';
    return 'border-amber-400 text-amber-700 dark:text-amber-400 focus:ring-amber-500';
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
</script>

<template>
    <Head :title="`Rate Interviews — ${program.program_title}`" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4">

            <!-- Back -->
            <button
                class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground w-fit transition-colors"
                @click="router.visit(route('nhrdc.programs.index'))"
            >
                <ArrowLeft class="h-4 w-4" /> Back to Programs
            </button>

            <!-- Hero -->
            <div class="relative rounded-2xl bg-gradient-to-br from-indigo-700 via-blue-700 to-sky-600 p-6 text-white shadow-xl overflow-hidden">
                <div class="absolute inset-0 overflow-hidden pointer-events-none">
                    <div class="absolute -top-8 -right-8 h-48 w-48 rounded-full bg-white/5" />
                    <div class="absolute -bottom-12 -right-4 h-64 w-64 rounded-full bg-white/5" />
                </div>

                <div class="relative flex flex-col gap-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-white/20 uppercase tracking-wide w-fit">
                        <ClipboardCheck class="h-3.5 w-3.5" /> Interview Rating
                    </span>

                    <h1 class="text-xl md:text-2xl font-bold leading-tight">{{ program.program_title }}</h1>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-2">
                        <div class="rounded-xl bg-white/10 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70 flex items-center gap-1.5">
                                <Calendar class="h-3 w-3" /> Duration
                            </p>
                            <p class="text-sm font-bold mt-1">{{ formatDate(program.program_start) }} – {{ formatDate(program.program_end) }}</p>
                            <p v-if="durationDays" class="text-[11px] text-white/70">{{ durationDays }} day{{ durationDays > 1 ? 's' : '' }}</p>
                        </div>
                        <div class="rounded-xl bg-white/10 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70 flex items-center gap-1.5">
                                <Building2 class="h-3 w-3" /> Sponsoring Donor
                            </p>
                            <p class="text-sm font-bold mt-1">{{ sponsorDisplay }}</p>
                        </div>
                        <div class="rounded-xl bg-white/10 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70 flex items-center gap-1.5">
                                <Users class="h-3 w-3" /> Slot(s)
                            </p>
                            <p class="text-sm font-bold mt-1">{{ nominees.length }} of {{ program.slots }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My assessment sheet: PDF + signed copy -->
            <div class="rounded-2xl border bg-background shadow-sm p-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold flex items-center gap-1.5">
                        <FileText class="h-4 w-4 text-indigo-600" /> My Assessment Sheet
                    </p>
                    <a
                        :href="route('nhrdc.programs.assessment-pdf', program.id)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700 mt-1"
                    >
                        <FileText class="h-3 w-3" /> Generate PDF
                    </a>
                </div>
                <NhrdcSelfSignedCopyUpload
                    :program-id="program.id"
                    :has-file="hasSignedCopy"
                    @uploaded="hasSignedCopy = true"
                    @deleted="hasSignedCopy = false"
                />
            </div>

            <!-- Nominee list -->
            <div v-if="!nominees.length" class="rounded-2xl border border-dashed py-14 text-center text-sm text-muted-foreground">
                No nominees to rate yet.
            </div>

            <div v-else class="flex flex-col gap-3">
                <div
                    v-for="n in nominees"
                    :key="n.id"
                    class="rounded-2xl border bg-background shadow-sm overflow-hidden"
                >
                    <!-- Nominee summary row -->
                    <button
                        type="button"
                        class="w-full flex items-center justify-between gap-3 px-5 py-4 hover:bg-muted/30 transition-colors text-left"
                        @click="toggleExpand(n)"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="shrink-0 h-10 w-10 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                :class="avatarClasses(n)"
                            >
                                {{ initials(n) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold truncate">{{ fullName(n) }}</p>
                                <p class="text-xs text-muted-foreground truncate">{{ n.position }} · {{ n.agency }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <span class="text-xs font-bold px-3 py-1.5 rounded-full" :class="gradeBadgeClass(n)">
                                <template v-if="hasAnyScore(n)">
                                    {{ n.my_rating ? `My rating: ${n.my_rating.total}/${INTERVIEW_MAX}` : 'Not yet rated' }}
                                </template>
                                <template v-else>Not yet rated</template>
                            </span>
                            <ChevronDown class="h-4 w-4 text-muted-foreground transition-transform" :class="{ 'rotate-180': expandedId === n.id }" />
                        </div>
                    </button>

                    <!-- Rating sheet -->
                    <div v-if="expandedId === n.id" class="border-t bg-muted/10 px-5 py-5 flex flex-col gap-6">

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                        <!-- Requirements: read-only, encoded by admin -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-bold flex items-center gap-1.5">
                                    <ClipboardCheck class="h-4 w-4 text-blue-600" />
                                    I. Requirements Assessment <span class="font-normal text-muted-foreground text-xs">(encoded by admin)</span>
                                </h3>
                                <span class="text-xs font-bold text-blue-700 dark:text-blue-400">
                                    {{ n.assessment ? fmt(n.assessment.requirements_total) : '—' }} / {{ REQUIREMENTS_MAX }}
                                </span>
                            </div>

                            <div v-if="n.assessment" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div v-for="c in REQUIREMENT_CRITERIA" :key="c.key" class="rounded-lg border bg-background px-3 py-2">
                                    <p class="text-[11px] text-muted-foreground leading-tight">{{ c.label }}</p>
                                    <p class="text-sm font-bold mt-0.5">{{ fmt((n.assessment as any)[c.key]) }} <span class="text-xs font-normal text-muted-foreground">/ {{ c.max }}</span></p>
                                </div>
                            </div>
                            <p v-else class="text-xs text-amber-700 dark:text-amber-400">
                                Not yet encoded by the admin.
                            </p>
                        </div>

                        <!-- Interview: my rating -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-bold flex items-center gap-1.5">
                                    <MessageSquareText class="h-4 w-4 text-indigo-600" />
                                    II. Interview — My Rating
                                </h3>
                                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-400">
                                    {{ draftTotal(n.id) }} / {{ INTERVIEW_MAX }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2">
                                <div v-for="c in INTERVIEW_CRITERIA" :key="c.key" class="grid grid-cols-[1fr_auto] gap-x-4 items-center">
                                    <p class="text-xs font-semibold">{{ c.label }}</p>
                                    <div class="flex items-center gap-1.5 justify-end shrink-0">
                                        <input
                                            type="number"
                                            min="0"
                                            :max="c.max"
                                            step="0.5"
                                            :value="draft[n.id][c.key]"
                                            @input="draft[n.id][c.key] = clampScore(($event.target as HTMLInputElement).value, c.max)"
                                            class="w-16 rounded-lg border px-2 py-1 text-sm font-bold text-right tabular-nums bg-background focus:outline-none focus:ring-2"
                                            :class="scoreColor(draft[n.id][c.key], c.max)"
                                        />
                                        <span class="text-xs text-muted-foreground font-normal">/ {{ c.max }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 mt-3">
                                <span v-if="savedId === n.id" class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                                    <CheckCircle2 class="h-3.5 w-3.5" /> Saved
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3 py-1.5 transition-colors disabled:opacity-60"
                                    :disabled="savingId === n.id"
                                    @click="saveRating(n)"
                                >
                                    <Save class="h-3.5 w-3.5" /> {{ savingId === n.id ? 'Saving…' : 'Save My Rating' }}
                                </button>
                            </div>
                        </div>

                        </div>

                        <!-- My Grand Total -->
                        <div class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-5 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70">My Grand Total</p>
                            <template v-if="myGrandTotal(n) !== null">
                                <p class="text-2xl font-extrabold">{{ myGrandTotal(n) }} <span class="text-sm font-semibold text-white/70">/ {{ GRAND_MAX }}</span></p>
                                <p class="text-[11px] text-white/70">Requirements {{ n.assessment?.requirements_total }}/{{ REQUIREMENTS_MAX }} + My Interview {{ n.my_rating?.total }}/{{ INTERVIEW_MAX }}</p>
                            </template>
                            <p v-else class="text-xs text-white/70 mt-1">
                                Appears once the admin encodes Requirements and you save your interview rating.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
