<script setup lang="ts">
import { ref, computed, reactive, onMounted } from 'vue';
import { router, usePage, Head } from '@inertiajs/vue3';
import { ChevronDown, Loader2, AlertCircle, Star, Users, ClipboardList, Target, Lightbulb, MessageSquareText, Building2, ArrowLeft, ArrowRight, Check } from 'lucide-vue-next';
import EvaluationLikertGrid from '@/components/EvaluationLikertGrid.vue';

interface Question {
    id: number;
    type: 'likert5' | 'scale10' | 'text' | 'checkbox';
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

const selectedParticipant = computed(() =>
    props.participants.find((p) => p.participant_id === Number(respondent.selectedParticipantId)) ?? null,
);

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

interface Step { key: string; label: string; }

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
    if (!isLastStep.value) { next(); return; }
    if (!validateStep(currentKey.value)) return;

    const payload = {
        email: respondent.email,
        empcode: respondent.manualEntry ? null : selectedParticipant.value?.empcode ?? null,
        participant_id: respondent.manualEntry ? null : selectedParticipant.value?.participant_id ?? null,
        respondent_name: respondent.manualEntry ? respondent.manualName : selectedParticipant.value?.name ?? '',
        name_source: respondent.manualEntry ? 'manual' : 'participant',
        answers: { ...answers },
        facilitator_answers: JSON.parse(JSON.stringify(facilitatorAnswers)),
    };

