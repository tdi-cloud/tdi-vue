<script setup lang="ts">
import { pickedOther, type AnswerValue, type SurveyOption, type SurveyQuestion } from '@/lib/publicForms';
import { type RegistrationTheme } from '@/lib/registrationThemes';
import { RotateCcw } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

/**
 * One public form question, in English with the Filipino translation below.
 * Handles every question type: ranking (tap choices in order), multiple
 * (tick up to `max`), single (one choice; rating scales as a row of numbers)
 * and text. A picked "Other" shows a text box for the typed answer.
 */
const props = defineProps<{
    question: SurveyQuestion;
    /** Shown before the question, counted over the questions this person is asked. */
    number: number;
    /** Marker value sent when "Other" is picked. */
    otherValue: string;
    theme: RegistrationTheme;
    error?: string;
    otherError?: string;
}>();

const value = defineModel<AnswerValue>('value', { required: true });
const other = defineModel<string>('other', { required: true });

const fieldId = computed(() => `question-${props.question.key}`);
const otherInput = ref<HTMLInputElement | null>(null);

/** The options plus "Other", as one list. */
const choices = computed<SurveyOption[]>(() => [
    ...props.question.options,
    ...(props.question.allow_other
        ? [{ value: props.otherValue, label: props.question.other_label, label_fil: props.question.other_label_fil, score: null, exclusive: false }]
        : []),
]);

const scaleChoices = computed(() => choices.value.filter((option) => option.score !== null));
const plainChoices = computed(() => choices.value.filter((option) => option.score === null));

const selected = computed<string[]>(() => (Array.isArray(value.value) ? value.value : []));
const showOther = computed(() => props.question.allow_other && pickedOther(value.value, props.otherValue));

