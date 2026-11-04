<script setup lang="ts">
import { useFormDraft } from '@/composables/useFormDraft';
import { suggestionFrom } from '@/lib/http';
import { registrationTheme } from '@/lib/registrationThemes';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    CalendarDays,
    Check,
    ChevronDown,
    ClipboardList,
    Clock,
    Eye,
    IdCard,
    Loader2,
    Mail,
    MapPin,
    Search,
    ShieldCheck,
    UserPen,
    UserSearch,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

interface PublicForm {
    slug: string;
    title: string;
    intro_text: string | null;
    theme: string;
    closes_at_label: string | null;
    batch: {
        batch: string;
        date_start: string | null;
        date_end: string | null;
        time_start: string | null;
        time_end: string | null;
        modality: string | null;
        venue: string | null;
    };
    program: { title: string | null; cover_url: string | null };
}

interface Question {
    id: number;
    label: string;
    help_text: string | null;
    type: 'text' | 'textarea' | 'radio' | 'checkbox' | 'select';
    options: string[] | null;
    allow_other: boolean;
    is_required: boolean;
}

interface EmployeeResult {
    empcode: string;
    name: string;
    office_division: string | null;
}

const props = defineProps<{
    form: PublicForm;
    questions: Question[];
    fieldOptions: { region: string[]; office_division: Record<string, string[]>; sex: string[]; plantilla_status: string[] };
    backgroundUrl: string | null;
    preview: { closedReason: string | null } | null;
}>();

onMounted(() => {
    document.documentElement.classList.remove('dark');
});

const theme = computed(() => registrationTheme(props.form.theme));
const inputClass = computed(
    () =>
        `w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:ring-2 ${theme.value.focus}`,
);
const selectClass = computed(
    () =>
        `w-full appearance-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-10 text-sm text-gray-900 outline-none transition focus:ring-2 ${theme.value.focus}`,
);

const page = usePage();
const serverErrors = computed(() => (page.props.errors as Record<string, string>) ?? {});

const isClosedPreview = computed(() => !!props.preview?.closedReason);

const formatDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '');

const batchDateLabel = computed(() => {
    const start = formatDate(props.form.batch.date_start);
    const end = formatDate(props.form.batch.date_end);
    if (!start) return '';
    if (!end || end === start) return start;
    return `${start} – ${end}`;
});

function formatTime(t: string | null): string {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    if (Number.isNaN(h)) return t;
    const suffix = h >= 12 ? 'PM' : 'AM';
    return `${((h + 11) % 12) + 1}:${String(m ?? 0).padStart(2, '0')} ${suffix}`;
}

const timeLabel = computed(() => {
    const start = formatTime(props.form.batch.time_start);
    const end = formatTime(props.form.batch.time_end);
    return start && end ? `${start} – ${end}` : start;
});

/* ── Registrant ───────────────────────────────────────────────────────────── */

const registrant = reactive({
    source: 'listed' as 'listed' | 'manual',
    selected: null as EmployeeResult | null,
    empcode: '',
    lastname: '',
    firstname: '',
    mi: '',
    position: '',
    sg: '',
    plantilla_status: '',
    sex: '',
    region: '',
    office_division: '',
    email: '',
    contact_number: '',
    consent: false,
});

// Sent in place of an option when "Other" is picked; the typed text goes in answersOther.
const OTHER_VALUE = '__other__';

const answers = reactive<Record<number, any>>({});
const answersOther = reactive<Record<number, string>>({});
for (const question of props.questions) {
    answers[question.id] = question.type === 'checkbox' ? [] : '';
    answersOther[question.id] = '';
}

// Typing an "Other" answer ticks its checkbox, so it isn't lost.
function checkOther(questionId: number) {
    const picked: string[] = answers[questionId];
    if (!picked.includes(OTHER_VALUE)) picked.push(OTHER_VALUE);
}

function pickedOther(question: Question): boolean {
    const value = answers[question.id];
    return Array.isArray(value) ? value.includes(OTHER_VALUE) : value === OTHER_VALUE;
}

// Hidden from people; only bots fill it in.
const honeypot = ref('');

// Autosave so the form survives an accidental reload or a lost connection.
const registrantDraft = useFormDraft(`registration:${props.form.slug}:registrant`, registrant);
const answersDraft = useFormDraft(`registration:${props.form.slug}:answers`, answers, { notify: false });
const answersOtherDraft = useFormDraft(`registration:${props.form.slug}:answers-other`, answersOther, { notify: false });

