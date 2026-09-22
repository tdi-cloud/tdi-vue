<script setup lang="ts">
import EvaluationLikertGrid from '@/components/EvaluationLikertGrid.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    Building2,
    Check,
    ChevronDown,
    ClipboardList,
    Lightbulb,
    Loader2,
    Star,
    Target,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

interface Question {
    id: number;
    type: 'likert5' | 'scale10' | 'text' | 'checkbox' | 'radio';
    label: string;
    options: string[] | null;
    is_required: boolean;
    sort_order: number;
}

interface Section {
    id: number;
    key: string;
    title: string;
    description: string | null;
    questions: Question[];
}

interface Facilitator {
    id: number;
    name: string;
    role: string | null;
}

interface EvaluationFormData {
    id: number;
    slug: string;
    title: string;
    intro_text: string | null;
    sections: Section[];
    facilitators: Facilitator[];
    batch: {
        id: number;
        batch: string;
        date_start: string | null;
        date_end: string | null;
        program: {
            id: number;
            title: string;
            cover_page?: { id: number; image_url: string | null } | null;
        };
    };
}

interface ParticipantOption {
    participant_id: number;
    empcode: string;
    name: string;
}

const props = defineProps<{
    form: EvaluationFormData;
    participants: ParticipantOption[];
    backgroundUrl: string | null;
}>();

onMounted(() => {
    document.documentElement.classList.remove('dark');
});

const page = usePage();
const serverErrors = computed(() => (page.props.errors as Record<string, string>) ?? {});
const flashError = computed(() => (page.props.flash as any)?.error as string | undefined);

const SECTION_ICONS: Record<string, any> = {
    content: ClipboardList,
    methodology: Lightbulb,
    environment: Building2,
    facilitators: Users,
    planned_actions: Target,
    overall: Star,
};

const LIKERT5_OPTIONS: { value: number; label: string }[] = [
    { value: 5, label: 'Strongly Agree' },
    { value: 4, label: 'Agree' },
    { value: 3, label: 'Disagree' },
    { value: 2, label: 'Strongly Disagree' },
    { value: 1, label: 'Not Applicable' },
];

const STEP_LABELS: Record<string, string> = {
    respondent: 'Your Info',
    content: 'Content',
    methodology: 'Methodology',
    environment: 'Environment',
    facilitators: 'Facilitator',
    planned_actions: 'Planned Actions',
    overall: 'Overall Rating',
};

const SCALE10_LEGEND: string[] = [
    '10 = Very Exceptional',
    '8-9 = Very Good',
    '6-7 = Satisfactory',
    '5 = Passing',
    '3-4 = Fair',
    '2 = Poor',
    '1 = Completely Unacceptable',
];

const formatDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '');

const batchDateLabel = computed(() => {
    const start = formatDate(props.form.batch.date_start);
    const end = formatDate(props.form.batch.date_end);
    if (!start) return '';
    if (!end || end === start) return start;
    return `${start} – ${end}`;
});

/* ── Respondent identity ─────────────────────────────────────────────────── */

const respondent = reactive({
    email: '',
    selectedParticipantId: '' as string | number,
    manualName: '',
    manualEntry: props.participants.length === 0,
});

const clientErrors = ref<Record<string, string>>({});

function fieldError(key: string): string | undefined {
    return clientErrors.value[key] ?? serverErrors.value[key];
}

const selectedParticipant = computed(() => props.participants.find((p) => p.participant_id === Number(respondent.selectedParticipantId)) ?? null);

/* ── Answers ──────────────────────────────────────────────────────────────── */

const answers = reactive<Record<number, any>>({});
const facilitatorAnswers = reactive<Record<number, Record<number, any>>>({});

function defaultValueFor(type: Question['type']) {
    return type === 'checkbox' ? [] : type === 'text' ? '' : null;
}

