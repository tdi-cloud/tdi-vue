<script setup lang="ts">
import PublicFormShell from '@/components/public-forms/PublicFormShell.vue';
import SurveyQuestion from '@/components/public-forms/SurveyQuestion.vue';
import { useFormDraft } from '@/composables/useFormDraft';
import { suggestionFrom } from '@/lib/http';
import {
    answerLines,
    answerProblem,
    emptyAnswer,
    pickedOther,
    type AnswerValue,
    type EmployeeMatch,
    type PublicFormSummary,
    type SurveyQuestion as Question,
    type SurveySection,
} from '@/lib/publicForms';
import { registrationTheme } from '@/lib/registrationThemes';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    CalendarClock,
    Check,
    ClipboardList,
    Clock,
    Eye,
    IdCard,
    Loader2,
    Mail,
    Search,
    ShieldCheck,
    UserSearch,
} from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';

/**
 * Public (no login) form, e.g. the Learning and Development Survey. Like the
 * public registration form: find your name in the employee list, add your
 * email address, answer the questions section by section, review, submit.
 */
const props = defineProps<{
    form: PublicFormSummary;
    sections: SurveySection[];
    questions: Question[];
    otherValue: string;
    /** The form's background picture; null for a plain page. */
    backgroundUrl: string | null;
    /** The wide banner picture above the title (Settings → Header Banner Image); null when the form has none. */
    headerImageUrl: string | null;
    /** Set when an admin opens the form with ?preview=1; closedReason says whether it is closed to the public. */
    preview: { closedReason: string | null } | null;
}>();

const theme = registrationTheme('blue');
const inputClass = `w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:ring-2 ${theme.focus}`;

const page = usePage();
const serverErrors = computed(() => (page.props.errors as Record<string, string>) ?? {});

// An admin previewing a form that isn't open: everything works except submitting.
const isClosedPreview = computed(() => !!props.preview?.closedReason);

/* ── Respondent and answers ───────────────────────────────────────────────── */

const respondent = reactive({
    selected: null as EmployeeMatch | null,
    email: '',
    consent: false,
});

const answers = reactive<Record<string, AnswerValue>>({});
const answersOther = reactive<Record<string, string>>({});
for (const question of props.questions) {
    answers[question.key] = emptyAnswer(question);
    answersOther[question.key] = '';
}

// Hidden from people; only bots fill it in.
const honeypot = ref('');

// Autosave so the answers survive an accidental reload or a lost connection.
const respondentDraft = useFormDraft(`public-form:${props.form.slug}:respondent`, respondent);
const answersDraft = useFormDraft(`public-form:${props.form.slug}:answers`, answers, { notify: false });
const answersOtherDraft = useFormDraft(`public-form:${props.form.slug}:answers-other`, answersOther, { notify: false });

/**
 * Sections the selected employee answers (e.g. LinkedIn Learning only for
 * SG 18 and above). Before a name is picked, the sections everyone answers.
 */
const sectionKeys = computed(
    () => respondent.selected?.sections ?? props.sections.filter((section) => !section.audience).map((section) => section.key),
);
const activeSections = computed(() => props.sections.filter((section) => sectionKeys.value.includes(section.key)));
const activeQuestions = computed(() => props.questions.filter((question) => sectionKeys.value.includes(question.section)));
const questionNumbers = computed(() => new Map(activeQuestions.value.map((question, index) => [question.key, index + 1])));

function questionsIn(sectionKey: string): Question[] {
    return activeQuestions.value.filter((question) => question.section === sectionKey);
}

/* ── Employee search ──────────────────────────────────────────────────────── */

const MIN_SEARCH_LENGTH = 3;
const searchQuery = ref('');
const searchResults = ref<EmployeeMatch[]>([]);
const searchSuggestion = ref<string | null>(null);
const searching = ref(false);
const searched = ref(false);
// A failed request (offline, server down) must not read as "No employee found".
const searchFailed = ref(false);
const searchInput = ref<HTMLInputElement | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let latestSearch = 0;