const describedBy = computed(
    () =>
        [props.question.instruction ? `${fieldId.value}-instruction` : null, props.error ? `${fieldId.value}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined,
);

async function focusOtherInput(): Promise<void> {
    await nextTick();
    otherInput.value?.focus();
}

/* ── Ranking ──────────────────────────────────────────────────────────────── */

const rankLimit = computed(() => props.question.rank ?? 0);
const isRankingFull = computed(() => selected.value.length >= rankLimit.value);

function rankOf(choice: string): number {
    return selected.value.indexOf(choice) + 1;
}

// Tapping a ranked choice takes it out, and the ones after it move up.
function toggleRank(choice: string): void {
    const ranking = [...selected.value];
    const index = ranking.indexOf(choice);

    if (index !== -1) {
        ranking.splice(index, 1);
    } else if (!isRankingFull.value) {
        ranking.push(choice);
        if (choice === props.otherValue) {
            focusOtherInput();
        }
    } else {
        return;
    }

    value.value = ranking;
}

/* ── Multiple ─────────────────────────────────────────────────────────────── */

const exclusiveValues = computed(() => choices.value.filter((option) => option.exclusive).map((option) => option.value));
const regularSelectedCount = computed(() => selected.value.filter((choice) => !exclusiveValues.value.includes(choice)).length);

// An exclusive answer ("No significant barriers") is never disabled: picking it replaces the others.
function isMultipleDisabled(option: SurveyOption): boolean {
    return (
        !selected.value.includes(option.value) && !option.exclusive && props.question.max !== null && regularSelectedCount.value >= props.question.max
    );
}

function toggleMultiple(option: SurveyOption): void {
    if (selected.value.includes(option.value)) {
        value.value = selected.value.filter((choice) => choice !== option.value);
        return;
    }

    if (option.exclusive) {
        value.value = [option.value];
        return;
    }

    if (isMultipleDisabled(option)) {
        return;
    }

    value.value = [...selected.value.filter((choice) => !exclusiveValues.value.includes(choice)), option.value];
    if (option.value === props.otherValue) {
        focusOtherInput();
    }
}

/* ── Single ───────────────────────────────────────────────────────────────── */

function choose(option: SurveyOption): void {
    value.value = option.value;
    if (option.value === props.otherValue) {
        focusOtherInput();
    }
}

const textLength = computed(() => (typeof value.value === 'string' ? value.value.length : 0));

function optionClasses(isSelected: boolean, isDisabled = false): string[] {
    return [
        'flex w-full items-start gap-3 rounded-xl border px-3.5 py-3 text-left transition',
        isSelected ? props.theme.selected : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50',
        isDisabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
    ];
}
</script>

<template>
    <fieldset :aria-describedby="describedBy" :aria-invalid="error ? 'true' : undefined">
        <legend class="flex items-start gap-2.5">
            <span
                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold text-white"
                :class="theme.accentBg"
                aria-hidden="true"
            >
                {{ number }}
            </span>
            <span class="text-sm font-bold leading-snug text-gray-800">
                <span class="sr-only">Question {{ number }}: </span>{{ question.question }}
                <span v-if="question.required" class="text-red-500" aria-hidden="true">*</span>
                <span v-else class="ml-1 text-xs font-medium text-gray-400">(optional)</span>
            </span>
        </legend>

        <div class="mt-1 pl-[2.125rem]">
            <p v-if="question.question_fil" class="text-xs italic leading-snug text-gray-500" lang="fil">{{ question.question_fil }}</p>
            <p
                v-if="question.instruction"
                :id="`${fieldId}-instruction`"
                class="mt-2 inline-flex flex-wrap items-baseline gap-x-1 rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700"
            >
                {{ question.instruction }}
                <span v-if="question.instruction_fil" class="font-normal italic text-gray-500" lang="fil">/ {{ question.instruction_fil }}</span>
            </p>

            <!-- Ranking: tap choices in order of priority -->
            <div v-if="question.type === 'ranking'" class="mt-3">
                <div class="mb-2 flex items-center justify-between gap-2 text-xs text-gray-500">
                    <p aria-live="polite">
                        <span class="font-semibold" :class="isRankingFull ? theme.accentText : 'text-gray-700'">
                            {{ selected.length }} of {{ rankLimit }} ranked.
                        </span>
                        {{ isRankingFull ? 'Tap a ranked choice to remove it.' : `Tap your choice number ${selected.length + 1}.` }}
                    </p>
                    <button
                        v-if="selected.length"
                        type="button"
                        class="flex shrink-0 items-center gap-1 font-semibold hover:underline"
                        :class="theme.accentText"
                        @click="value = []"
                    >
                        <RotateCcw class="h-3 w-3" aria-hidden="true" /> Start over
                    </button>
                </div>

                <ul class="grid gap-2">
                    <li v-for="option in choices" :key="option.value">
                        <button
                            type="button"
                            :class="optionClasses(rankOf(option.value) > 0, isRankingFull && rankOf(option.value) === 0)"
                            :aria-pressed="rankOf(option.value) > 0"
                            :aria-disabled="isRankingFull && rankOf(option.value) === 0 ? 'true' : undefined"
                            @click="toggleRank(option.value)"
                        >
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 text-xs font-extrabold transition"
                                :class="
                                    rankOf(option.value) > 0 ? `${theme.accentBg} border-transparent text-white` : 'border-gray-300 text-transparent'
                                "
                                aria-hidden="true"
                            >
                                {{ rankOf(option.value) || '·' }}
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm text-gray-800">{{ option.label }}</span>
                                <span v-if="option.label_fil" class="block text-xs italic text-gray-500" lang="fil">{{ option.label_fil }}</span>
                                <span v-if="rankOf(option.value) > 0" class="sr-only">, ranked number {{ rankOf(option.value) }}</span>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Multiple: tick up to `max` -->
            <ul v-else-if="question.type === 'multiple'" class="mt-3 grid gap-2">
                <li v-for="option in choices" :key="option.value">
                    <label :class="optionClasses(selected.includes(option.value), isMultipleDisabled(option))">
                        <input
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 shrink-0 accent-blue-600"
                            :checked="selected.includes(option.value)"
                            :disabled="isMultipleDisabled(option)"
                            @change="toggleMultiple(option)"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm text-gray-800">{{ option.label }}</span>
                            <span v-if="option.label_fil" class="block text-xs italic text-gray-500" lang="fil">{{ option.label_fil }}</span>
                        </span>
                    </label>
                </li>
            </ul>

            <!-- Single: rating scales show their numbers in a row, other choices as a list -->
            <div v-else-if="question.type === 'single'" class="mt-3 space-y-2">
                <div v-if="scaleChoices.length" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <label
                        v-for="option in scaleChoices"
                        :key="option.value"
                        class="flex cursor-pointer flex-col items-center gap-0.5 rounded-xl border px-2 py-3 text-center transition focus-within:ring-2 focus-within:ring-blue-200"
                        :class="value === option.value ? theme.selected : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'"
                    >
                        <input
                            type="radio"
                            class="sr-only"
                            :name="fieldId"
                            :value="option.value"
                            :checked="value === option.value"
                            @change="choose(option)"
                        />
                        <span
                            class="text-xl font-extrabold tabular-nums"
                            :class="value === option.value ? theme.accentText : 'text-gray-400'"
                            aria-hidden="true"
                        >
                            {{ option.score }}
                        </span>
                        <span class="text-xs font-semibold leading-tight text-gray-800">{{ option.label }}</span>
                        <span v-if="option.label_fil" class="text-[11px] italic leading-tight text-gray-500" lang="fil">{{ option.label_fil }}</span>
                    </label>
                </div>

                <ul v-if="plainChoices.length" class="grid gap-2">
                    <li v-for="option in plainChoices" :key="option.value">
                        <label :class="optionClasses(value === option.value)">
                            <input
                                type="radio"
                                class="mt-0.5 h-4 w-4 shrink-0 accent-blue-600"
                                :name="fieldId"
                                :value="option.value"
                                :checked="value === option.value"
                                @change="choose(option)"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm text-gray-800">{{ option.label }}</span>
                                <span v-if="option.label_fil" class="block text-xs italic text-gray-500" lang="fil">{{ option.label_fil }}</span>
                            </span>
                        </label>
                    </li>
                </ul>
            </div>

            <!-- Text -->
            <div v-else class="mt-3">
                <textarea
                    :id="fieldId"
                    :value="value"
                    rows="4"
                    :maxlength="question.max_length"
                    :aria-label="question.question"
                    placeholder="Type your answer here"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:ring-2"
                    :class="theme.focus"
                    @input="value = ($event.target as HTMLTextAreaElement).value"
                />
                <p class="mt-1 text-right text-[11px] text-gray-400">{{ textLength }} / {{ question.max_length }}</p>
            </div>

            <!-- "Other" answer -->
            <div v-if="showOther" class="mt-2">
                <label :for="`${fieldId}-other`" class="mb-1 block text-xs font-semibold text-gray-600">
                    Your other answer <span class="text-red-500">*</span>
                </label>
                <input
                    :id="`${fieldId}-other`"
                    ref="otherInput"
                    v-model="other"
                    type="text"
                    maxlength="255"
                    placeholder="Please specify"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:ring-2"
                    :class="theme.focus"
                    :aria-invalid="otherError ? 'true' : undefined"
                />
                <p v-if="otherError" class="mt-1 text-xs text-red-500">{{ otherError }}</p>
            </div>

            <p v-if="error" :id="`${fieldId}-error`" class="mt-2 text-xs font-medium text-red-500">{{ error }}</p>
        </div>
    </fieldset>
</template>