const isManual = computed(() => registrant.source === 'manual');

type ManualFieldKey = 'empcode' | 'lastname' | 'firstname' | 'mi' | 'position' | 'sg' | 'plantilla_status' | 'sex' | 'region' | 'office_division';

// Office, location, section and unit aren't asked: they follow from the office/division.
const MANUAL_FIELDS: {
    key: ManualFieldKey;
    label: string;
    required: boolean;
    options?: keyof typeof props.fieldOptions;
    placeholder?: string;
    hint?: string;
}[] = [
    { key: 'empcode', label: 'Employee Code (EMPCODE)', required: true, placeholder: 'e.g. 2026-1234' },
    { key: 'lastname', label: 'Last Name', required: true },
    { key: 'firstname', label: 'First Name', required: true },
    { key: 'mi', label: 'Middle Initial', required: false },
    {
        key: 'position',
        label: 'Position',
        required: true,
        placeholder: 'e.g. Administrative Assistant III',
        hint: 'Write your full position title. Do not abbreviate (e.g. "Administrative Assistant III", not "AA III").',
    },
    { key: 'sg', label: 'Salary Grade', required: false },
    { key: 'plantilla_status', label: 'Plantilla Status', required: true, options: 'plantilla_status' },
    { key: 'sex', label: 'Sex', required: true, options: 'sex' },
    { key: 'region', label: 'Region', required: true, options: 'region' },
    { key: 'office_division', label: 'Office / Division', required: true, options: 'office_division' },
];

/**
 * Dropdown choices for a field, or null for a plain text box (when the
 * employee list has nothing to offer). Office/divisions follow the region.
 */
function choicesFor(field: (typeof MANUAL_FIELDS)[number]): string[] | null {
    if (field.options === 'office_division') {
        const byRegion = props.fieldOptions.office_division;
        return Object.keys(byRegion).length ? (byRegion[registrant.region] ?? []) : null;
    }
    if (!field.options) return null;

    const choices = props.fieldOptions[field.options];
    return choices.length ? choices : null;
}

// A different region has different office/divisions, so clear one that no longer fits.
watch(
    () => registrant.region,
    () => {
        const divisions = choicesFor(MANUAL_FIELDS.find((f) => f.key === 'office_division')!);
        if (divisions && !divisions.includes(registrant.office_division)) {
            registrant.office_division = '';
        }
    },
);

const displayName = computed(() => {
    if (!isManual.value) return registrant.selected?.name ?? '';
    const mi = registrant.mi.trim() ? ` ${registrant.mi.trim().replace(/\.$/, '')}.` : '';
    return `${registrant.firstname.trim()}${mi} ${registrant.lastname.trim()}`.trim();
});

/* ── Employee search ──────────────────────────────────────────────────────── */

const MIN_SEARCH_LENGTH = 3;
const searchQuery = ref('');
const searchResults = ref<EmployeeResult[]>([]);
const searchSuggestion = ref<string | null>(null);
const searching = ref(false);
const searched = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searched.value = false;
    if (value.trim().length < MIN_SEARCH_LENGTH) {
        searchResults.value = [];
        searchSuggestion.value = null;
        return;
    }
    searchTimer = setTimeout(runSearch, 300);
});

async function runSearch() {
    searching.value = true;
    try {
        const response = await axios.get(route('join.search', props.form.slug), {
            params: { q: searchQuery.value.trim(), ...(props.preview ? { preview: 1 } : {}) },
            silentErrors: true,
        });
        searchResults.value = response.data;
        searchSuggestion.value = suggestionFrom(response);
    } catch {
        searchResults.value = [];
        searchSuggestion.value = null;
    } finally {
        searching.value = false;
        searched.value = true;
    }
}

const searchInput = ref<HTMLInputElement | null>(null);

// Once a name is picked, the results list closes so only the chosen name shows.
function selectEmployee(employee: EmployeeResult) {
    registrant.source = 'listed';
    registrant.selected = employee;
    clientErrors.value = {};

    if (searchTimer) clearTimeout(searchTimer);
    searchResults.value = [];
    searchSuggestion.value = null;
    searched.value = false;
}

// Brings the results back so another name can be picked.
async function changeSelection() {
    registrant.selected = null;
    if (searchQuery.value.trim().length >= MIN_SEARCH_LENGTH) runSearch();

    await nextTick();
    searchInput.value?.focus();
}