watch(searchQuery, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searched.value = false;
    searchFailed.value = false;
    if (value.trim().length < MIN_SEARCH_LENGTH) {
        searchResults.value = [];
        searchSuggestion.value = null;
        return;
    }
    searchTimer = setTimeout(runSearch, 300);
});

async function runSearch(): Promise<void> {
    // Only the newest search updates the list, even if an older one answers last.
    const searchId = ++latestSearch;
    searching.value = true;
    searchFailed.value = false;
    try {
        const response = await axios.get<EmployeeMatch[]>(route('public-forms.search', props.form.slug), {
            params: { q: searchQuery.value.trim(), ...(props.preview ? { preview: 1 } : {}) },
            silentErrors: true,
        });
        if (searchId !== latestSearch) return;
        searchResults.value = response.data;
        searchSuggestion.value = suggestionFrom(response);
    } catch {
        if (searchId !== latestSearch) return;
        searchResults.value = [];
        searchSuggestion.value = null;
        searchFailed.value = true;
    } finally {
        if (searchId === latestSearch) {
            searching.value = false;
            searched.value = true;
        }
    }
}

// Once a name is picked, the results close so only the chosen name shows.
function selectEmployee(employee: EmployeeMatch): void {
    if (employee.already_answered) return;

    respondent.selected = employee;
    clientErrors.value = {};

    if (searchTimer) clearTimeout(searchTimer);
    searchResults.value = [];
    searchSuggestion.value = null;
    searched.value = false;
}

// Brings the results back so another name can be picked.
async function changeSelection(): Promise<void> {
    respondent.selected = null;
    if (searchQuery.value.trim().length >= MIN_SEARCH_LENGTH) runSearch();

    await nextTick();
    searchInput.value?.focus();
}

/* ── Steps ────────────────────────────────────────────────────────────────── */

interface Step {
    key: string;
    label: string;
    kind: 'find' | 'details' | 'section' | 'review';
    section?: SurveySection;
}

const steps = computed<Step[]>(() => [
    { key: 'find', label: 'Find Name', kind: 'find' },
    { key: 'details', label: 'Your Details', kind: 'details' },
    ...activeSections.value.map((section) => ({ key: `section:${section.key}`, label: section.step_label, kind: 'section' as const, section })),
    { key: 'review', label: 'Review', kind: 'review' },
]);

const currentStep = ref(0);
const current = computed<Step>(() => steps.value[currentStep.value] ?? steps.value[0]);
const isLastStep = computed(() => currentStep.value === steps.value.length - 1);
const progressPct = computed(() => ((currentStep.value + 1) / steps.value.length) * 100);

