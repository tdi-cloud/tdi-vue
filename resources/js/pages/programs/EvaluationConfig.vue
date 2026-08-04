<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import {
    ArrowLeft, ClipboardCheck, Copy, ExternalLink, Sparkles, Layers,
    Plus, Pencil, Trash2, ChevronUp, ChevronDown, Check, X, LoaderCircle,
    ListChecks, Users, BarChart3,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirmDialog } = useConfirm();

const page = usePage();
const isSuperAdmin = computed(() => (page.props.auth as any)?.user?.access === 'superadmin');

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
    sort_order: number;
    questions: Question[];
}

interface Facilitator {
    id: number;
    name: string;
    role: string | null;
    sort_order: number;
}

interface EvaluationFormData {
    id: number;
    slug: string;
    title: string;
    intro_text: string | null;
    is_active: boolean;
    created_by_name: string | null;
    created_at: string;
    sections: Section[];
    facilitators: Facilitator[];
}

interface Batch {
    id: number;
    batch: string;
    program_code: string;
    program: { id: number; title: string };
    evaluation_form: EvaluationFormData | null;
}

const props = defineProps<{
    batch: Batch;
    siblingBatchesWithForms: { id: number; batch: string }[];
}>();

const form = computed(() => props.batch.evaluation_form);

const TYPE_LABELS: Record<string, string> = {
    likert5: '5-point rating',
    scale10: '1-10 rating',
    text: 'Open text',
    checkbox: 'Checkboxes',
    radio: 'Single choice',
};

const publicUrl = computed(() => form.value ? route('evaluate.show', form.value.slug) : '');

/* ── Set up form (empty state) ───────────────────────────────────────────── */
const creating = ref(false);
const cloneFromBatchId = ref<string>('');

function createDefault() {
    creating.value = true;
    router.post(route('batches.evaluation-form.store', props.batch.id), { mode: 'default' }, {
        preserveScroll: true,
        onFinish: () => { creating.value = false; },
    });
}

function createFromClone() {
    if (!cloneFromBatchId.value) return;
    creating.value = true;
    router.post(route('batches.evaluation-form.store', props.batch.id), {
        mode: 'clone',
        source_batch_id: cloneFromBatchId.value,
    }, {
        preserveScroll: true,
        onFinish: () => { creating.value = false; },
    });
}

/* ── Settings ─────────────────────────────────────────────────────────────── */
const savingSettings = ref(false);
const settingsDraft = ref({ title: '', intro_text: '' });
const editingSettings = ref(false);

function openEditSettings() {
    if (!form.value) return;
    settingsDraft.value = { title: form.value.title, intro_text: form.value.intro_text ?? '' };
    editingSettings.value = true;
}

function saveSettings() {
    if (!form.value) return;
    savingSettings.value = true;
    router.put(route('evaluation-forms.update', form.value.id), {
        title: settingsDraft.value.title,
        intro_text: settingsDraft.value.intro_text || null,
        is_active: form.value.is_active,
    }, {
        preserveScroll: true,
        onSuccess: () => { editingSettings.value = false; },
        onFinish: () => { savingSettings.value = false; },
    });
}

function toggleActive() {
    if (!form.value) return;
    router.put(route('evaluation-forms.update', form.value.id), {
        title: form.value.title,
        intro_text: form.value.intro_text,
        is_active: !form.value.is_active,
    }, { preserveScroll: true, preserveState: true });
}

const linkCopied = ref(false);
async function copyLink() {
    if (!publicUrl.value) return;
    await navigator.clipboard.writeText(publicUrl.value);
    linkCopied.value = true;
    setTimeout(() => { linkCopied.value = false; }, 1500);
}

const deletingForm = ref(false);
async function deleteForm() {
    if (!form.value) return;
    const confirmed = await confirmDialog(
        `Delete the entire evaluation form for "${props.batch.batch}"? This removes all sections, questions, facilitators, and any responses already submitted — this cannot be undone.`,
        { confirmText: 'Delete Evaluation Form' },
    );
    if (!confirmed) return;

    deletingForm.value = true;
    router.delete(route('evaluation-forms.destroy', form.value.id), {
        preserveScroll: true,
        onFinish: () => { deletingForm.value = false; },
    });
}