for (const section of props.form.sections) {
    for (const question of section.questions) {
        if (section.key === 'facilitators') {
            for (const facilitator of props.form.facilitators) {
                if (!facilitatorAnswers[facilitator.id]) facilitatorAnswers[facilitator.id] = {};
                facilitatorAnswers[facilitator.id][question.id] = defaultValueFor(question.type);
            }
        } else {
            answers[question.id] = defaultValueFor(question.type);
        }
    }
}

function likertQuestions(section: Section) {
    return section.questions.filter((q) => q.type === 'likert5');
}

function otherQuestions(section: Section) {
    return section.questions.filter((q) => q.type !== 'likert5');
}

function toggleCheckboxValue(target: any[], option: string) {
    const idx = target.indexOf(option);
    if (idx === -1) {
        target.push(option);
    } else {
        target.splice(idx, 1);
    }
}

/* ── Steps ────────────────────────────────────────────────────────────────── */

interface Step {
    key: string;
    label: string;
}

const steps = computed<Step[]>(() => [
    { key: 'respondent', label: 'Your Info' },
    ...props.form.sections.map((s) => ({ key: s.key, label: STEP_LABELS[s.key] ?? s.title })),
]);

const currentStep = ref(0);
const currentKey = computed(() => steps.value[currentStep.value]?.key);
const isLastStep = computed(() => currentStep.value === steps.value.length - 1);
const progressPct = computed(() => ((currentStep.value + 1) / steps.value.length) * 100);

/* ── Validation ───────────────────────────────────────────────────────────── */

function isEmpty(value: any): boolean {
    if (Array.isArray(value)) return value.length === 0;
    return value === null || value === undefined || String(value).trim() === '';
}