function goToStep(key: string): void {
    const index = steps.value.findIndex((step) => step.key === key);
    if (index !== -1) currentStep.value = index;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// On phones not every step fits, so the step bar scrolls sideways to keep the current one in view.
const stepList = ref<HTMLOListElement | null>(null);

watch(currentStep, async () => {
    await nextTick();
    const list = stepList.value;
    const active = list?.querySelector<HTMLElement>('[aria-current="step"]');
    if (list && active) {
        list.scrollTo({ left: active.offsetLeft - (list.clientWidth - active.offsetWidth) / 2, behavior: 'smooth' });
    }
});

/* ── Validation ───────────────────────────────────────────────────────────── */

const clientErrors = ref<Record<string, string>>({});

/** The error for a field, including server errors on its items (e.g. answers.priority_areas.0). */
function fieldError(key: string): string | undefined {
    if (clientErrors.value[key]) return clientErrors.value[key];
    if (serverErrors.value[key]) return serverErrors.value[key];
    const nested = Object.keys(serverErrors.value).find((errorKey) => errorKey.startsWith(`${key}.`));
    return nested ? serverErrors.value[nested] : undefined;
}

function validateStep(step: Step): boolean {
    const errors: Record<string, string> = {};

    if (step.kind === 'find') {
        if (!respondent.selected) {
            errors.empcode = 'Search for your name and select it.';
        } else if (respondent.selected.already_answered) {
            errors.empcode = 'You have already answered this form.';
        }
    }

    if (step.kind === 'details') {
        if (!respondent.email.trim()) {
            errors.email = 'Email address is required.';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(respondent.email.trim())) {
            errors.email = 'Please enter a valid email address.';
        }
    }

    if (step.kind === 'section' && step.section) {
        for (const question of questionsIn(step.section.key)) {
            const problem = answerProblem(question, answers[question.key]);
            if (problem) {
                errors[`answers.${question.key}`] = problem;
            }
            if (question.allow_other && pickedOther(answers[question.key], props.otherValue) && !answersOther[question.key].trim()) {
                errors[`answers_other.${question.key}`] = 'Please type your answer for "Other".';
            }
        }
    }

    if (step.kind === 'review' && !respondent.consent) {
        errors.data_privacy_consent = 'Please agree to the data privacy statement to submit your answers.';
    }

    clientErrors.value = errors;

    if (Object.keys(errors).length > 0) {
        scrollToFirstError();
        return false;
    }
    return true;
}

async function scrollToFirstError(): Promise<void> {
    await nextTick();
    const target = document.querySelector('[data-has-error="true"]');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function next(): void {
    if (!validateStep(current.value)) return;
    if (currentStep.value < steps.value.length - 1) currentStep.value++;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function back(): void {
    clientErrors.value = {};
    if (currentStep.value > 0) currentStep.value--;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Server errors (e.g. "already answered") jump back to the step that holds the field.
function stepKeyForError(errorKey: string): string {
    if (errorKey === 'email') return 'details';
    if (errorKey === 'data_privacy_consent') return 'review';

    const answerKey = errorKey.match(/^answers(?:_other)?\.([^.]+)/)?.[1];
    const question = answerKey ? props.questions.find((candidate) => candidate.key === answerKey) : undefined;
    return question ? `section:${question.section}` : 'find';
}

watch(serverErrors, (errors) => {
    const keys = Object.keys(errors);
    if (!keys.length) return;
    const order = steps.value.map((step) => step.key);
    const target = keys.map(stepKeyForError).sort((a, b) => order.indexOf(a) - order.indexOf(b))[0];
    goToStep(target);
});

/* ── Review ───────────────────────────────────────────────────────────────── */

interface DetailRow {
    label: string;
    value: string;
}

/** The picked employee's details from the employee list. */
const employeeRows = computed<DetailRow[]>(() => {
    const employee = respondent.selected;
    if (!employee) return [];

    return [
        { label: 'Name', value: employee.name },
        { label: 'Employee Code', value: employee.empcode },
        { label: 'Position', value: employee.position },
        { label: 'Office / Division', value: employee.office_division },
    ].filter((row): row is DetailRow => Boolean(row.value));
});

const detailRows = computed<DetailRow[]>(() => [
    ...employeeRows.value,
    ...(respondent.email.trim() ? [{ label: 'Email', value: respondent.email.trim() }] : []),
]);

function reviewLines(question: Question): string[] {
    return answerLines(question, answers[question.key], answersOther[question.key], props.otherValue);
}

/* ── Submit ───────────────────────────────────────────────────────────────── */

const processing = ref(false);

function submit(): void {
    if (!isLastStep.value) {
        next();
        return;
    }
    if (!validateStep(current.value) || !respondent.selected || isClosedPreview.value) return;

    // Only the questions this person is asked; "Other" text only where "Other" was picked.
    const sentAnswers = Object.fromEntries(activeQuestions.value.map((question) => [question.key, answers[question.key]]));
    const sentOther = Object.fromEntries(
        activeQuestions.value
            .filter((question) => question.allow_other && pickedOther(answers[question.key], props.otherValue))
            .map((question) => [question.key, answersOther[question.key].trim()]),
    );

    processing.value = true;
    router.post(
        route('public-forms.submit', props.form.slug),
        {
            empcode: respondent.selected.empcode,
            email: respondent.email.trim(),
            data_privacy_consent: respondent.consent,
            answers: JSON.parse(JSON.stringify(sentAnswers)),
            answers_other: sentOther,
            website: honeypot.value,
        },
        {
            onSuccess: () => {
                respondentDraft.clearDraft();
                answersDraft.clearDraft();
                answersOtherDraft.clearDraft();
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="form.title" />

    <PublicFormShell :background-url="backgroundUrl">
        <!-- Admin preview notice -->
        <p v-if="preview" class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
            <Eye class="mt-0.5 h-4 w-4 shrink-0" />
            <span v-if="isClosedPreview">
                Preview only. This form isn't open to the public, so submitting is turned off. Open it in the form's Settings to take responses.
            </span>
            <span v-else>Preview. This form is open, so anything you submit here is saved as a real response.</span>
        </p>

        <!-- Header picture: its own card above the title, never behind it -->
        <div v-if="headerImageUrl" class="overflow-hidden rounded-2xl bg-white shadow-md">
            <img :src="headerImageUrl" alt="" decoding="async" class="block aspect-[16/5] w-full object-cover object-center" />
        </div>

        <!-- Banner -->
        <div class="overflow-hidden rounded-2xl shadow-md">
            <div class="px-6 pb-5 pt-6" :class="theme.header">
                <p v-if="form.kicker" class="mb-1 text-xs font-semibold uppercase tracking-widest" :class="theme.kicker">{{ form.kicker }}</p>
                <h1 class="text-xl font-extrabold leading-tight text-white sm:text-2xl">{{ form.title }}</h1>
                <p v-if="form.description" class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-white/85">{{ form.description }}</p>
            </div>
            <div class="space-y-2 border-t-4 bg-white px-6 py-3 text-xs text-gray-500" :class="theme.strip">
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    <span class="flex items-center gap-1"><Clock class="h-3.5 w-3.5" /> About {{ form.estimated_minutes }} minutes</span>
                    <span v-if="form.closes_at_label" class="flex items-center gap-1">
                        <CalendarClock class="h-3.5 w-3.5" /> Open until {{ form.closes_at_label }}
                    </span>
                    <span class="flex items-center gap-1"><Mail class="h-3.5 w-3.5" /> Confirmation sent by email</span>
                </div>
                <p>
                    Start by finding your name in the employee list. Each employee can answer once. Questions marked with an asterisk (<span
                        class="font-bold text-red-500"
                        >*</span
                    >) are required.
                </p>
            </div>
        </div>

        <!-- Progress -->
        <div class="rounded-2xl bg-white px-5 py-4 shadow-sm">
            <ol ref="stepList" class="relative -mx-1 mb-3 flex items-start justify-between gap-1 overflow-x-auto px-1 pb-1">
                <li
                    v-for="(step, index) in steps"
                    :key="step.key"
                    class="flex w-16 shrink-0 flex-col items-center"
                    :aria-current="index === currentStep ? 'step' : undefined"
                >
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-all"
                        :class="
                            index < currentStep
                                ? `${theme.accentBg} text-white`
                                : index === currentStep
                                  ? `${theme.accentBg} text-white ring-4 ${theme.activeRing}`
                                  : 'bg-gray-200 text-gray-500'
                        "
                    >
                        <Check v-if="index < currentStep" class="h-4 w-4" />
                        <span v-else>{{ index + 1 }}</span>
                    </div>
                    <span
                        class="mt-1.5 whitespace-nowrap text-center text-[10px] leading-tight"
                        :class="index === currentStep ? `font-semibold ${theme.accentText}` : 'text-gray-400'"
                    >
                        {{ step.label }}
                    </span>
                </li>
            </ol>

            <div class="h-1.5 overflow-hidden rounded-full bg-gray-200">
                <div class="h-full rounded-full transition-all duration-300" :class="theme.accentBg" :style="{ width: progressPct + '%' }"></div>
            </div>
            <p class="mt-2 text-center text-xs text-gray-500">Step {{ currentStep + 1 }} of {{ steps.length }}</p>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="submit">
            <!-- Honeypot: hidden from people and screen readers -->
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label>
                    Website
                    <input v-model="honeypot" type="text" name="website" tabindex="-1" autocomplete="off" />
                </label>
            </div>

            <!-- Step: find your name -->
            <section v-show="current.kind === 'find'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                <div class="border-l-4 px-5 py-3" :class="theme.sectionHeader">
                    <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                        <UserSearch class="h-4 w-4" :class="theme.accentText" /> Find Your Name
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500">Search the employee list by your name or employee code.</p>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div>
                        <label for="employee-search" class="mb-1 block text-xs font-semibold text-gray-600">
                            Your Name or Employee Code <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                id="employee-search"
                                ref="searchInput"
                                v-model="searchQuery"
                                type="search"
                                autocomplete="off"
                                placeholder="e.g. Dela Cruz, Juan"
                                :class="[inputClass, 'pl-9']"
                            />
                            <Loader2 v-if="searching" class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-gray-400" />
                        </div>
                        <p v-if="searchQuery.trim().length > 0 && searchQuery.trim().length < MIN_SEARCH_LENGTH" class="mt-1 text-xs text-gray-400">
                            Type at least {{ MIN_SEARCH_LENGTH }} letters.
                        </p>
                        <p v-if="fieldError('empcode')" class="mt-1 text-xs text-red-500" data-has-error="true">{{ fieldError('empcode') }}</p>
                    </div>

                    <!-- Results -->
                    <div v-if="searchResults.length" class="divide-y overflow-hidden rounded-xl border border-gray-200">
                        <button
                            v-for="employee in searchResults"
                            :key="employee.empcode"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 border-l-4 border-transparent px-4 py-2.5 text-left transition"
                            :class="employee.already_answered ? 'cursor-not-allowed bg-gray-50' : 'hover:bg-gray-50'"
                            :disabled="employee.already_answered"
                            @click="selectEmployee(employee)"
                        >
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-sm font-semibold"
                                    :class="employee.already_answered ? 'text-gray-400' : 'text-gray-800'"
                                >
                                    {{ employee.name }}
                                </span>
                                <span class="block truncate text-xs text-gray-500">
                                    {{ employee.empcode }}<template v-if="employee.office_division"> · {{ employee.office_division }}</template>
                                </span>
                            </span>
                            <span
                                v-if="employee.already_answered"
                                class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700"
                            >
                                Already answered
                            </span>
                        </button>
                    </div>

                    <div
                        v-else-if="searchFailed && !searching"
                        class="rounded-xl border border-dashed border-amber-200 bg-amber-50 px-4 py-4 text-center text-xs text-amber-800"
                        role="alert"
                    >
                        <p class="font-semibold">We couldn't search the employee list right now.</p>
                        <p class="mt-1">Check your internet connection, then try again.</p>
                        <button type="button" class="mt-1 font-semibold hover:underline" :class="theme.accentText" @click="runSearch">
                            Try again
                        </button>
                    </div>

                    <div
                        v-else-if="searched && !searching && searchQuery.trim().length >= MIN_SEARCH_LENGTH"
                        class="rounded-xl border border-dashed border-gray-200 px-4 py-4 text-center text-xs text-gray-500"
                    >
                        <p>
                            No employee found for “<span class="font-semibold text-gray-700">{{ searchQuery.trim() }}</span
                            >”.
                        </p>
                        <button
                            v-if="searchSuggestion"
                            type="button"
                            class="mt-1 font-semibold hover:underline"
                            :class="theme.accentText"
                            @click="searchQuery = searchSuggestion"
                        >
                            Did you mean “{{ searchSuggestion }}”?
                        </button>
                        <p class="mt-1">Check the spelling, or try your last name only.</p>
                    </div>

                    <!-- Selected -->
                    <div v-if="respondent.selected" class="flex items-center gap-3 rounded-xl border px-4 py-3" :class="theme.selected">
                        <BadgeCheck class="h-5 w-5 shrink-0" :class="theme.accentText" />
                        <div class="min-w-0 flex-1 text-xs text-gray-600">
                            <p class="truncate text-sm font-bold text-gray-800">{{ respondent.selected.name }}</p>
                            <p class="truncate">
                                {{ respondent.selected.empcode
                                }}<template v-if="respondent.selected.office_division"> · {{ respondent.selected.office_division }}</template>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-xs font-semibold hover:underline"
                            :class="theme.accentText"
                            @click="changeSelection"
                        >
                            Change
                        </button>
                    </div>

                    <p class="border-t pt-4 text-center text-xs text-gray-500">
                        Can't find your name? The list comes from HR's employee records. Please get in touch with the TESDA Development Institute.
                    </p>
                </div>
            </section>

            <!-- Step: your details -->
            <section v-show="current.kind === 'details'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                <div class="border-l-4 px-5 py-3" :class="theme.sectionHeader">
                    <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                        <IdCard class="h-4 w-4" :class="theme.accentText" /> Your Details
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500">From the employee list. Add the email address where we'll send your confirmation.</p>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <dl v-if="respondent.selected" class="divide-y rounded-xl border border-gray-200 text-sm">
                        <div v-for="row in employeeRows" :key="row.label" class="flex justify-between gap-4 px-4 py-2.5">
                            <dt class="shrink-0 text-xs font-semibold text-gray-500">{{ row.label }}</dt>
                            <dd class="min-w-0 break-words text-right text-gray-800">{{ row.value }}</dd>
                        </div>
                    </dl>
                    <p class="text-xs text-gray-500">
                        Not you?
                        <button type="button" class="font-semibold hover:underline" :class="theme.accentText" @click="goToStep('find')">
                            Search again
                        </button>
                    </p>

                    <div :data-has-error="fieldError('email') ? 'true' : undefined">
                        <label for="field-email" class="mb-1 block text-xs font-semibold text-gray-600">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="field-email"
                            v-model="respondent.email"
                            type="email"
                            autocomplete="email"
                            placeholder="e.g. juan.delacruz@tesda.gov.ph"
                            :class="inputClass"
                            :aria-invalid="fieldError('email') ? 'true' : undefined"
                        />
                        <p class="mt-1 text-xs text-gray-400">We'll send a confirmation with a copy of your answers to this address.</p>
                        <p v-if="fieldError('email')" class="mt-1 text-xs text-red-500">{{ fieldError('email') }}</p>
                    </div>
                </div>
            </section>

            <!-- Steps: one per section of questions -->
            <section
                v-for="section in activeSections"
                v-show="current.key === `section:${section.key}`"
                :key="section.key"
                class="overflow-hidden rounded-2xl bg-white shadow-sm"
            >
                <div class="border-l-4 px-5 py-3" :class="theme.sectionHeader">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                            <ClipboardList class="h-4 w-4" :class="theme.accentText" /> {{ section.title }}
                        </h2>
                        <span
                            v-if="section.audience"
                            class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800"
                        >
                            {{ section.audience }}
                        </span>
                    </div>
                    <p v-if="section.description" class="mt-0.5 text-xs text-gray-500">{{ section.description }}</p>
                </div>

                <div class="divide-y divide-gray-100 px-5">
                    <div
                        v-for="question in questionsIn(section.key)"
                        :key="question.key"
                        class="py-5"
                        :data-has-error="fieldError(`answers.${question.key}`) || fieldError(`answers_other.${question.key}`) ? 'true' : undefined"
                    >
                        <SurveyQuestion
                            v-model:value="answers[question.key]"
                            v-model:other="answersOther[question.key]"
                            :question="question"
                            :number="questionNumbers.get(question.key) ?? 0"
                            :other-value="otherValue"
                            :theme="theme"
                            :error="fieldError(`answers.${question.key}`)"
                            :other-error="fieldError(`answers_other.${question.key}`)"
                        />
                    </div>
                </div>
            </section>

            <!-- Last step: review and submit -->
            <section v-show="current.kind === 'review'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                <div class="border-l-4 px-5 py-3" :class="theme.sectionHeader">
                    <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                        <ShieldCheck class="h-4 w-4" :class="theme.accentText" /> Review and Submit
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500">Check your answers before you submit. You can answer this form only once.</p>
                </div>

                <div class="space-y-5 px-5 py-5">
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">Your details</h3>
                            <button
                                type="button"
                                class="text-xs font-semibold hover:underline"
                                :class="theme.accentText"
                                @click="goToStep('details')"
                            >
                                Edit
                            </button>
                        </div>
                        <dl class="divide-y rounded-xl border border-gray-200 text-sm">
                            <div v-for="row in detailRows" :key="row.label" class="flex justify-between gap-4 px-4 py-2.5">
                                <dt class="shrink-0 text-xs font-semibold text-gray-500">{{ row.label }}</dt>
                                <dd class="min-w-0 break-words text-right text-gray-800">{{ row.value }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-for="section in activeSections" :key="section.key">
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ section.title }}</h3>
                            <button
                                type="button"
                                class="text-xs font-semibold hover:underline"
                                :class="theme.accentText"
                                @click="goToStep(`section:${section.key}`)"
                            >
                                Edit
                            </button>
                        </div>
                        <dl class="divide-y rounded-xl border border-gray-200 text-sm">
                            <div v-for="question in questionsIn(section.key)" :key="question.key" class="px-4 py-2.5">
                                <dt class="text-xs text-gray-500">{{ questionNumbers.get(question.key) }}. {{ question.question }}</dt>
                                <dd class="mt-1 text-gray-800">
                                    <template v-if="reviewLines(question).length">
                                        <span v-for="line in reviewLines(question)" :key="line" class="block break-words font-semibold">{{
                                            line
                                        }}</span>
                                    </template>
                                    <span v-else class="text-gray-400">No answer</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <label
                        class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600"
                        :data-has-error="fieldError('data_privacy_consent') ? 'true' : undefined"
                    >
                        <input v-model="respondent.consent" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 accent-blue-600" />
                        <span>
                            I agree that the TESDA Development Institute may collect and use the information in this form to plan its learning and
                            development programs and to send me a confirmation, in line with the Data Privacy Act of 2012 (Republic Act No. 10173).
                            <span class="text-red-500">*</span>
                        </span>
                    </label>
                    <p v-if="fieldError('data_privacy_consent')" class="-mt-3 text-xs text-red-500">{{ fieldError('data_privacy_consent') }}</p>
                </div>
            </section>

            <!-- Navigation -->
            <div class="rounded-2xl bg-white px-5 py-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <button
                        v-if="currentStep > 0"
                        type="button"
                        class="flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        @click="back"
                    >
                        <ArrowLeft class="h-4 w-4" /> Back
                    </button>

                    <button
                        v-if="!isLastStep"
                        type="button"
                        class="ml-auto flex items-center justify-center gap-1.5 rounded-xl px-6 py-3 text-sm font-extrabold text-white transition"
                        :class="[theme.accentBg, theme.accentHover]"
                        @click="next"
                    >
                        Next <ArrowRight class="h-4 w-4" />
                    </button>

                    <button
                        v-else
                        type="submit"
                        :disabled="processing || isClosedPreview"
                        class="ml-auto flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-extrabold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
                        :class="[theme.accentBg, theme.accentHover]"
                    >
                        <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                        {{ processing ? 'Submitting…' : 'Submit Answers' }}
                    </button>
                </div>
                <p v-if="isLastStep" class="mt-3 flex items-center justify-center gap-1.5 text-center text-xs text-gray-400">
                    <AlertCircle class="h-3.5 w-3.5" /> After you submit, we'll email you a confirmation with your reference number.
                </p>
            </div>
        </form>
    </PublicFormShell>
</template>