/* ── Sections ─────────────────────────────────────────────────────────────── */
const editingSectionId = ref<number | null>(null);
const sectionDraft = ref({ title: '', description: '' });

function openEditSection(section: Section) {
    editingSectionId.value = section.id;
    sectionDraft.value = { title: section.title, description: section.description ?? '' };
}

function saveSection(section: Section) {
    router.put(route('evaluation-sections.update', section.id), {
        title: sectionDraft.value.title,
        description: sectionDraft.value.description || null,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { editingSectionId.value = null; },
    });
}

function moveSection(section: Section, dir: 'up' | 'down') {
    router.post(route(`evaluation-sections.move-${dir}`, section.id), {}, { preserveScroll: true, preserveState: true });
}

/* ── Questions ────────────────────────────────────────────────────────────── */
const showQuestionModal = ref(false);
const editingQuestion = ref<Question | null>(null);
const activeSectionId = ref<number | null>(null);
const questionForm = ref({ type: 'likert5', label: '', options: '', is_required: true });
const questionErrors = ref<Record<string, string>>({});
const savingQuestion = ref(false);

function openAddQuestion(section: Section) {
    activeSectionId.value = section.id;
    editingQuestion.value = null;
    questionForm.value = { type: 'likert5', label: '', options: '', is_required: true };
    questionErrors.value = {};
    showQuestionModal.value = true;
}

function openEditQuestion(section: Section, question: Question) {
    activeSectionId.value = section.id;
    editingQuestion.value = question;
    questionForm.value = {
        type: question.type,
        label: question.label,
        options: (question.options ?? []).join('\n'),
        is_required: question.is_required,
    };
    questionErrors.value = {};
    showQuestionModal.value = true;
}

function submitQuestion() {
    questionErrors.value = {};
    savingQuestion.value = true;

    const payload: Record<string, unknown> = {
        type: questionForm.value.type,
        label: questionForm.value.label,
        is_required: questionForm.value.is_required,
    };
    if (questionForm.value.type === 'checkbox' || questionForm.value.type === 'radio') {
        payload.options = questionForm.value.options.split('\n').map(o => o.trim()).filter(Boolean);
    }

    const onDone = {
        preserveScroll: true,
        onSuccess: () => { showQuestionModal.value = false; },
        onError: (errors: Record<string, string>) => { questionErrors.value = errors; },
        onFinish: () => { savingQuestion.value = false; },
    };

    if (editingQuestion.value) {
        router.put(route('evaluation-questions.update', editingQuestion.value.id), payload, onDone);
    } else if (activeSectionId.value) {
        router.post(route('evaluation-sections.questions.store', activeSectionId.value), payload, onDone);
    }
}

async function deleteQuestion(question: Question) {
    if (!(await confirmDialog(`Delete the question "${question.label}"?`))) return;
    router.delete(route('evaluation-questions.destroy', question.id), { preserveScroll: true });
}

function moveQuestion(question: Question, dir: 'up' | 'down') {
    router.post(route(`evaluation-questions.move-${dir}`, question.id), {}, { preserveScroll: true, preserveState: true });
}

/* ── Facilitators ─────────────────────────────────────────────────────────── */
const showFacilitatorModal = ref(false);
const editingFacilitator = ref<Facilitator | null>(null);
const facilitatorForm = ref({ name: '', role: '' });
const facilitatorErrors = ref<Record<string, string>>({});
const savingFacilitator = ref(false);

function openAddFacilitator() {
    editingFacilitator.value = null;
    facilitatorForm.value = { name: '', role: 'Resource Person/Facilitator' };
    facilitatorErrors.value = {};
    showFacilitatorModal.value = true;
}

function openEditFacilitator(facilitator: Facilitator) {
    editingFacilitator.value = facilitator;
    facilitatorForm.value = { name: facilitator.name, role: facilitator.role ?? '' };
    facilitatorErrors.value = {};
    showFacilitatorModal.value = true;
}