function validateStep(stepKey: string): boolean {
    clientErrors.value = {};

    if (stepKey === 'respondent') {
        if (isEmpty(respondent.email)) {
            clientErrors.value.email = 'Email address is required.';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(respondent.email)) {
            clientErrors.value.email = 'Please enter a valid email address.';
        }

        if (respondent.manualEntry) {
            if (isEmpty(respondent.manualName)) {
                clientErrors.value.respondent_name = 'Please enter your name.';
            }
        } else if (!respondent.selectedParticipantId) {
            clientErrors.value.respondent_name = 'Please select your name from the list.';
        }
    } else {
        const section = props.form.sections.find((s) => s.key === stepKey);
        for (const question of section?.questions ?? []) {
            if (!question.is_required) continue;

            if (section?.key === 'facilitators') {
                for (const facilitator of props.form.facilitators) {
                    if (isEmpty(facilitatorAnswers[facilitator.id]?.[question.id])) {
                        clientErrors.value[`facilitator_answers.${facilitator.id}.${question.id}`] = 'This field is required.';
                    }
                }
            } else if (isEmpty(answers[question.id])) {
                clientErrors.value[`answers.${question.id}`] = 'This field is required.';
            }
        }
    }

    if (Object.keys(clientErrors.value).length > 0) {
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

/* ── Submit ───────────────────────────────────────────────────────────────── */

const processing = ref(false);

function submit() {
    if (!isLastStep.value) {
        next();
        return;
    }
    if (!validateStep(currentKey.value)) return;

    const payload = {
        email: respondent.email,
        empcode: respondent.manualEntry ? null : (selectedParticipant.value?.empcode ?? null),
        participant_id: respondent.manualEntry ? null : (selectedParticipant.value?.participant_id ?? null),
        respondent_name: respondent.manualEntry ? respondent.manualName : (selectedParticipant.value?.name ?? ''),
        name_source: respondent.manualEntry ? 'manual' : 'participant',
        answers: { ...answers },
        facilitator_answers: JSON.parse(JSON.stringify(facilitatorAnswers)),
    };

    processing.value = true;
    router.post(route('evaluate.submit', props.form.slug), payload, {
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Head :title="form.title" />

    <div class="relative min-h-screen bg-gray-100 px-4 py-8 [color-scheme:light]">
        <!-- Background image (superadmin default via Homepage Images, or a per-batch override in Evaluation settings) -->
        <div
            v-if="backgroundUrl"
            class="fixed inset-0 bg-cover bg-center bg-fixed"
            :style="{ backgroundImage: `url(${backgroundUrl})` }"
        ></div>
        <div v-if="backgroundUrl" class="fixed inset-0 bg-white/80"></div>

        <div class="relative mx-auto max-w-2xl space-y-4">
            <!-- Cover photo (only shown if the program has one uploaded) -->
            <div v-if="form.batch.program.cover_page?.image_url" class="overflow-hidden rounded-2xl shadow-md">
                <img
                    :src="form.batch.program.cover_page.image_url"
                    :alt="`${form.batch.program.title} cover photo`"
                    class="h-40 w-full object-cover"
                />
            </div>

            <!-- Banner -->
            <div class="overflow-hidden rounded-2xl shadow-md">
                <div class="bg-gradient-to-br from-rose-700 via-red-700 to-orange-600 px-6 pb-5 pt-6">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-rose-200">Program Evaluation</p>
                    <h1 class="text-xl font-extrabold leading-tight text-white">{{ form.batch.program.title }}</h1>
                    <p class="mt-0.5 text-xs text-white/80">
                        {{ form.batch.batch }}
                        <span v-if="batchDateLabel"> · {{ batchDateLabel }}</span>
                    </p>
                </div>
                <div class="space-y-1 border-t-4 border-rose-500 bg-white px-6 py-3 text-xs text-gray-500">
                    <p v-if="form.intro_text">{{ form.intro_text }}</p>
                    <p>
                        All fields marked with <span class="font-bold text-red-500">*</span> are required. Your honest feedback helps us improve
                        future programs.
                    </p>
                </div>
            </div>

            <p v-if="flashError" class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" /> {{ flashError }}
            </p>

            <!-- Progress -->
            <div class="rounded-2xl bg-white px-5 py-4 shadow-sm">
                <div class="-mx-1 mb-3 flex items-start justify-between gap-1 overflow-x-auto px-1 pb-1">
                    <div v-for="(s, i) in steps" :key="s.key" class="flex w-16 shrink-0 flex-col items-center">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-all"
                            :class="
                                i < currentStep
                                    ? 'bg-rose-600 text-white'
                                    : i === currentStep
                                      ? 'bg-rose-600 text-white ring-4 ring-rose-100'
                                      : 'bg-gray-200 text-gray-500'
                            "
                        >
                            <Check v-if="i < currentStep" class="h-4 w-4" />
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <span
                            class="mt-1.5 whitespace-nowrap text-center text-[10px] leading-tight"
                            :class="i === currentStep ? 'font-semibold text-rose-600' : 'text-gray-400'"
                        >
                            {{ s.label }}
                        </span>
                    </div>
                </div>

                <div class="h-1.5 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full rounded-full bg-rose-600 transition-all duration-300" :style="{ width: progressPct + '%' }"></div>
                </div>
                <p class="mt-2 text-center text-xs text-gray-500">Step {{ currentStep + 1 }} of {{ steps.length }}</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- Respondent identity -->
                <div v-show="currentKey === 'respondent'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="border-l-4 border-rose-500 bg-rose-50 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Your Information</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Used only to prevent duplicate submissions and follow up if needed.</p>
                    </div>
                    <div class="space-y-4 px-5 py-5">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600"> Email Address <span class="text-red-500">*</span> </label>
                            <input
                                v-model="respondent.email"
                                type="email"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                            />
                            <p v-if="fieldError('email')" class="mt-1 text-xs text-red-500">{{ fieldError('email') }}</p>
                        </div>

                        <div v-if="!respondent.manualEntry">
                            <label class="mb-1 block text-xs font-semibold text-gray-600">
                                Select Your Name <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    v-model="respondent.selectedParticipantId"
                                    class="w-full appearance-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-10 text-sm text-gray-900 outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                                >
                                    <option value="">— Select your name —</option>
                                    <option v-for="p in participants" :key="p.participant_id" :value="p.participant_id">{{ p.name }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            </div>
                            <p v-if="fieldError('respondent_name')" class="mt-1 text-xs text-red-500">{{ fieldError('respondent_name') }}</p>
                            <button type="button" class="mt-2 text-xs text-rose-600 hover:underline" @click="respondent.manualEntry = true">
                                My name is not on this list
                            </button>
                        </div>

                        <div v-else>
                            <label class="mb-1 block text-xs font-semibold text-gray-600"> Full Name <span class="text-red-500">*</span> </label>
                            <input
                                v-model="respondent.manualName"
                                type="text"
                                placeholder="Enter your full name"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                            />
                            <p v-if="fieldError('respondent_name')" class="mt-1 text-xs text-red-500">{{ fieldError('respondent_name') }}</p>
                            <button
                                v-if="participants.length"
                                type="button"
                                class="mt-2 text-xs text-rose-600 hover:underline"
                                @click="respondent.manualEntry = false"
                            >
                                Select from participant list instead
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sections -->
                <template v-for="section in form.sections" :key="section.id">
                    <div v-show="currentKey === section.key">
                        <!-- Facilitators section: repeat questions per facilitator -->
                        <div v-if="section.key === 'facilitators'" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                            <div class="border-l-4 border-rose-500 bg-rose-50 px-5 py-3">
                                <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                                    <component :is="SECTION_ICONS[section.key]" class="h-4 w-4 text-rose-600" /> {{ section.title }}
                                </h2>
                                <p v-if="section.description" class="mt-0.5 text-xs text-gray-500">{{ section.description }}</p>
                            </div>

                            <div v-if="!form.facilitators.length" class="px-5 py-6 text-center text-xs text-gray-400">
                                No facilitators have been added for this batch yet.
                            </div>

                            <div v-for="facilitator in form.facilitators" :key="facilitator.id" class="border-t px-5 py-5 first:border-t-0">
                                <p class="text-sm font-bold text-gray-800">{{ facilitator.name }}</p>
                                <p v-if="facilitator.role" class="mb-3 text-xs text-gray-500">{{ facilitator.role }}</p>

                                <!-- Likert-scale statements share one ratings grid -->
                                <EvaluationLikertGrid
                                    v-if="likertQuestions(section).length"
                                    :questions="likertQuestions(section)"
                                    :values="facilitatorAnswers[facilitator.id]"
                                    :options="LIKERT5_OPTIONS"
                                    :name-prefix="`facilitator-${facilitator.id}`"
                                    :error-for="(qid) => fieldError(`facilitator_answers.${facilitator.id}.${qid}`)"
                                    :on-select="(qid, value) => (facilitatorAnswers[facilitator.id][qid] = value)"
                                />

                                <div class="mt-4 space-y-4">
                                    <div v-for="question in otherQuestions(section)" :key="question.id">
                                        <label class="mb-1.5 block text-xs font-semibold text-gray-600">
                                            {{ question.label }} <span v-if="question.is_required" class="text-red-500">*</span>
                                        </label>

                                        <textarea
                                            v-if="question.type === 'text'"
                                            v-model="facilitatorAnswers[facilitator.id][question.id]"
                                            rows="2"
                                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                                        />

                                        <div v-else-if="question.type === 'checkbox'" class="flex flex-col gap-1.5">
                                            <label
                                                v-for="opt in question.options ?? []"
                                                :key="opt"
                                                class="flex cursor-pointer items-center gap-2 text-xs text-gray-700"
                                            >
                                                <input
                                                    type="checkbox"
                                                    class="h-3.5 w-3.5"
                                                    :checked="facilitatorAnswers[facilitator.id][question.id]?.includes(opt)"
                                                    @change="toggleCheckboxValue(facilitatorAnswers[facilitator.id][question.id], opt)"
                                                />
                                                {{ opt }}
                                            </label>
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
                                                    :name="`facilitator-${facilitator.id}-question-${question.id}`"
                                                    :checked="facilitatorAnswers[facilitator.id][question.id] === opt"
                                                    @change="facilitatorAnswers[facilitator.id][question.id] = opt"
                                                />
                                                {{ opt }}
                                            </label>
                                        </div>

                                        <p
                                            v-if="fieldError(`facilitator_answers.${facilitator.id}.${question.id}`)"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ fieldError(`facilitator_answers.${facilitator.id}.${question.id}`) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Regular sections -->
                        <div v-else class="overflow-hidden rounded-2xl bg-white shadow-sm">
                            <div class="border-l-4 border-rose-500 bg-rose-50 px-5 py-3">
                                <h2 class="flex items-center gap-1.5 text-base font-extrabold text-gray-800">
                                    <component :is="SECTION_ICONS[section.key]" class="h-4 w-4 text-rose-600" /> {{ section.title }}
                                </h2>
                                <p v-if="section.description" class="mt-0.5 text-xs text-gray-500">{{ section.description }}</p>
                            </div>

                            <div class="space-y-5 px-5 py-5">
                                <!-- Likert-scale statements share one ratings grid -->
                                <EvaluationLikertGrid
                                    v-if="likertQuestions(section).length"
                                    :questions="likertQuestions(section)"
                                    :values="answers"
                                    :options="LIKERT5_OPTIONS"
                                    :name-prefix="`section-${section.id}`"
                                    :error-for="(qid) => fieldError(`answers.${qid}`)"
                                    :on-select="(qid, value) => (answers[qid] = value)"
                                />

                                <div v-for="question in otherQuestions(section)" :key="question.id">
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                                        {{ question.label }} <span v-if="question.is_required" class="text-red-500">*</span>
                                    </label>

                                    <!-- scale10 -->
                                    <div v-if="question.type === 'scale10'" class="space-y-2">
                                        <div class="grid grid-cols-10 gap-1">
                                            <button
                                                v-for="n in 10"
                                                :key="n"
                                                type="button"
                                                class="h-9 min-w-0 rounded-lg border px-0 text-[11px] font-bold transition sm:text-sm"
                                                :class="
                                                    answers[question.id] === n
                                                        ? 'border-rose-600 bg-rose-600 text-white'
                                                        : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-rose-50'
                                                "
                                                @click="answers[question.id] = n"
                                            >
                                                {{ n }}
                                            </button>
                                        </div>
                                        <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-[11px] leading-relaxed text-gray-500">
                                            <p v-for="line in SCALE10_LEGEND" :key="line">{{ line }}</p>
                                        </div>
                                    </div>

                                    <!-- text -->
                                    <textarea
                                        v-else-if="question.type === 'text'"
                                        v-model="answers[question.id]"
                                        rows="3"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                                    />

                                    <!-- checkbox -->
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
                                    </div>

                                    <!-- radio (single choice) -->
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
                                    </div>

                                    <p v-if="fieldError(`answers.${question.id}`)" class="mt-1 text-xs text-red-500">
                                        {{ fieldError(`answers.${question.id}`) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Navigation -->
                <div class="rounded-2xl bg-white px-5 py-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <button
                            v-if="currentStep > 0"
                            type="button"
                            @click="back"
                            class="flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        >
                            <ArrowLeft class="h-4 w-4" /> Back
                        </button>

                        <button
                            v-if="!isLastStep"
                            type="button"
                            @click="next"
                            class="ml-auto flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-6 py-3 text-sm font-extrabold text-white transition hover:bg-rose-700"
                        >
                            Next <ArrowRight class="h-4 w-4" />
                        </button>

                        <button
                            v-else
                            type="submit"
                            :disabled="processing"
                            class="ml-auto flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-6 py-3 text-sm font-extrabold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                            {{ processing ? 'Submitting…' : 'Submit Evaluation' }}
                        </button>
                    </div>
                    <p v-if="isLastStep" class="mt-3 text-center text-xs text-gray-400">
                        Thank you for taking the time to help us improve. Please review your answers before submitting.
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
