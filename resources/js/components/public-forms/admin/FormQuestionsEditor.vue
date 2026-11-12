<script setup lang="ts">
import QuestionDialog from '@/components/public-forms/admin/QuestionDialog.vue';
import SectionDialog from '@/components/public-forms/admin/SectionDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast/use-toast';
import { useConfirm } from '@/composables/useConfirm';
import { optionLabel, questionTypeLabel, type EditorData, type EditorQuestion, type EditorSection } from '@/lib/publicForms';
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Eye, FolderPlus, Info, Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * The question editor of a public form (admin): its sections, each one a
 * step of the form, and their questions, in the order people see them.
 */
const props = defineProps<{
    formSlug: string;
    editor: EditorData;
    previewUrl: string;
    responsesCount: number;
}>();

const { confirmDialog } = useConfirm();

const questionCount = computed(() => props.editor.sections.reduce((total, section) => total + section.questions.length, 0));

/** Numbered over the whole form, as people see them (sections for higher salary grades included). */
const questionNumbers = computed(() => {
    const numbers = new Map<number, number>();
    let number = 0;
    for (const section of props.editor.sections) {
        for (const question of section.questions) numbers.set(question.id, ++number);
    }
    return numbers;
});

/* ── Dialogs ──────────────────────────────────────────────────────────────── */

const sectionDialogOpen = ref(false);
const editingSection = ref<EditorSection | null>(null);

function openSectionDialog(section: EditorSection | null): void {
    editingSection.value = section;
    sectionDialogOpen.value = true;
}

const questionDialogOpen = ref(false);
const editingQuestion = ref<EditorQuestion | null>(null);
const questionSectionId = ref<number | null>(null);

function openQuestionDialog(question: EditorQuestion | null, sectionId: number | null = null): void {
    editingQuestion.value = question;
    questionSectionId.value = sectionId;
    questionDialogOpen.value = true;
}

/* ── Actions ──────────────────────────────────────────────────────────────── */

const busy = ref(false);

function showErrors(errors: Record<string, string>): void {
    const message = Object.values(errors)[0];
    if (message) toast({ title: "That didn't work", description: message, variant: 'destructive' });
}

function send(method: 'post' | 'delete', url: string, data: Record<string, string> = {}): void {
    busy.value = true;
    const options = { preserveScroll: true, onError: showErrors, onFinish: () => (busy.value = false) };
    if (method === 'post') {
        router.post(url, data, options);
    } else {
        router.delete(url, options);
    }
}

function moveSection(section: EditorSection, direction: 'up' | 'down'): void {
    send('post', route('public-form-sections.move', section.id), { direction });
}

function moveQuestion(question: EditorQuestion, direction: 'up' | 'down'): void {
    send('post', route('public-form-questions.move', question.id), { direction });
}

async function deleteSection(section: EditorSection): Promise<void> {
    if (section.questions.length) {
        toast({
            title: 'This section still has questions',
            description: 'Move its questions to another section, or remove them, before deleting it.',
            variant: 'destructive',
        });
        return;
    }
    if (!(await confirmDialog(`Delete the section "${section.title}"?`, { confirmText: 'Delete Section' }))) return;
    send('delete', route('public-form-sections.destroy', section.id));
}

async function removeQuestion(question: EditorQuestion): Promise<void> {
    const note = props.responsesCount > 0 ? ' Answers already sent to it still show in the responses and the CSV export.' : '';
    if (!(await confirmDialog(`Remove this question from the form?${note}`, { confirmText: 'Remove Question' }))) return;
    send('delete', route('public-form-questions.destroy', question.id));
}

/** A short look at the choices, e.g. "Yes · No" or "1 — Very Poor · 2 — Poor · … (5 choices)". */
function choicesSummary(question: EditorQuestion): string {
    if (question.type === 'text') return 'Written answer';
    const labels = question.options.map(optionLabel);
    if (question.allow_other) labels.push(question.other_label || 'Other');
    return labels.length > 4 ? `${labels.slice(0, 3).join(' · ')} · … (${labels.length} choices)` : labels.join(' · ');
}