    processing.value = true;
    router.post(route('evaluate.submit', props.form.slug), payload, {
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <Head :title="form.title" />

    <div class="min-h-screen bg-gray-100 py-8 px-4 [color-scheme:light]">
        <div class="mx-auto max-w-2xl space-y-4">

            <!-- Cover photo (only shown if the program has one uploaded) -->
            <div v-if="form.batch.program.cover_page?.image_url" class="rounded-2xl overflow-hidden shadow-md">
                <img
                    :src="form.batch.program.cover_page.image_url"
                    :alt="`${form.batch.program.title} cover photo`"
                    class="w-full h-40 object-cover"
                />
            </div>

            <!-- Banner -->
            <div class="rounded-2xl overflow-hidden shadow-md">
                <div class="bg-gradient-to-br from-rose-700 via-red-700 to-orange-600 px-6 pt-6 pb-5">
                    <p class="text-rose-200 text-xs font-semibold uppercase tracking-widest mb-1">Program Evaluation</p>
                    <h1 class="text-white text-xl font-extrabold leading-tight">{{ form.batch.program.title }}</h1>
                    <p class="text-white/80 text-xs mt-0.5">{{ form.batch.batch }}</p>
                </div>
                <div class="bg-white px-6 py-3 border-t-4 border-rose-500 text-xs text-gray-500 space-y-1">
                    <p v-if="form.intro_text">{{ form.intro_text }}</p>
                    <p>All fields marked with <span class="text-red-500 font-bold">*</span> are required. Your honest feedback helps us improve future programs.</p>
                </div>
            </div>

            <p v-if="flashError" class="flex items-start gap-2 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
                <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" /> {{ flashError }}
            </p>

            <!-- Progress -->
            <div class="bg-white rounded-2xl shadow-sm px-5 py-4">
                <div class="flex items-start justify-between mb-3 gap-1">
                    <div
                        v-for="(s, i) in steps"
                        :key="s.key"
                        class="flex flex-col items-center flex-1 min-w-0"
                    >
                        <div
                            class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-all"
                            :class="i < currentStep ? 'bg-rose-600 text-white'
                                : i === currentStep ? 'bg-rose-600 text-white ring-4 ring-rose-100'
                                : 'bg-gray-200 text-gray-500'"
                        >
                            <Check v-if="i < currentStep" class="h-4 w-4" />
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <span
                            class="text-[10px] mt-1.5 text-center leading-tight w-full px-0.5"
                            :class="i === currentStep ? 'text-rose-600 font-semibold' : 'text-gray-400'"
                        >
                            {{ s.label }}
                        </span>
                    </div>
                </div>

                <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-rose-600 rounded-full transition-all duration-300"
                        :style="{ width: progressPct + '%' }"
                    ></div>
                </div>
                <p class="text-center text-xs text-gray-500 mt-2">
                    Step {{ currentStep + 1 }} of {{ steps.length }}
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">

                <!-- Respondent identity -->
                <div v-show="currentKey === 'respondent'" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-rose-50 border-l-4 border-rose-500 px-5 py-3">
                        <h2 class="text-base font-extrabold text-gray-800">Your Information</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Used only to prevent duplicate submissions and follow up if needed.</p>
                    </div>
                    <div class="px-5 py-5 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input v-model="respondent.email" type="email"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-100 transition" />
                            <p v-if="fieldError('email')" class="mt-1 text-xs text-red-500">{{ fieldError('email') }}</p>
                        </div>

                        <div v-if="!respondent.manualEntry">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                Select Your Name <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select v-model="respondent.selectedParticipantId"
                                    class="w-full appearance-none rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-4 py-2.5 pr-10 text-sm focus:border-rose-500 focus:ring-2 focus:ring-rose-100 outline-none transition">
                                    <option value="">— Select your name —</option>
                                    <option v-for="p in participants" :key="p.participant_id" :value="p.participant_id">{{ p.name }}</option>
                                </select>
                                <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                            </div>
                            <p v-if="fieldError('respondent_name')" class="mt-1 text-xs text-red-500">{{ fieldError('respondent_name') }}</p>
                            <button type="button" class="mt-2 text-xs text-rose-600 hover:underline" @click="respondent.manualEntry = true">
                                My name is not on this list
                            </button>
                        </div>

                        <div v-else>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input v-model="respondent.manualName" type="text" placeholder="Enter your full name"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-100 transition" />
                            <p v-if="fieldError('respondent_name')" class="mt-1 text-xs text-red-500">{{ fieldError('respondent_name') }}</p>
                            <button v-if="participants.length" type="button" class="mt-2 text-xs text-rose-600 hover:underline" @click="respondent.manualEntry = false">
                                Select from participant list instead
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sections -->
                <template v-for="section in form.sections" :key="section.id">
                <div v-show="currentKey === section.key">

                    <!-- Facilitators section: repeat questions per facilitator -->
                    <div v-if="section.key === 'facilitators'" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        <div class="bg-rose-50 border-l-4 border-rose-500 px-5 py-3">
                            <h2 class="text-base font-extrabold text-gray-800 flex items-center gap-1.5">
                                <component :is="SECTION_ICONS[section.key]" class="h-4 w-4 text-rose-600" /> {{ section.title }}
                            </h2>
                            <p v-if="section.description" class="text-xs text-gray-500 mt-0.5">{{ section.description }}</p>
                        </div>

                        <div v-if="!form.facilitators.length" class="px-5 py-6 text-xs text-gray-400 text-center">
                            No facilitators have been added for this batch yet.
                        </div>

                        <div v-for="facilitator in form.facilitators" :key="facilitator.id" class="px-5 py-5 border-t first:border-t-0">
                            <p class="text-sm font-bold text-gray-800">{{ facilitator.name }}</p>
                            <p v-if="facilitator.role" class="text-xs text-gray-500 mb-3">{{ facilitator.role }}</p>

                            <!-- Likert-scale statements share one ratings grid -->
                            <EvaluationLikertGrid
                                v-if="likertQuestions(section).length"
                                :questions="likertQuestions(section)"
                                :values="facilitatorAnswers[facilitator.id]"
                                :options="LIKERT5_OPTIONS"
                                :name-prefix="`facilitator-${facilitator.id}`"
                                :error-for="(qid) => fieldError(`facilitator_answers.${facilitator.id}.${qid}`)"
                            />

                            <div class="space-y-4 mt-4">
                                <div v-for="question in otherQuestions(section)" :key="question.id">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                        {{ question.label }} <span v-if="question.is_required" class="text-red-500">*</span>
                                    </label>

                                    <textarea v-if="question.type === 'text'" v-model="facilitatorAnswers[facilitator.id][question.id]" rows="2"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-100 transition" />

                                    <div v-else-if="question.type === 'checkbox'" class="flex flex-col gap-1.5">
                                        <label v-for="opt in question.options ?? []" :key="opt" class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                            <input type="checkbox" class="h-3.5 w-3.5"
                                                :checked="facilitatorAnswers[facilitator.id][question.id]?.includes(opt)"
                                                @change="toggleCheckboxValue(facilitatorAnswers[facilitator.id][question.id], opt)" />
                                            {{ opt }}
                                        </label>
                                    </div>

                                    <p v-if="fieldError(`facilitator_answers.${facilitator.id}.${question.id}`)" class="mt-1 text-xs text-red-500">
                                        {{ fieldError(`facilitator_answers.${facilitator.id}.${question.id}`) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Regular sections -->
                    <div v-else class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        <div class="bg-rose-50 border-l-4 border-rose-500 px-5 py-3">
                            <h2 class="text-base font-extrabold text-gray-800 flex items-center gap-1.5">
                                <component :is="SECTION_ICONS[section.key]" class="h-4 w-4 text-rose-600" /> {{ section.title }}
                            </h2>
                            <p v-if="section.description" class="text-xs text-gray-500 mt-0.5">{{ section.description }}</p>
                        </div>

                        <div class="px-5 py-5 space-y-5">
                            <!-- Likert-scale statements share one ratings grid -->
                            <EvaluationLikertGrid
                                v-if="likertQuestions(section).length"
                                :questions="likertQuestions(section)"
                                :values="answers"
                                :options="LIKERT5_OPTIONS"
                                :name-prefix="`section-${section.id}`"
                                :error-for="(qid) => fieldError(`answers.${qid}`)"
                            />

                            <div v-for="question in otherQuestions(section)" :key="question.id">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    {{ question.label }} <span v-if="question.is_required" class="text-red-500">*</span>
                                </label>

                                <!-- scale10 -->
                                <div v-if="question.type === 'scale10'" class="space-y-2">
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="n in 10" :key="n" type="button"
                                            class="h-9 w-9 rounded-lg text-sm font-bold border transition"
                                            :class="answers[question.id] === n
                                                ? 'bg-rose-600 border-rose-600 text-white'
                                                : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-rose-50'"
                                            @click="answers[question.id] = n"
                                        >{{ n }}</button>
                                    </div>
                                    <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2 text-[11px] text-gray-500 leading-relaxed">
                                        <p v-for="line in SCALE10_LEGEND" :key="line">{{ line }}</p>
                                    </div>
                                </div>

                                <!-- text -->
                                <textarea v-else-if="question.type === 'text'" v-model="answers[question.id]" rows="3"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 text-gray-900 px-3 py-2 text-sm outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-100 transition" />

                                <!-- checkbox -->
                                <div v-else-if="question.type === 'checkbox'" class="flex flex-col gap-1.5">
                                    <label v-for="opt in question.options ?? []" :key="opt" class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                        <input type="checkbox" class="h-3.5 w-3.5"
                                            :checked="answers[question.id]?.includes(opt)"
                                            @change="toggleCheckboxValue(answers[question.id], opt)" />
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
                <div class="bg-white rounded-2xl shadow-sm px-5 py-5">
                    <div class="flex items-center gap-3">
                        <button
                            v-if="currentStep > 0"
                            type="button"
                            @click="back"
                            class="flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-semibold py-3 px-5 text-sm transition"
                        >
                            <ArrowLeft class="h-4 w-4" /> Back
                        </button>

                        <button
                            v-if="!isLastStep"
                            type="button"
                            @click="next"
                            class="ml-auto flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold py-3 px-6 text-sm transition"
                        >
                            Next <ArrowRight class="h-4 w-4" />
                        </button>

                        <button
                            v-else
                            type="submit"
                            :disabled="processing"
                            class="ml-auto flex items-center justify-center gap-2 rounded-xl bg-rose-600 hover:bg-rose-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-extrabold py-3 px-6 text-sm transition"
                        >
                            <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                            {{ processing ? 'Submitting…' : 'Submit Evaluation' }}
                        </button>
                    </div>
                    <p v-if="isLastStep" class="text-center text-xs text-gray-400 mt-3">
                        Thank you for taking the time to help us improve. Please review your answers before submitting.
                    </p>
                </div>
            </form>

            <!-- Footer -->
            <footer class="flex items-center justify-center gap-2.5 pt-2 pb-4">
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