function chooseManualEntry() {
    registrant.source = 'manual';
    registrant.selected = null;
    clientErrors.value = {};
    next();
}

function backToSearch() {
    registrant.source = 'listed';
    currentStep.value = 0;
}

/* ── Steps ────────────────────────────────────────────────────────────────── */

interface Step {
    key: 'find' | 'employee' | 'contact' | 'questions' | 'review';
    label: string;
}

const steps = computed<Step[]>(() => [
    { key: 'find', label: 'Find Name' },
    { key: 'employee', label: 'Employee Info' },
    { key: 'contact', label: 'Contact' },
    ...(props.questions.length ? [{ key: 'questions' as const, label: 'Others' }] : []),
    { key: 'review', label: 'Review' },
]);

const currentStep = ref(0);
const currentKey = computed<Step['key']>(() => steps.value[currentStep.value]?.key ?? 'find');
const isLastStep = computed(() => currentStep.value === steps.value.length - 1);
const progressPct = computed(() => ((currentStep.value + 1) / steps.value.length) * 100);

function goToStep(key: Step['key']) {
    const index = steps.value.findIndex((s) => s.key === key);
    if (index !== -1) currentStep.value = index;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── Validation ───────────────────────────────────────────────────────────── */

const clientErrors = ref<Record<string, string>>({});

function fieldError(key: string): string | undefined {
    return clientErrors.value[key] ?? serverErrors.value[key];
}

function isEmpty(value: any): boolean {
    if (Array.isArray(value)) return value.length === 0;
    return value === null || value === undefined || String(value).trim() === '';
}

function validateStep(stepKey: Step['key']): boolean {
    clientErrors.value = {};
    const errors: Record<string, string> = {};

    if (stepKey === 'find' && !isManual.value && !registrant.selected) {
        errors.empcode = 'Search for your name and select it, or choose "I\'m not in the list".';
    }

    if (stepKey === 'employee' && isManual.value) {
        for (const field of MANUAL_FIELDS) {
            if (field.required && isEmpty(registrant[field.key])) {
                errors[field.key] = `${field.label} is required.`;
            }
        }
    }

    if (stepKey === 'contact') {
        if (isEmpty(registrant.email)) {
            errors.email = 'Email address is required.';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(registrant.email)) {
            errors.email = 'Please enter a valid email address.';
        }

        if (isEmpty(registrant.contact_number)) {
            errors.contact_number = 'Contact number is required.';
        } else if (!/^[0-9+()\-\s]{7,20}$/.test(registrant.contact_number)) {
            errors.contact_number = 'Use digits only, for example 0917 123 4567.';
        }
    }

    if (stepKey === 'questions') {
        for (const question of props.questions) {
            if (question.is_required && isEmpty(answers[question.id])) {
                errors[`answers.${question.id}`] = 'This question is required.';
            }
            if (question.allow_other && pickedOther(question) && isEmpty(answersOther[question.id])) {
                errors[`answers_other.${question.id}`] = 'Please type your answer for "Other".';
            }
        }
    }

    if (stepKey === 'review' && !registrant.consent) {
        errors.data_privacy_consent = 'Please agree to the data privacy statement to submit your registration.';
    }

    clientErrors.value = errors;

    if (Object.keys(errors).length > 0) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return false;
    }
    return true;
}