function submitFacilitator() {
    if (!form.value) return;
    facilitatorErrors.value = {};
    savingFacilitator.value = true;

    const payload = { name: facilitatorForm.value.name, role: facilitatorForm.value.role || null };
    const onDone = {
        preserveScroll: true,
        onSuccess: () => { showFacilitatorModal.value = false; },
        onError: (errors: Record<string, string>) => { facilitatorErrors.value = errors; },
        onFinish: () => { savingFacilitator.value = false; },
    };

    if (editingFacilitator.value) {
        router.put(route('evaluation-facilitators.update', editingFacilitator.value.id), payload, onDone);
    } else {
        router.post(route('evaluation-forms.facilitators.store', form.value.id), payload, onDone);
    }
}

async function deleteFacilitator(facilitator: Facilitator) {
    if (!(await confirmDialog(`Remove "${facilitator.name}" from the facilitators list?`))) return;
    router.delete(route('evaluation-facilitators.destroy', facilitator.id), { preserveScroll: true });
}

function moveFacilitator(facilitator: Facilitator, dir: 'up' | 'down') {
    router.post(route(`evaluation-facilitators.move-${dir}`, facilitator.id), {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head :title="`Evaluation — ${batch.batch}`" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4">

            <!-- Back -->
            <button
                class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground w-fit transition-colors"
                @click="router.visit(route('programs.show', batch.program.id))"
            >
                <ArrowLeft class="h-4 w-4" /> Back to Program
            </button>

            <!-- Hero -->
            <div class="relative rounded-2xl bg-gradient-to-br from-rose-700 via-red-700 to-orange-600 p-6 text-white shadow-xl overflow-hidden">
                <div class="absolute inset-0 overflow-hidden pointer-events-none">
                    <div class="absolute -top-8 -right-8 h-48 w-48 rounded-full bg-white/5" />
                    <div class="absolute -bottom-12 -right-4 h-64 w-64 rounded-full bg-white/5" />
                </div>
                <div class="relative flex flex-col gap-2">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-white/20 uppercase tracking-wide w-fit">
                        <ClipboardCheck class="h-3.5 w-3.5" /> Program Evaluation
                    </span>
                    <h1 class="text-xl md:text-2xl font-bold leading-tight">{{ batch.program.title }}</h1>
                    <p class="text-white/80 text-sm">{{ batch.batch }}</p>
                </div>
            </div>

            <!-- Empty state: set up the form -->
            <div v-if="!form" class="rounded-2xl border bg-background p-8 flex flex-col items-center gap-4 text-center">
                <div class="h-14 w-14 rounded-2xl bg-rose-100 dark:bg-rose-950/40 flex items-center justify-center">
                    <Sparkles class="h-7 w-7 text-rose-600" />
                </div>
                <div>
                    <p class="text-sm font-bold">No evaluation form yet for this batch</p>
                    <p class="text-xs text-muted-foreground mt-1 max-w-md">
                        Set one up to start collecting participant feedback on content, methodology, facilitators, and
                        overall experience — you can edit every question afterward.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-2 mt-2">
                    <Button :disabled="creating" class="bg-rose-600 hover:bg-rose-700 dark:text-white" @click="createDefault">
                        <LoaderCircle v-if="creating" class="h-4 w-4 mr-1 animate-spin" />
                        <Sparkles v-else class="h-4 w-4 mr-1" /> Use Default Template
                    </Button>
                    <template v-if="siblingBatchesWithForms.length">
                        <span class="text-xs text-muted-foreground">or</span>
                        <div class="flex items-center gap-2">
                            <Select v-model="cloneFromBatchId">
                                <SelectTrigger class="text-xs h-9 w-48">
                                    <SelectValue placeholder="Clone from a batch…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="b in siblingBatchesWithForms" :key="b.id" :value="String(b.id)" class="text-xs">
                                        {{ b.batch }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button variant="outline" size="sm" :disabled="creating || !cloneFromBatchId" @click="createFromClone">
                                Clone
                            </Button>
                        </div>
                    </template>
                </div>
            </div>

            <template v-else>

                <!-- Settings -->
                <div class="rounded-2xl border bg-background p-5 flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div v-if="!editingSettings" class="flex items-center gap-2">
                                <h2 class="text-sm font-extrabold">{{ form.title }}</h2>
                                <button class="text-muted-foreground hover:text-foreground transition-colors" @click="openEditSettings">
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div v-else class="flex flex-col gap-2 max-w-md">
                                <Input v-model="settingsDraft.title" class="text-xs h-8" placeholder="Form title" />
                                <Textarea v-model="settingsDraft.intro_text" class="text-xs" rows="2" placeholder="Optional intro text shown to respondents" />
                                <div class="flex items-center gap-2">
                                    <Button size="sm" class="h-7 text-xs bg-rose-600 hover:bg-rose-700 dark:text-white" :disabled="savingSettings" @click="saveSettings">
                                        <Check class="h-3 w-3 mr-1" /> Save
                                    </Button>
                                    <Button size="sm" variant="outline" class="h-7 text-xs" @click="editingSettings = false">
                                        <X class="h-3 w-3 mr-1" /> Cancel
                                    </Button>
                                </div>
                            </div>
                            <p v-if="!editingSettings && form.intro_text" class="text-xs text-muted-foreground mt-1 max-w-md">{{ form.intro_text }}</p>
                            <p class="text-[11px] text-muted-foreground mt-1">
                                Set up by {{ form.created_by_name ?? 'Unknown' }}
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1.5 rounded-full transition-colors"
                                :class="form.is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 hover:bg-emerald-200'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300 hover:bg-slate-200'"
                                @click="toggleActive"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="form.is_active ? 'bg-emerald-500' : 'bg-slate-400'" />
                                {{ form.is_active ? 'Accepting Responses' : 'Closed' }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-3 border-t">
                        <code class="text-[11px] bg-muted px-2 py-1 rounded-md truncate max-w-xs">{{ publicUrl }}</code>
                        <Button size="sm" variant="outline" class="h-7 text-xs" @click="copyLink">
                            <Check v-if="linkCopied" class="h-3 w-3 mr-1 text-emerald-600" />
                            <Copy v-else class="h-3 w-3 mr-1" /> {{ linkCopied ? 'Copied!' : 'Copy Link' }}
                        </Button>
                        <a :href="publicUrl" target="_blank" rel="noopener">
                            <Button size="sm" variant="outline" class="h-7 text-xs">
                                <ExternalLink class="h-3 w-3 mr-1" /> Open Form
                            </Button>
                        </a>
                        <Link :href="route('programs.show', batch.program.id) + '?tab=evaluation'" class="ml-auto">
                            <Button size="sm" variant="outline" class="h-7 text-xs text-rose-600 border-rose-200 hover:bg-rose-50">
                                <BarChart3 class="h-3 w-3 mr-1" /> View Results Dashboard
                            </Button>
                        </Link>
                    </div>

                    <div v-if="isSuperAdmin" class="flex items-center justify-between gap-3 pt-3 border-t">
                        <p class="text-[11px] text-muted-foreground">
                            Superadmin only: permanently delete this batch's evaluation form and start over.
                        </p>
                        <Button size="sm" variant="destructive" class="h-7 text-xs shrink-0" :disabled="deletingForm" @click="deleteForm">
                            <LoaderCircle v-if="deletingForm" class="h-3 w-3 mr-1 animate-spin" />
                            <Trash2 v-else class="h-3 w-3 mr-1" /> Delete Evaluation Form
                        </Button>
                    </div>
                </div>

                <!-- Sections & Questions -->
                <div class="flex flex-col gap-3">
                    <h2 class="text-sm font-extrabold flex items-center gap-1.5">
                        <ListChecks class="h-4 w-4 text-rose-600" /> Sections & Questions
                    </h2>

                    <div v-for="(section, sIdx) in form.sections" :key="section.id" class="rounded-2xl border bg-background overflow-hidden">
                        <div class="flex items-start justify-between gap-3 px-5 py-4 bg-rose-50/50 dark:bg-rose-950/10 border-b">
                            <div class="min-w-0 flex-1">
                                <div v-if="editingSectionId !== section.id" class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold">{{ section.title }}</h3>
                                    <button class="text-muted-foreground hover:text-foreground transition-colors" @click="openEditSection(section)">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <div v-else class="flex flex-col gap-2 max-w-lg">
                                    <Input v-model="sectionDraft.title" class="text-xs h-8" placeholder="Section title" />
                                    <Textarea v-model="sectionDraft.description" class="text-xs" rows="2" placeholder="Optional description shown under the title" />
                                    <div class="flex items-center gap-2">
                                        <Button size="sm" class="h-7 text-xs bg-rose-600 hover:bg-rose-700 dark:text-white" @click="saveSection(section)">
                                            <Check class="h-3 w-3 mr-1" /> Save
                                        </Button>
                                        <Button size="sm" variant="outline" class="h-7 text-xs" @click="editingSectionId = null">
                                            <X class="h-3 w-3 mr-1" /> Cancel
                                        </Button>
                                    </div>
                                </div>
                                <p v-if="editingSectionId !== section.id && section.description" class="text-xs text-muted-foreground mt-1">
                                    {{ section.description }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button
                                    class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30"
                                    :disabled="sIdx === 0"
                                    @click="moveSection(section, 'up')"
                                >
                                    <ChevronUp class="h-4 w-4" />
                                </button>
                                <button
                                    class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30"
                                    :disabled="sIdx === form.sections.length - 1"
                                    @click="moveSection(section, 'down')"
                                >
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col divide-y">
                            <div v-for="(question, qIdx) in section.questions" :key="question.id" class="flex items-start justify-between gap-3 px-5 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold">{{ question.label }}</p>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <Badge variant="outline" class="text-[10px]">{{ TYPE_LABELS[question.type] }}</Badge>
                                        <Badge v-if="question.is_required" variant="outline" class="text-[10px] text-rose-600 border-rose-200">Required</Badge>
                                        <span v-if="(question.type === 'checkbox' || question.type === 'radio') && question.options" class="text-[10px] text-muted-foreground truncate">
                                            {{ question.options.join(', ') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30" :disabled="qIdx === 0" @click="moveQuestion(question, 'up')">
                                        <ChevronUp class="h-3.5 w-3.5" />
                                    </button>
                                    <button class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30" :disabled="qIdx === section.questions.length - 1" @click="moveQuestion(question, 'down')">
                                        <ChevronDown class="h-3.5 w-3.5" />
                                    </button>
                                    <button class="p-1 rounded text-muted-foreground hover:text-blue-600 transition-colors" @click="openEditQuestion(section, question)">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button class="p-1 rounded text-muted-foreground hover:text-red-600 transition-colors" @click="deleteQuestion(question)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            <p v-if="!section.questions.length" class="px-5 py-4 text-xs text-muted-foreground">No questions in this section yet.</p>
                        </div>

                        <div class="px-5 py-3 border-t">
                            <Button size="sm" variant="outline" class="h-7 text-xs" @click="openAddQuestion(section)">
                                <Plus class="h-3 w-3 mr-1" /> Add Question
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Facilitators -->
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-extrabold flex items-center gap-1.5">
                            <Users class="h-4 w-4 text-rose-600" /> Facilitators to Rate
                        </h2>
                        <Button size="sm" class="h-7 text-xs bg-rose-600 hover:bg-rose-700 dark:text-white" @click="openAddFacilitator">
                            <Plus class="h-3 w-3 mr-1" /> Add Facilitator
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground -mt-2">
                        Every facilitator added here will be rated separately by each respondent using the Section III questions above.
                    </p>

                    <div v-if="!form.facilitators.length" class="rounded-2xl border border-dashed py-10 text-center text-sm text-muted-foreground">
                        No facilitators added yet.
                    </div>

                    <div v-else class="rounded-2xl border bg-background overflow-hidden divide-y">
                        <div v-for="(facilitator, fIdx) in form.facilitators" :key="facilitator.id" class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold truncate">{{ facilitator.name }}</p>
                                <p class="text-xs text-muted-foreground truncate">{{ facilitator.role }}</p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30" :disabled="fIdx === 0" @click="moveFacilitator(facilitator, 'up')">
                                    <ChevronUp class="h-3.5 w-3.5" />
                                </button>
                                <button class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30" :disabled="fIdx === form.facilitators.length - 1" @click="moveFacilitator(facilitator, 'down')">
                                    <ChevronDown class="h-3.5 w-3.5" />
                                </button>
                                <button class="p-1 rounded text-muted-foreground hover:text-blue-600 transition-colors" @click="openEditFacilitator(facilitator)">
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                                <button class="p-1 rounded text-muted-foreground hover:text-red-600 transition-colors" @click="deleteFacilitator(facilitator)">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </template>
        </div>

        <!-- Question Dialog -->
        <Dialog :open="showQuestionModal" @update:open="showQuestionModal = $event">
            <DialogContent class="max-w-lg !rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><ListChecks class="h-4 w-4" /> {{ editingQuestion ? 'Edit Question' : 'Add Question' }}</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        {{ editingQuestion ? 'Update this question.' : 'Add a new question to this section.' }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4 py-2" @submit.prevent="submitQuestion">
                    <div class="grid gap-1">
                        <Label class="text-xs">Question Type <span class="text-red-500">*</span></Label>
                        <Select v-model="questionForm.type">
                            <SelectTrigger class="text-xs h-8">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="likert5">5-point rating (Strongly Agree → Not Applicable)</SelectItem>
                                <SelectItem class="text-xs" value="scale10">1–10 rating</SelectItem>
                                <SelectItem class="text-xs" value="text">Open text</SelectItem>
                                <SelectItem class="text-xs" value="checkbox">Checkboxes (multiple choice)</SelectItem>
                                <SelectItem class="text-xs" value="radio">Single choice (radio buttons)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-1">
                        <Label class="text-xs">Question <span class="text-red-500">*</span></Label>
                        <Textarea v-model="questionForm.label" class="text-xs" rows="2" placeholder="e.g. Objectives were clearly explained" />
                        <p v-if="questionErrors.label" class="text-xs text-red-500">{{ questionErrors.label }}</p>
                    </div>

                    <div v-if="questionForm.type === 'checkbox' || questionForm.type === 'radio'" class="grid gap-1">
                        <Label class="text-xs">Options <span class="text-red-500">*</span></Label>
                        <Textarea v-model="questionForm.options" class="text-xs" rows="3" placeholder="One option per line, e.g.&#10;just right&#10;too slow&#10;too fast" />
                        <p v-if="questionErrors.options" class="text-xs text-red-500">{{ questionErrors.options }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-xs cursor-pointer w-fit">
                        <input type="checkbox" v-model="questionForm.is_required" class="h-3.5 w-3.5" />
                        Required question
                    </label>
                </form>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <Button type="button" variant="outline" size="sm" @click="showQuestionModal = false">Cancel</Button>
                    <Button type="button" size="sm" class="bg-rose-600 hover:bg-rose-700 dark:text-white" :disabled="savingQuestion" @click="submitQuestion">
                        <LoaderCircle v-if="savingQuestion" class="h-3 w-3 animate-spin mr-1" />
                        {{ editingQuestion ? 'Save Changes' : 'Add Question' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Facilitator Dialog -->
        <Dialog :open="showFacilitatorModal" @update:open="showFacilitatorModal = $event">
            <DialogContent class="max-w-sm !rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><Users class="h-4 w-4" /> {{ editingFacilitator ? 'Edit Facilitator' : 'Add Facilitator' }}</DialogTitle>
                </DialogHeader>

                <form class="grid gap-4 py-2" @submit.prevent="submitFacilitator">
                    <div class="grid gap-1">
                        <Label class="text-xs">Name <span class="text-red-500">*</span></Label>
                        <Input v-model="facilitatorForm.name" class="text-xs h-8" placeholder="Full name" />
                        <p v-if="facilitatorErrors.name" class="text-xs text-red-500">{{ facilitatorErrors.name }}</p>
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs">Role</Label>
                        <Input v-model="facilitatorForm.role" class="text-xs h-8" placeholder="e.g. Resource Person/Facilitator" />
                    </div>
                </form>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <Button type="button" variant="outline" size="sm" @click="showFacilitatorModal = false">Cancel</Button>
                    <Button type="button" size="sm" class="bg-rose-600 hover:bg-rose-700 dark:text-white" :disabled="savingFacilitator" @click="submitFacilitator">
                        <LoaderCircle v-if="savingFacilitator" class="h-3 w-3 animate-spin mr-1" />
                        {{ editingFacilitator ? 'Save Changes' : 'Add Facilitator' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