function questionRule(question: EditorQuestion): string | null {
    if (question.type === 'ranking' && question.rank) return `Rank top ${question.rank}`;
    if (question.type === 'multiple') return question.max ? `Up to ${question.max}` : 'Any number';
    return null;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Toolbar -->
        <div class="flex flex-col gap-3 rounded-2xl border bg-background p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 text-xs text-muted-foreground">
                <p class="text-sm font-bold text-foreground">
                    {{ questionCount }} {{ questionCount === 1 ? 'question' : 'questions' }} in {{ editor.sections.length }}
                    {{ editor.sections.length === 1 ? 'section' : 'sections' }}
                </p>
                <p>Each section is one step of the form. People see your changes the next time they open it.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button as-child size="sm" variant="outline" class="h-8 text-xs">
                    <a :href="previewUrl" target="_blank" rel="noopener"><Eye class="mr-1 h-3.5 w-3.5" /> Preview form</a>
                </Button>
                <Button size="sm" variant="outline" class="h-8 text-xs" @click="openSectionDialog(null)">
                    <FolderPlus class="mr-1 h-3.5 w-3.5" /> Add section
                </Button>
            </div>
        </div>

        <p
            v-if="responsesCount > 0"
            class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300"
        >
            <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <span>
                {{ responsesCount }} {{ responsesCount === 1 ? 'person has' : 'people have' }} answered this form. Changes apply to new answers.
                Removed questions and choices still show in the answers already sent<template v-if="editor.removed_count">
                    ({{ editor.removed_count }} removed {{ editor.removed_count === 1 ? 'question' : 'questions' }} so far)</template
                >.
            </span>
        </p>

        <!-- Sections -->
        <section v-for="(section, sectionIndex) in editor.sections" :key="section.id" class="overflow-hidden rounded-2xl border bg-background">
            <header class="flex flex-col gap-2 border-b bg-muted/40 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Section {{ sectionIndex + 1 }}</span>
                        <h3 class="text-sm font-bold">{{ section.title }}</h3>
                        <Badge
                            v-if="section.audience"
                            variant="outline"
                            class="border-amber-200 bg-amber-50 text-[10px] text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300"
                        >
                            <Users class="mr-1 h-3 w-3" /> {{ section.audience }}
                        </Badge>
                    </div>
                    <p v-if="section.description" class="mt-0.5 text-xs text-muted-foreground">{{ section.description }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-0.5">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        :disabled="busy || sectionIndex === 0"
                        :aria-label="`Move section ${section.title} up`"
                        @click="moveSection(section, 'up')"
                    >
                        <ArrowUp class="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        :disabled="busy || sectionIndex === editor.sections.length - 1"
                        :aria-label="`Move section ${section.title} down`"
                        @click="moveSection(section, 'down')"
                    >
                        <ArrowDown class="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        :aria-label="`Edit section ${section.title}`"
                        @click="openSectionDialog(section)"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-red-500 hover:text-red-600"
                        :disabled="busy || editor.sections.length === 1"
                        :aria-label="`Delete section ${section.title}`"
                        @click="deleteSection(section)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </header>

            <ol v-if="section.questions.length" class="divide-y">
                <li
                    v-for="(question, questionIndex) in section.questions"
                    :key="question.id"
                    class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-5"
                >
                    <div class="flex min-w-0 gap-2.5">
                        <span
                            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[11px] font-extrabold text-white"
                            aria-hidden="true"
                        >
                            {{ questionNumbers.get(question.id) }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-snug">
                                {{ question.question }}
                                <span v-if="question.required" class="text-red-500" title="Required">*</span>
                            </p>
                            <p v-if="question.question_fil" class="text-xs italic text-muted-foreground">{{ question.question_fil }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <Badge variant="outline" class="text-[10px]">{{ questionTypeLabel(question.type) }}</Badge>
                                <Badge v-if="questionRule(question)" variant="outline" class="text-[10px]">{{ questionRule(question) }}</Badge>
                                <Badge v-if="!question.required" variant="outline" class="text-[10px] text-muted-foreground">Optional</Badge>
                            </div>
                            <p class="mt-1 text-[11px] text-muted-foreground">{{ choicesSummary(question) }}</p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-0.5 self-end sm:self-start">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :disabled="busy || questionIndex === 0"
                            :aria-label="`Move question ${questionNumbers.get(question.id)} up`"
                            @click="moveQuestion(question, 'up')"
                        >
                            <ArrowUp class="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :disabled="busy || questionIndex === section.questions.length - 1"
                            :aria-label="`Move question ${questionNumbers.get(question.id)} down`"
                            @click="moveQuestion(question, 'down')"
                        >
                            <ArrowDown class="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :aria-label="`Edit question ${questionNumbers.get(question.id)}`"
                            @click="openQuestionDialog(question)"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-red-500 hover:text-red-600"
                            :disabled="busy"
                            :aria-label="`Remove question ${questionNumbers.get(question.id)}`"
                            @click="removeQuestion(question)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </li>
            </ol>
            <p v-else class="px-5 py-4 text-xs text-muted-foreground">No questions in this section yet.</p>

            <div class="border-t px-4 py-3 sm:px-5">
                <Button size="sm" variant="outline" class="h-8 text-xs" @click="openQuestionDialog(null, section.id)">
                    <Plus class="mr-1 h-3.5 w-3.5" /> Add question
                </Button>
            </div>
        </section>

        <SectionDialog v-model:open="sectionDialogOpen" :form-slug="formSlug" :section="editingSection" />
        <QuestionDialog
            v-model:open="questionDialogOpen"
            :form-slug="formSlug"
            :sections="editor.sections"
            :question="editingQuestion"
            :section-id="questionSectionId"
            :has-responses="responsesCount > 0"
        />
    </div>
</template>