function next() {
    if (!validateStep(currentKey.value)) return;
    if (currentStep.value < steps.value.length - 1) currentStep.value++;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function back() {
    clientErrors.value = {};
    if (currentStep.value > 0) currentStep.value--;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Server errors (e.g. "already registered") jump back to the step that holds the field.
function stepForErrorKey(key: string): Step['key'] {
    if (key === 'email' || key === 'contact_number') return 'contact';
    if (key.startsWith('answers')) return 'questions';
    if (key === 'data_privacy_consent') return 'review';
    if (key === 'empcode' && !isManual.value) return 'find';
    return isManual.value ? 'employee' : 'find';
}

watch(serverErrors, (errors) => {
    const keys = Object.keys(errors);
    if (!keys.length) return;
    const order = steps.value.map((s) => s.key);
    const target = keys.map(stepForErrorKey).sort((a, b) => order.indexOf(a) - order.indexOf(b))[0];
    goToStep(target);
});

/* ── Review ───────────────────────────────────────────────────────────────── */

const reviewRows = computed(() => {
    const rows: { label: string; value: string }[] = [{ label: 'Name', value: displayName.value }];

    if (isManual.value) {
        for (const field of MANUAL_FIELDS) {
            if (['lastname', 'firstname', 'mi'].includes(field.key)) continue;
            const value = String(registrant[field.key] ?? '').trim();
            if (value) rows.push({ label: field.label, value });
        }
    } else if (registrant.selected) {
        rows.push({ label: 'Employee Code', value: registrant.selected.empcode });
        if (registrant.selected.office_division) rows.push({ label: 'Office / Division', value: registrant.selected.office_division });
    }

    rows.push({ label: 'Email', value: registrant.email }, { label: 'Contact Number', value: registrant.contact_number });
    return rows;
});

function answerLabel(question: Question): string {
    const show = (choice: string) => (choice === OTHER_VALUE ? `Other: ${answersOther[question.id].trim()}` : choice);
    const value = answers[question.id];
    if (Array.isArray(value)) return value.length ? value.map(show).join(', ') : '—';
    return isEmpty(value) ? '—' : show(String(value));
}

function toggleCheckboxValue(target: string[], option: string) {
    const index = target.indexOf(option);
    if (index === -1) {
        target.push(option);
    } else {
        target.splice(index, 1);
    }
}

/* ── Submit ───────────────────────────────────────────────────────────────── */

const processing = ref(false);

function submit() {
    if (!isLastStep.value) {
        next();
        return;
    }
    if (!validateStep(currentKey.value) || isClosedPreview.value) return;

    const employeeFields = isManual.value
        ? Object.fromEntries(MANUAL_FIELDS.map((field) => [field.key, String(registrant[field.key] ?? '').trim() || null]))
        : { empcode: registrant.selected?.empcode ?? '' };

    processing.value = true;
    router.post(
        route('join.submit', props.form.slug),
        {
            source: registrant.source,
            ...employeeFields,
            email: registrant.email.trim(),
            contact_number: registrant.contact_number.trim(),
            answers: JSON.parse(JSON.stringify(answers)),
            answers_other: { ...answersOther },
            data_privacy_consent: registrant.consent,
            website: honeypot.value,
        },
        {
            onSuccess: () => {
                registrantDraft.clearDraft();
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
    <Head :title="`${form.title} — ${form.program.title ?? ''}`" />

    <div class="relative min-h-screen bg-gray-100 px-4 py-8 [color-scheme:light]">
        <!-- Background image (superadmin default via Homepage Images, or a per-batch override in Registration settings) -->
        <div v-if="backgroundUrl" class="fixed inset-0 bg-cover bg-fixed bg-center" :style="{ backgroundImage: `url(${backgroundUrl})` }"></div>
        <div v-if="backgroundUrl" class="fixed inset-0 bg-white/80"></div>

        <div class="relative mx-auto max-w-2xl space-y-4">
            <!-- Manager preview notice -->
            <p v-if="preview" class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                <Eye class="mt-0.5 h-4 w-4 shrink-0" />
                <span v-if="isClosedPreview">
                    Preview only. This form is closed to the public, so submitting is turned off. Open it in the Registration settings to accept
                    registrations.
                </span>
                <span v-else>Preview. This form is open, so anything you submit here is saved as a real registration.</span>
            </p>

            <!-- Cover photo (only shown if the program has one uploaded) -->
            <div v-if="form.program.cover_url" class="overflow-hidden rounded-2xl shadow-md">
                <img :src="form.program.cover_url" :alt="`${form.program.title} cover photo`" class="h-40 w-full object-cover" />
            </div>

            <!-- Banner -->
            <div class="overflow-hidden rounded-2xl shadow-md">
                <div class="px-6 pb-5 pt-6" :class="theme.header">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest" :class="theme.kicker">{{ form.title }}</p>
                    <h1 class="text-xl font-extrabold leading-tight text-white">{{ form.program.title }}</h1>
                    <p class="mt-0.5 text-xs text-white/80">
                        {{ form.batch.batch }}
                        <span v-if="batchDateLabel"> · {{ batchDateLabel }}</span>
                    </p>
                </div>
                <div class="space-y-2 border-t-4 bg-white px-6 py-3 text-xs text-gray-500" :class="theme.strip">
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        <span v-if="batchDateLabel" class="flex items-center gap-1"><CalendarDays class="h-3.5 w-3.5" /> {{ batchDateLabel }}</span>
                        <span v-if="timeLabel" class="flex items-center gap-1"><Clock class="h-3.5 w-3.5" /> {{ timeLabel }}</span>
                        <span v-if="form.batch.venue || form.batch.modality" class="flex items-center gap-1">
                            <MapPin class="h-3.5 w-3.5" /> {{ form.batch.venue || form.batch.modality }}
                        </span>
                    </div>
                    <p v-if="form.intro_text" class="whitespace-pre-line">{{ form.intro_text }}</p>
                    <p>
                        All fields marked with asterisk (<span class="font-bold text-red-500">*</span>) are required. The organizer will review every
                        registration before you are added as a participant.
                    </p>
                    <p v-if="form.closes_at_label" class="font-semibold text-gray-600">Registration closes on {{ form.closes_at_label }}.</p>
                </div>
            </div>

            <!-- Progress -->
            <div class="rounded-2xl bg-white px-5 py-4 shadow-sm">
                <div class="-mx-1 mb-3 flex items-start justify-between gap-1 overflow-x-auto px-1 pb-1">
                    <div v-for="(s, i) in steps" :key="s.key" class="flex w-16 shrink-0 flex-col items-center">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-all"
                            :class="
                                i < currentStep
                                    ? `${theme.accentBg} text-white`
                                    : i === currentStep
                                      ? `${theme.accentBg} text-white ring-4 ${theme.activeRing}`
                                      : 'bg-gray-200 text-gray-500'
                            "
                        >
                            <Check v-if="i < currentStep" class="h-4 w-4" />
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <span
                            class="mt-1.5 whitespace-nowrap text-center text-[10px] leading-tight"
                            :class="i === currentStep ? `font-semibold ${theme.accentText}` : 'text-gray-400'"
                        >
                            {{ s.label }}
                        </span>
                    </div>
                </div>

                <div class="h-1.5 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full rounded-full transition-all duration-300" :class="theme.accentBg" :style="{ width: progressPct + '%' }"></div>
                </div>
                <p class="mt-2 text-center text-xs text-gray-500">Step {{ currentStep + 1 }} of {{ steps.length }}</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- Honeypot: hidden from people and screen readers -->
                <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                    <label>
                        Website
                        <input v-model="honeypot" type="text" name="website" tabindex="-1" autocomplete="off" />
                    </label>
                </div>

                <!-- Step 1: Find your name -->
                <div v-show="currentKey === 'find'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 px-5 py-3" :class="theme.sectionHeader">
                        <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                            <UserSearch class="h-4 w-4" :class="theme.accentText" /> Find Your Name
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500">Search the employee list by your name or employee code.</p>
                    </div>

                    <div class="space-y-4 px-5 py-5">
                        <template v-if="!isManual">
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
                                <p
                                    v-if="searchQuery.trim().length > 0 && searchQuery.trim().length < MIN_SEARCH_LENGTH"
                                    class="mt-1 text-xs text-gray-400"
                                >
                                    Type at least {{ MIN_SEARCH_LENGTH }} letters.
                                </p>
                                <p v-if="fieldError('empcode')" class="mt-1 text-xs text-red-500">{{ fieldError('empcode') }}</p>
                            </div>

                            <!-- Results -->
                            <div v-if="searchResults.length" class="divide-y overflow-hidden rounded-xl border border-gray-200">
                                <button
                                    v-for="employee in searchResults"
                                    :key="employee.empcode"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 border-l-4 px-4 py-2.5 text-left transition hover:bg-gray-50"
                                    :class="registrant.selected?.empcode === employee.empcode ? theme.selected : 'border-transparent'"
                                    @click="selectEmployee(employee)"
                                >
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-gray-800">{{ employee.name }}</span>
                                        <span class="block truncate text-xs text-gray-500">
                                            {{ employee.empcode
                                            }}<template v-if="employee.office_division"> · {{ employee.office_division }}</template>
                                        </span>
                                    </span>
                                    <BadgeCheck
                                        v-if="registrant.selected?.empcode === employee.empcode"
                                        class="h-5 w-5 shrink-0"
                                        :class="theme.accentText"
                                    />
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
                            <div v-if="registrant.selected" class="flex items-center gap-3 rounded-xl border px-4 py-3" :class="theme.selected">
                                <BadgeCheck class="h-5 w-5 shrink-0" :class="theme.accentText" />
                                <div class="min-w-0 flex-1 text-xs text-gray-600">
                                    <p class="truncate text-sm font-bold text-gray-800">{{ registrant.selected.name }}</p>
                                    <p class="truncate">
                                        {{ registrant.selected.empcode
                                        }}<template v-if="registrant.selected.office_division"> · {{ registrant.selected.office_division }}</template>
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

                            <div class="border-t pt-4 text-center">
                                <p class="text-xs text-gray-500">Not in the employee list yet?</p>
                                <button
                                    type="button"
                                    class="mt-1 text-sm font-semibold hover:underline"
                                    :class="theme.accentText"
                                    @click="chooseManualEntry"
                                >
                                    I'm not in the list. I'll enter my details.
                                </button>
                            </div>
                        </template>

                        <div v-else class="space-y-2 text-center text-xs text-gray-500">
                            <UserPen class="mx-auto h-8 w-8" :class="theme.accentText" />
                            <p>You chose to enter your employee details yourself.</p>
                            <button
                                type="button"
                                class="font-semibold hover:underline"
                                :class="theme.accentText"
                                @click="registrant.source = 'listed'"
                            >
                                Search the employee list instead
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Employee information -->
                <div v-show="currentKey === 'employee'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 px-5 py-3" :class="theme.sectionHeader">
                        <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                            <IdCard class="h-4 w-4" :class="theme.accentText" /> Employee Information
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ isManual ? 'Enter your details exactly as they appear in your HR records.' : 'This comes from the employee list.' }}
                        </p>
                    </div>

                    <div class="px-5 py-5">
                        <div v-if="!isManual && registrant.selected" class="space-y-3">
                            <dl class="divide-y rounded-xl border border-gray-200 text-sm">
                                <div class="flex justify-between gap-4 px-4 py-2.5">
                                    <dt class="text-xs font-semibold text-gray-500">Name</dt>
                                    <dd class="text-right font-semibold text-gray-800">{{ registrant.selected.name }}</dd>
                                </div>
                                <div class="flex justify-between gap-4 px-4 py-2.5">
                                    <dt class="text-xs font-semibold text-gray-500">Employee Code</dt>
                                    <dd class="text-right text-gray-800">{{ registrant.selected.empcode }}</dd>
                                </div>
                                <div v-if="registrant.selected.office_division" class="flex justify-between gap-4 px-4 py-2.5">
                                    <dt class="text-xs font-semibold text-gray-500">Office / Division</dt>
                                    <dd class="text-right text-gray-800">{{ registrant.selected.office_division }}</dd>
                                </div>
                            </dl>
                            <p class="text-xs text-gray-500">
                                Your full record from the employee list will be attached to this registration. Not you?
                                <button type="button" class="font-semibold hover:underline" :class="theme.accentText" @click="backToSearch">
                                    Search again
                                </button>
                            </p>
                        </div>

                        <div v-else class="grid gap-4 sm:grid-cols-2">
                            <div v-for="field in MANUAL_FIELDS" :key="field.key">
                                <label :for="`field-${field.key}`" class="mb-1 block text-xs font-semibold text-gray-600">
                                    {{ field.label }} <span v-if="field.required" class="text-red-500">*</span>
                                </label>
                                <div v-if="choicesFor(field) !== null" class="relative">
                                    <select
                                        :id="`field-${field.key}`"
                                        v-model="registrant[field.key]"
                                        :class="[selectClass, 'disabled:cursor-not-allowed disabled:opacity-60']"
                                        :disabled="field.key === 'office_division' && !registrant.region"
                                    >
                                        <option value="">
                                            {{ field.key === 'office_division' && !registrant.region ? '— Select a region first —' : '— Select —' }}
                                        </option>
                                        <option v-for="option in choicesFor(field)" :key="option" :value="option">{{ option }}</option>
                                    </select>
                                    <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                </div>
                                <input
                                    v-else
                                    :id="`field-${field.key}`"
                                    v-model="registrant[field.key]"
                                    type="text"
                                    :placeholder="field.placeholder"
                                    :aria-describedby="field.hint ? `hint-${field.key}` : undefined"
                                    :class="inputClass"
                                />
                                <p v-if="field.hint" :id="`hint-${field.key}`" class="mt-1 text-xs text-gray-400">{{ field.hint }}</p>
                                <p v-if="fieldError(field.key)" class="mt-1 text-xs text-red-500">{{ fieldError(field.key) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Contact information -->
                <div v-show="currentKey === 'contact'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 px-5 py-3" :class="theme.sectionHeader">
                        <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                            <Mail class="h-4 w-4" :class="theme.accentText" /> Contact Information
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500">We'll use these to reach you about this program.</p>
                    </div>
                    <div class="space-y-4 px-5 py-5">
                        <div>
                            <label for="field-email" class="mb-1 block text-xs font-semibold text-gray-600">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input id="field-email" v-model="registrant.email" type="email" autocomplete="email" :class="inputClass" />
                            <p class="mt-1 text-xs text-gray-400">This email will be your login once your registration is approved.</p>
                            <p v-if="fieldError('email')" class="mt-1 text-xs text-red-500">{{ fieldError('email') }}</p>
                        </div>
                        <div>
                            <label for="field-contact" class="mb-1 block text-xs font-semibold text-gray-600">
                                Contact Number <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="field-contact"
                                v-model="registrant.contact_number"
                                type="tel"
                                autocomplete="tel"
                                placeholder="e.g. 0917 123 4567"
                                :class="inputClass"
                            />
                            <p v-if="fieldError('contact_number')" class="mt-1 text-xs text-red-500">{{ fieldError('contact_number') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Additional questions -->
                <div v-if="questions.length" v-show="currentKey === 'questions'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 px-5 py-3" :class="theme.sectionHeader">
                        <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                            <ClipboardList class="h-4 w-4" :class="theme.accentText" /> Additional Questions
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500">The organizer asks these to prepare for the program.</p>
                    </div>
                    <div class="space-y-5 px-5 py-5">
                        <div v-for="question in questions" :key="question.id">
                            <label :for="`question-${question.id}`" class="mb-1 block text-sm font-semibold text-gray-700">
                                {{ question.label }} <span v-if="question.is_required" class="text-red-500">*</span>
                            </label>
                            <p v-if="question.help_text" class="mb-1.5 text-xs text-gray-500">{{ question.help_text }}</p>

                            <input
                                v-if="question.type === 'text'"
                                :id="`question-${question.id}`"
                                v-model="answers[question.id]"
                                type="text"
                                :class="inputClass"
                            />

                            <textarea
                                v-else-if="question.type === 'textarea'"
                                :id="`question-${question.id}`"
                                v-model="answers[question.id]"
                                rows="3"
                                :class="inputClass"
                            />

                            <div v-else-if="question.type === 'select'" class="relative">
                                <select :id="`question-${question.id}`" v-model="answers[question.id]" :class="selectClass">
                                    <option value="">— Select —</option>
                                    <option v-for="opt in question.options ?? []" :key="opt" :value="opt">{{ opt }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            </div>

                            <div v-else-if="question.type === 'radio'" class="flex flex-col gap-1.5">
                                <label
                                    v-for="opt in question.options ?? []"
                                    :key="opt"
                                    class="flex cursor-pointer items-center gap-2 text-xs text-gray-700"
                                >
                                    <input
                                        type="radio"
                                        class="h-3.5 w-3.5"
                                        :name="`question-${question.id}`"
                                        :checked="answers[question.id] === opt"
                                        @change="answers[question.id] = opt"
                                    />
                                    {{ opt }}
                                </label>
                                <label v-if="question.allow_other" class="flex cursor-pointer items-center gap-2 text-xs text-gray-700">
                                    <input
                                        type="radio"
                                        class="h-3.5 w-3.5 shrink-0"
                                        :name="`question-${question.id}`"
                                        :checked="answers[question.id] === OTHER_VALUE"
                                        @change="answers[question.id] = OTHER_VALUE"
                                    />
                                    <span class="shrink-0">Other:</span>
                                    <input
                                        v-model="answersOther[question.id]"
                                        type="text"
                                        :aria-label="`${question.label} — other answer`"
                                        class="min-w-0 flex-1 border-0 border-b border-gray-300 bg-transparent px-1 py-0.5 text-xs text-gray-900 outline-none focus:border-gray-500"
                                        @input="answers[question.id] = OTHER_VALUE"
                                    />
                                </label>
                            </div>

                            <div v-else-if="question.type === 'checkbox'" class="flex flex-col gap-1.5">
                                <label
                                    v-for="opt in question.options ?? []"
                                    :key="opt"
                                    class="flex cursor-pointer items-center gap-2 text-xs text-gray-700"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-3.5 w-3.5"
                                        :checked="answers[question.id]?.includes(opt)"
                                        @change="toggleCheckboxValue(answers[question.id], opt)"
                                    />
                                    {{ opt }}
                                </label>
                                <label v-if="question.allow_other" class="flex cursor-pointer items-center gap-2 text-xs text-gray-700">
                                    <input
                                        type="checkbox"
                                        class="h-3.5 w-3.5 shrink-0"
                                        :checked="answers[question.id]?.includes(OTHER_VALUE)"
                                        @change="toggleCheckboxValue(answers[question.id], OTHER_VALUE)"
                                    />
                                    <span class="shrink-0">Other:</span>
                                    <input
                                        v-model="answersOther[question.id]"
                                        type="text"
                                        :aria-label="`${question.label} — other answer`"
                                        class="min-w-0 flex-1 border-0 border-b border-gray-300 bg-transparent px-1 py-0.5 text-xs text-gray-900 outline-none focus:border-gray-500"
                                        @input="checkOther(question.id)"
                                    />
                                </label>
                            </div>

                            <p v-if="fieldError(`answers.${question.id}`)" class="mt-1 text-xs text-red-500">
                                {{ fieldError(`answers.${question.id}`) }}
                            </p>
                            <p v-if="fieldError(`answers_other.${question.id}`)" class="mt-1 text-xs text-red-500">
                                {{ fieldError(`answers_other.${question.id}`) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Last step: Review and submit -->
                <div v-show="currentKey === 'review'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 px-5 py-3" :class="theme.sectionHeader">
                        <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                            <ShieldCheck class="h-4 w-4" :class="theme.accentText" /> Review and Submit
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500">Check your details before you submit.</p>
                    </div>
                    <div class="space-y-4 px-5 py-5">
                        <dl class="divide-y rounded-xl border border-gray-200 text-sm">
                            <div v-for="row in reviewRows" :key="row.label" class="flex justify-between gap-4 px-4 py-2.5">
                                <dt class="shrink-0 text-xs font-semibold text-gray-500">{{ row.label }}</dt>
                                <dd class="min-w-0 break-words text-right text-gray-800">{{ row.value }}</dd>
                            </div>
                            <div v-for="question in questions" :key="question.id" class="flex justify-between gap-4 px-4 py-2.5">
                                <dt class="text-xs font-semibold text-gray-500">{{ question.label }}</dt>
                                <dd class="min-w-0 break-words text-right text-gray-800">{{ answerLabel(question) }}</dd>
                            </div>
                        </dl>

                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            <button
                                type="button"
                                class="font-semibold hover:underline"
                                :class="theme.accentText"
                                @click="goToStep(isManual ? 'employee' : 'find')"
                            >
                                Edit employee details
                            </button>
                            <button type="button" class="font-semibold hover:underline" :class="theme.accentText" @click="goToStep('contact')">
                                Edit contact details
                            </button>
                            <button
                                v-if="questions.length"
                                type="button"
                                class="font-semibold hover:underline"
                                :class="theme.accentText"
                                @click="goToStep('questions')"
                            >
                                Edit answers
                            </button>
                        </div>

                        <label
                            class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600"
                        >
                            <input v-model="registrant.consent" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>
                                I agree that the TESDA Development Institute may collect and use the personal information in this form to process my
                                registration and to contact me about this program, in line with the Data Privacy Act of 2012 (Republic Act No. 10173).
                                <span class="text-red-500">*</span>
                            </span>
                        </label>
                        <p v-if="fieldError('data_privacy_consent')" class="-mt-2 text-xs text-red-500">{{ fieldError('data_privacy_consent') }}</p>
                    </div>
                </div>

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
                            {{ processing ? 'Submitting…' : 'Submit Registration' }}
                        </button>
                    </div>
                    <p v-if="isLastStep" class="mt-3 flex items-center justify-center gap-1.5 text-center text-xs text-gray-400">
                        <AlertCircle class="h-3.5 w-3.5" /> Your registration is reviewed by the organizer before you're added to the batch.
                    </p>
                </div>
            </form>

            <!-- Footer -->
            <footer class="flex items-center justify-center gap-2.5 pb-4 pt-2">
                <img
                    src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/TESDA_Seal.svg/1280px-TESDA_Seal.svg.png"
                    alt="TESDA Development Institute"
                    class="h-7 w-7 object-contain"
                />
                <span class="text-sm font-semibold text-gray-600">TESDA Development Institute</span>
            </footer>
        </div>
    </div>
</template>
