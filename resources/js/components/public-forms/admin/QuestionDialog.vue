<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { QUESTION_TYPES, type EditorQuestion, type EditorSection, type QuestionType } from '@/lib/publicForms';
import { useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, LoaderCircle, Plus, TriangleAlert, X } from 'lucide-vue-next';
import { computed, watch } from 'vue';

/**
 * Add or edit a question of a public form: its type, English and Filipino
 * text, choices (with rating numbers for a rating scale, or one "only
 * answer" choice for pick-several), an "Other" choice, and how many choices
 * to rank or pick.
 */
const props = defineProps<{
    formSlug: string;
    sections: EditorSection[];
    /** Null to add a new question. */
    question: EditorQuestion | null;
    /** Where a new question goes. */
    sectionId: number | null;
    /** People already answered the form: edits only change new answers. */
    hasResponses: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

// A type alias (not an interface), so useForm() accepts a list of them.
type ChoiceDraft = {
    /** Kept for existing choices, so answers already sent still match them. */
    value: string | null;
    label: string;
    label_fil: string;
    score: string | number;
    exclusive: boolean;
};

const blankChoice = (): ChoiceDraft => ({ value: null, label: '', label_fil: '', score: '', exclusive: false });

const form = useForm({
    section_id: 0,
    type: 'single' as QuestionType,
    question: '',
    question_fil: '',
    instruction: '',
    instruction_fil: '',
    required: true as boolean,
    options: [blankChoice(), blankChoice()] as ChoiceDraft[],
    allow_other: false as boolean,
    other_label: 'Other',
    other_label_fil: 'Iba pa',
    rank: '' as string | number,
    max: '' as string | number,
});

const errors = computed(() => form.errors as Record<string, string | undefined>);
const hasChoices = computed(() => form.type !== 'text');
const choiceCount = computed(() => form.options.length + (form.allow_other ? 1 : 0));

// Fill the boxes each time the dialog opens.
watch(open, (isOpen) => {
    if (!isOpen) return;
    const question = props.question;

    form.defaults({
        section_id: question?.section_id ?? props.sectionId ?? props.sections[0]?.id ?? 0,
        type: question?.type ?? 'single',
        question: question?.question ?? '',
        question_fil: question?.question_fil ?? '',
        instruction: question?.instruction ?? '',
        instruction_fil: question?.instruction_fil ?? '',
        required: question?.required ?? true,
        options: question?.options.length
            ? question.options.map((option) => ({
                  value: option.value,
                  label: option.label,
                  label_fil: option.label_fil ?? '',
                  score: option.score ?? '',
                  exclusive: option.exclusive,
              }))
            : [blankChoice(), blankChoice()],
        allow_other: question?.allow_other ?? false,
        other_label: question?.other_label ?? 'Other',
        other_label_fil: question?.other_label_fil ?? 'Iba pa',
        rank: question?.rank ?? 3,
        max: question?.max ?? '',
    });
    form.reset();
    form.clearErrors();
});

/* ── Choices ──────────────────────────────────────────────────────────────── */

function addChoice(): void {
    form.options.push(blankChoice());
}

function removeChoice(index: number): void {
    form.options.splice(index, 1);
}

function moveChoice(index: number, delta: -1 | 1): void {
    const target = index + delta;
    if (target < 0 || target >= form.options.length) return;
    const [choice] = form.options.splice(index, 1);
    form.options.splice(target, 0, choice);
}

// Only one choice of a pick-several question can be the "only answer" (e.g. "No significant barriers").
function setExclusive(index: number, checked: boolean): void {
    form.options.forEach((choice, position) => {
        choice.exclusive = position === index ? checked : false;
    });
}

/** Gives choices 1, 2, 3… in order, for a rating scale. */
function numberChoices(): void {
    form.options.forEach((choice, index) => {
        choice.score = index + 1;
    });
}

const usesRatingScale = computed(() => form.type === 'single' && form.options.some((choice) => choice.score !== '' && choice.score !== null));

/* ── Save ─────────────────────────────────────────────────────────────────── */

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    form.transform((data) => ({
        ...data,
        options: hasChoices.value
            ? data.options.map((choice) => ({
                  value: choice.value,
                  label: choice.label,
                  label_fil: choice.label_fil,
                  score: choice.score === '' || choice.score === null ? null : Number(choice.score),
                  exclusive: choice.exclusive,
              }))
            : null,
        rank: data.type === 'ranking' && data.rank !== '' ? Number(data.rank) : null,
        max: data.type === 'multiple' && data.max !== '' ? Number(data.max) : null,
    }));

    if (props.question) {
        form.put(route('public-form-questions.update', props.question.id), options);
    } else {
        form.post(route('public-forms.admin.questions.store', props.formSlug), options);
    }
}

const fieldClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] max-w-3xl overflow-y-auto !rounded-2xl">
            <DialogHeader>
                <DialogTitle>{{ question ? 'Edit question' : 'Add a question' }}</DialogTitle>
                <DialogDescription class="text-xs">People see changes the next time they open the form.</DialogDescription>
            </DialogHeader>

            <p
                v-if="question && hasResponses"
                class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300"
            >
                <TriangleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                People have already answered this form. Your changes only apply to new answers; a removed choice still shows in the answers that
                picked it.
            </p>

            <form class="grid gap-5" @submit.prevent="save">
                <!-- Type -->
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-xs font-semibold">Type of question</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="type in QUESTION_TYPES"
                            :key="type.value"
                            class="flex cursor-pointer items-start gap-2.5 rounded-xl border px-3 py-2.5 transition-colors"
                            :class="form.type === type.value ? 'border-blue-600 bg-blue-50 dark:bg-blue-950/30' : 'hover:bg-muted'"
                        >
                            <input v-model="form.type" type="radio" name="question-type" :value="type.value" class="mt-0.5 accent-blue-600" />
                            <span>
                                <span class="block text-xs font-semibold">{{ type.label }}</span>
                                <span class="block text-[11px] leading-snug text-muted-foreground">{{ type.hint }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="errors.type" class="text-xs text-red-500">{{ errors.type }}</p>
                </fieldset>

                <!-- Section -->
                <div class="grid gap-1">
                    <label for="question-section" class="text-xs font-semibold">Section</label>
                    <select id="question-section" v-model="form.section_id" :class="fieldClass">
                        <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.title }}</option>
                    </select>
                    <p v-if="errors.section_id" class="text-xs text-red-500">{{ errors.section_id }}</p>
                </div>

                <!-- Question text -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <label for="question-text" class="text-xs font-semibold">Question <span class="text-red-500">*</span></label>
                        <Textarea id="question-text" v-model="form.question" rows="3" class="text-sm" maxlength="500" />
                        <p v-if="errors.question" class="text-xs text-red-500">{{ errors.question }}</p>
                    </div>
                    <div class="grid gap-1">
                        <label for="question-text-fil" class="text-xs font-semibold">In Filipino <span class="font-normal">(optional)</span></label>
                        <Textarea id="question-text-fil" v-model="form.question_fil" rows="3" class="text-sm" maxlength="500" />
                        <p v-if="errors.question_fil" class="text-xs text-red-500">{{ errors.question_fil }}</p>
                    </div>
                    <div class="grid gap-1">
                        <label for="question-instruction" class="text-xs font-semibold"
                            >Instruction <span class="font-normal">(optional)</span></label
                        >
                        <Input
                            id="question-instruction"
                            v-model="form.instruction"
                            class="h-9 text-sm"
                            maxlength="255"
                            :placeholder="
                                form.type === 'ranking' ? 'e.g. Rank your top three (3)…' : form.type === 'multiple' ? 'e.g. Select up to 2.' : ''
                            "
                        />
                    </div>
                    <div class="grid gap-1">
                        <label for="question-instruction-fil" class="text-xs font-semibold"
                            >Instruction in Filipino <span class="font-normal">(optional)</span></label
                        >
                        <Input id="question-instruction-fil" v-model="form.instruction_fil" class="h-9 text-sm" maxlength="255" />
                    </div>
                </div>

                <label class="flex w-fit cursor-pointer items-center gap-2 text-xs font-semibold">
                    <input v-model="form.required" type="checkbox" class="h-4 w-4 accent-blue-600" />
                    People must answer this question
                </label>

                <!-- Choices -->
                <div v-if="hasChoices" role="group" aria-labelledby="question-choices-heading" class="grid gap-2 rounded-xl border p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 id="question-choices-heading" class="text-xs font-semibold">Choices</h3>
                        <button
                            v-if="form.type === 'single'"
                            type="button"
                            class="text-[11px] font-semibold text-blue-600 hover:underline dark:text-blue-400"
                            @click="numberChoices"
                        >
                            Number them 1, 2, 3… (rating scale)
                        </button>
                    </div>
                    <p v-if="form.type === 'single'" class="text-[11px] text-muted-foreground">
                        Choices with a rating number show as a row of numbered boxes (like 1–4); leave it empty for choices such as "Not applicable".
                    </p>
                    <p v-if="form.type === 'multiple'" class="text-[11px] text-muted-foreground">
                        Tick "Only answer" for a choice that can't be combined with others, such as "No significant barriers".
                    </p>

                    <div v-for="(choice, index) in form.options" :key="index" class="grid gap-1">
                        <div class="flex items-start gap-1.5">
                            <span class="mt-2 w-5 shrink-0 text-right text-[11px] font-semibold text-muted-foreground">{{ index + 1 }}.</span>
                            <div class="grid min-w-0 flex-1 gap-1.5 sm:grid-cols-2">
                                <Input
                                    v-model="choice.label"
                                    class="h-9 text-sm"
                                    maxlength="255"
                                    :aria-label="`Choice ${index + 1}`"
                                    placeholder="Choice"
                                />
                                <Input
                                    v-model="choice.label_fil"
                                    class="h-9 text-sm"
                                    maxlength="255"
                                    :aria-label="`Choice ${index + 1} in Filipino`"
                                    placeholder="In Filipino (optional)"
                                />
                            </div>
                            <Input
                                v-if="form.type === 'single'"
                                v-model="choice.score"
                                type="number"
                                min="0"
                                max="100"
                                class="h-9 w-20 shrink-0 text-sm"
                                :aria-label="`Rating number of choice ${index + 1}`"
                                placeholder="Rating"
                            />
                            <label
                                v-if="form.type === 'multiple'"
                                class="mt-2 flex shrink-0 cursor-pointer items-center gap-1 text-[11px] text-muted-foreground"
                            >
                                <input
                                    type="checkbox"
                                    class="h-3.5 w-3.5 accent-blue-600"
                                    :checked="choice.exclusive"
                                    @change="setExclusive(index, ($event.target as HTMLInputElement).checked)"
                                />
                                Only answer
                            </label>
                            <div class="flex shrink-0 items-center">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-9 w-7"
                                    :disabled="index === 0"
                                    :aria-label="`Move choice ${index + 1} up`"
                                    @click="moveChoice(index, -1)"
                                >
                                    <ArrowUp class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-9 w-7"
                                    :disabled="index === form.options.length - 1"
                                    :aria-label="`Move choice ${index + 1} down`"
                                    @click="moveChoice(index, 1)"
                                >
                                    <ArrowDown class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-9 w-7 text-red-500 hover:text-red-600"
                                    :aria-label="`Remove choice ${index + 1}`"
                                    @click="removeChoice(index)"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                        <p v-if="errors[`options.${index}.label`]" class="pl-7 text-xs text-red-500">{{ errors[`options.${index}.label`] }}</p>
                        <p v-if="errors[`options.${index}.score`]" class="pl-7 text-xs text-red-500">{{ errors[`options.${index}.score`] }}</p>
                    </div>

                    <div>
                        <Button type="button" variant="outline" size="sm" class="h-8 text-xs" @click="addChoice">
                            <Plus class="mr-1 h-3 w-3" /> Add choice
                        </Button>
                    </div>
                    <p v-if="errors.options" class="text-xs text-red-500">{{ errors.options }}</p>
                    <p v-if="usesRatingScale" class="text-[11px] text-muted-foreground">Shown as a rating scale.</p>

                    <!-- "Other" -->
                    <div class="mt-1 grid gap-2 border-t pt-3">
                        <label class="flex w-fit cursor-pointer items-center gap-2 text-xs font-semibold">
                            <input v-model="form.allow_other" type="checkbox" class="h-4 w-4 accent-blue-600" />
                            Add an "Other" choice with a text box
                        </label>
                        <div v-if="form.allow_other" class="grid gap-1.5 pl-6 sm:grid-cols-2">
                            <Input
                                v-model="form.other_label"
                                class="h-9 text-sm"
                                maxlength="100"
                                aria-label="Other choice label"
                                placeholder="Other"
                            />
                            <Input
                                v-model="form.other_label_fil"
                                class="h-9 text-sm"
                                maxlength="100"
                                aria-label="Other choice label in Filipino"
                                placeholder="Iba pa"
                            />
                        </div>
                    </div>
                </div>

                <!-- How many to rank / pick -->
                <div v-if="form.type === 'ranking'" class="grid gap-1">
                    <label for="question-rank" class="text-xs font-semibold">How many choices to rank <span class="text-red-500">*</span></label>
                    <Input id="question-rank" v-model="form.rank" type="number" min="1" :max="choiceCount" class="h-9 w-24 text-sm" />
                    <p class="text-[11px] text-muted-foreground">People put exactly this many choices in order, 1 being their top priority.</p>
                    <p v-if="errors.rank" class="text-xs text-red-500">{{ errors.rank }}</p>
                </div>
                <div v-if="form.type === 'multiple'" class="grid gap-1">
                    <label for="question-max" class="text-xs font-semibold">Most answers allowed <span class="font-normal">(optional)</span></label>
                    <Input id="question-max" v-model="form.max" type="number" min="1" :max="choiceCount" class="h-9 w-24 text-sm" />
                    <p class="text-[11px] text-muted-foreground">Leave it empty to let people tick as many as they want.</p>
                    <p v-if="errors.max" class="text-xs text-red-500">{{ errors.max }}</p>
                </div>

                <div class="flex justify-end gap-2 border-t pt-3">
                    <Button type="button" variant="outline" size="sm" @click="open = false">Cancel</Button>
                    <Button type="submit" size="sm" class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-1 h-3 w-3 animate-spin" />
                        {{ question ? 'Save Question' : 'Add Question' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
