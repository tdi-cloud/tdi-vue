<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart3,
    Check,
    ChevronDown,
    ChevronUp,
    ClipboardCheck,
    ClipboardPaste,
    Copy,
    ExternalLink,
    Image as ImageIcon,
    ListChecks,
    LoaderCircle,
    Pencil,
    Plus,
    RotateCcw,
    Sparkles,
    Trash2,
    Upload,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    background_image_url: string | null;
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
    defaultBackgroundUrl: string;
}>();

const form = computed(() => props.batch.evaluation_form);

const TYPE_LABELS: Record<string, string> = {
    likert5: '5-point rating',
    scale10: '1-10 rating',
    text: 'Open text',
    checkbox: 'Checkboxes',
    radio: 'Single choice',
};

const publicUrl = computed(() => (form.value ? route('evaluate.show', form.value.slug) : ''));

/* ── Set up form (empty state) ───────────────────────────────────────────── */
const creating = ref(false);
const cloneFromBatchId = ref<string>('');

function createDefault() {
    creating.value = true;
    router.post(
        route('batches.evaluation-form.store', props.batch.id),
        { mode: 'default' },
        {
            preserveScroll: true,
            onFinish: () => {
                creating.value = false;
            },
        },
    );
}

function createFromClone() {
    if (!cloneFromBatchId.value) return;
    creating.value = true;
    router.post(
        route('batches.evaluation-form.store', props.batch.id),
        {
            mode: 'clone',
            source_batch_id: cloneFromBatchId.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                creating.value = false;
            },
        },
    );
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
    router.put(
        route('evaluation-forms.update', form.value.id),
        {
            title: settingsDraft.value.title,
            intro_text: settingsDraft.value.intro_text || null,
            is_active: form.value.is_active,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingSettings.value = false;
            },
            onFinish: () => {
                savingSettings.value = false;
            },
        },
    );
}

/* ── Background image ────────────────────────────────────────────────────── */
const backgroundFileInput = ref<HTMLInputElement | null>(null);
const uploadingBackground = ref(false);
const removingBackground = ref(false);

const previewBackgroundUrl = computed(() => form.value?.background_image_url ?? props.defaultBackgroundUrl);

function triggerBackgroundUpload() {
    backgroundFileInput.value?.click();
}

function handleBackgroundFileChange(e: Event) {
    if (!form.value) return;
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    uploadingBackground.value = true;
    router.post(
        route('evaluation-forms.background.upload', form.value.id),
        { image: file },
        {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                uploadingBackground.value = false;
                if (backgroundFileInput.value) backgroundFileInput.value.value = '';
            },
        },
    );
}

async function resetBackground() {
    if (!form.value) return;
    if (!(await confirmDialog('Reset the background image back to the site default?', { confirmText: 'Reset' }))) return;

    removingBackground.value = true;
    router.delete(route('evaluation-forms.background.destroy', form.value.id), {
        preserveScroll: true,
        onFinish: () => {
            removingBackground.value = false;
        },
    });
}

function toggleActive() {
    if (!form.value) return;
    router.put(
        route('evaluation-forms.update', form.value.id),
        {
            title: form.value.title,
            intro_text: form.value.intro_text,
            is_active: !form.value.is_active,
        },
        { preserveScroll: true, preserveState: true },
    );
}

const linkCopied = ref(false);
async function copyLink() {
    if (!publicUrl.value) return;
    await navigator.clipboard.writeText(publicUrl.value);
    linkCopied.value = true;
    setTimeout(() => {
        linkCopied.value = false;
    }, 1500);
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
        onFinish: () => {
            deletingForm.value = false;
        },
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
    router.put(
        route('evaluation-sections.update', section.id),
        {
            title: sectionDraft.value.title,
            description: sectionDraft.value.description || null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                editingSectionId.value = null;
            },
        },
    );
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
        payload.options = questionForm.value.options
            .split('\n')
            .map((o) => o.trim())
            .filter(Boolean);
    }

    const onDone = {
        preserveScroll: true,
        onSuccess: () => {
            showQuestionModal.value = false;
        },
        onError: (errors: Record<string, string>) => {
            questionErrors.value = errors;
        },
        onFinish: () => {
            savingQuestion.value = false;
        },
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
        onSuccess: () => {
            showFacilitatorModal.value = false;
        },
        onError: (errors: Record<string, string>) => {
            facilitatorErrors.value = errors;
        },
        onFinish: () => {
            savingFacilitator.value = false;
        },
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

/* ── Bulk Add Facilitators — a small spreadsheet-like grid you can paste
 * directly into from Excel/Google Sheets. Pasting a multi-cell block into
 * any cell fills the grid from that point onward (tab = next column,
 * newline = next row), growing rows automatically as needed.
 */
interface BulkRow {
    name: string;
    role: string;
}

const showBulkModal = ref(false);
const bulkErrors = ref<Record<string, string>>({});
const savingBulk = ref(false);
const BULK_STARTER_ROWS = 5;

function makeEmptyBulkRows(count: number): BulkRow[] {
    return Array.from({ length: count }, () => ({ name: '', role: '' }));
}

const bulkRows = ref<BulkRow[]>(makeEmptyBulkRows(BULK_STARTER_ROWS));

const filledBulkRows = computed<BulkRow[]>(() =>
    bulkRows.value.map((row) => ({ name: row.name.trim(), role: row.role.trim() })).filter((row) => row.name !== ''),
);

function openBulkAdd() {
    bulkRows.value = makeEmptyBulkRows(BULK_STARTER_ROWS);
    bulkErrors.value = {};
    showBulkModal.value = true;
}

function addBulkRow() {
    bulkRows.value.push({ name: '', role: '' });
}

function removeBulkRow(index: number) {
    bulkRows.value.splice(index, 1);
}

// Ipinasok dito ang pinaste na block (mula sa Excel/Sheets — tab-separated
// ang columns, newline-separated ang rows), simula sa cell kung saan
// na-trigger ang paste. Lumalago ang grid kung kailangan pa ng rows.
function handleBulkPaste(event: ClipboardEvent, rowIndex: number, col: 'name' | 'role') {
    const text = event.clipboardData?.getData('text/plain') ?? '';
    if (!text.includes('\t') && !text.includes('\n')) return; // single-cell paste — default behavior is fine

    event.preventDefault();

    const pastedRows = text
        .replace(/\r/g, '')
        .split('\n')
        .filter((line, i, arr) => !(i === arr.length - 1 && line === ''));
    const startColIsName = col === 'name';

    pastedRows.forEach((line, i) => {
        const cells = line.split('\t');
        const targetIndex = rowIndex + i;

        while (bulkRows.value.length <= targetIndex) {
            bulkRows.value.push({ name: '', role: '' });
        }

        const targetRow = bulkRows.value[targetIndex];
        if (startColIsName) {
            if (cells[0] !== undefined) targetRow.name = cells[0].trim();
            if (cells[1] !== undefined) targetRow.role = cells[1].trim();
        } else if (cells[0] !== undefined) {
            targetRow.role = cells[0].trim();
        }
    });
}

function submitBulkFacilitators() {
    if (!form.value || !filledBulkRows.value.length) return;
    bulkErrors.value = {};
    savingBulk.value = true;

    const payload = {
        facilitators: filledBulkRows.value.map((row) => ({
            name: row.name,
            role: row.role || null,
        })),
    };

    router.post(route('evaluation-forms.facilitators.bulk-store', form.value.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            showBulkModal.value = false;
        },
        onError: (errors: Record<string, string>) => {
            bulkErrors.value = errors;
        },
        onFinish: () => {
            savingBulk.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Evaluation — ${batch.batch}`" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4">
            <!-- Back -->
            <button
                class="flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                @click="router.visit(route('programs.show', batch.program.id))"
            >
                <ArrowLeft class="h-4 w-4" /> Back to Program
            </button>

            <!-- Hero -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-rose-700 via-red-700 to-orange-600 p-6 text-white shadow-xl">
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div class="absolute -right-8 -top-8 h-48 w-48 rounded-full bg-white/5" />
                    <div class="absolute -bottom-12 -right-4 h-64 w-64 rounded-full bg-white/5" />
                </div>
                <div class="relative flex flex-col gap-2">
                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide"
                    >
                        <ClipboardCheck class="h-3.5 w-3.5" /> Program Evaluation
                    </span>
                    <h1 class="text-xl font-bold leading-tight md:text-2xl">{{ batch.program.title }}</h1>
                    <p class="text-sm text-white/80">{{ batch.batch }}</p>
                </div>
            </div>

            <!-- Empty state: set up the form -->
            <div v-if="!form" class="flex flex-col items-center gap-4 rounded-2xl border bg-background p-8 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-100 dark:bg-rose-950/40">
                    <Sparkles class="h-7 w-7 text-rose-600" />
                </div>
                <div>
                    <p class="text-sm font-bold">No evaluation form yet for this batch</p>
                    <p class="mt-1 max-w-md text-xs text-muted-foreground">
                        Set one up to start collecting participant feedback on content, methodology, facilitators, and overall experience — you can
                        edit every question afterward.
                    </p>
                </div>
                <div class="mt-2 flex flex-col items-center gap-2 sm:flex-row">
                    <Button :disabled="creating" class="bg-rose-600 hover:bg-rose-700 dark:text-white" @click="createDefault">
                        <LoaderCircle v-if="creating" class="mr-1 h-4 w-4 animate-spin" />
                        <Sparkles v-else class="mr-1 h-4 w-4" /> Use Default Template
                    </Button>
                    <template v-if="siblingBatchesWithForms.length">
                        <span class="text-xs text-muted-foreground">or</span>
                        <div class="flex items-center gap-2">
                            <Select v-model="cloneFromBatchId">
                                <SelectTrigger class="h-9 w-48 text-xs">
                                    <SelectValue placeholder="Clone from a batch…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="b in siblingBatchesWithForms" :key="b.id" :value="String(b.id)" class="text-xs">
                                        {{ b.batch }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button variant="outline" size="sm" :disabled="creating || !cloneFromBatchId" @click="createFromClone"> Clone </Button>
                        </div>
                    </template>
                </div>
            </div>

            <template v-else>
                <!-- Settings -->
                <div class="flex flex-col gap-4 rounded-2xl border bg-background p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div v-if="!editingSettings" class="flex items-center gap-2">
                                <h2 class="text-sm font-extrabold">{{ form.title }}</h2>
                                <button class="text-muted-foreground transition-colors hover:text-foreground" @click="openEditSettings">
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div v-else class="flex max-w-md flex-col gap-2">
                                <Input v-model="settingsDraft.title" class="h-8 text-xs" placeholder="Form title" />
                                <Textarea
                                    v-model="settingsDraft.intro_text"
                                    class="text-xs"
                                    rows="2"
                                    placeholder="Optional intro text shown to respondents"
                                />
                                <div class="flex items-center gap-2">
                                    <Button
                                        size="sm"
                                        class="h-7 bg-rose-600 text-xs hover:bg-rose-700 dark:text-white"
                                        :disabled="savingSettings"
                                        @click="saveSettings"
                                    >
                                        <Check class="mr-1 h-3 w-3" /> Save
                                    </Button>
                                    <Button size="sm" variant="outline" class="h-7 text-xs" @click="editingSettings = false">
                                        <X class="mr-1 h-3 w-3" /> Cancel
                                    </Button>
                                </div>
                            </div>
                            <p v-if="!editingSettings && form.intro_text" class="mt-1 max-w-md text-xs text-muted-foreground">
                                {{ form.intro_text }}
                            </p>
                            <p class="mt-1 text-[11px] text-muted-foreground">Set up by {{ form.created_by_name ?? 'Unknown' }}</p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-bold transition-colors"
                                :class="
                                    form.is_active
                                        ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                                "
                                @click="toggleActive"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="form.is_active ? 'bg-emerald-500' : 'bg-slate-400'" />
                                {{ form.is_active ? 'Accepting Responses' : 'Closed' }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 border-t pt-3">
                        <code class="max-w-xs truncate rounded-md bg-muted px-2 py-1 text-[11px]">{{ publicUrl }}</code>
                        <Button size="sm" variant="outline" class="h-7 text-xs" @click="copyLink">
                            <Check v-if="linkCopied" class="mr-1 h-3 w-3 text-emerald-600" />
                            <Copy v-else class="mr-1 h-3 w-3" /> {{ linkCopied ? 'Copied!' : 'Copy Link' }}
                        </Button>
                        <a :href="publicUrl" target="_blank" rel="noopener">
                            <Button size="sm" variant="outline" class="h-7 text-xs"> <ExternalLink class="mr-1 h-3 w-3" /> Open Form </Button>
                        </a>
                        <Link :href="route('programs.show', batch.program.id) + '?tab=evaluation'" class="ml-auto">
                            <Button size="sm" variant="outline" class="h-7 border-rose-200 text-xs text-rose-600 hover:bg-rose-50">
                                <BarChart3 class="mr-1 h-3 w-3" /> View Results Dashboard
                            </Button>
                        </Link>
                    </div>

                    <!-- Background image -->
                    <div class="flex items-center gap-3 border-t pt-3">
                        <img :src="previewBackgroundUrl" alt="Evaluation form background" class="h-14 w-20 shrink-0 rounded-lg object-cover" />
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-xs font-bold">
                                <ImageIcon class="h-3.5 w-3.5 text-rose-600" /> Background Image
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                {{ form.background_image_url ? 'Custom background for this batch.' : 'Using the site-wide default background.' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <input
                                ref="backgroundFileInput"
                                type="file"
                                accept="image/jpeg,image/jpg,image/png,image/webp"
                                class="hidden"
                                @change="handleBackgroundFileChange"
                            />
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-7 text-xs"
                                :disabled="uploadingBackground"
                                @click="triggerBackgroundUpload"
                            >
                                <LoaderCircle v-if="uploadingBackground" class="mr-1 h-3 w-3 animate-spin" />
                                <Upload v-else class="mr-1 h-3 w-3" /> Change
                            </Button>
                            <Button
                                v-if="form.background_image_url"
                                size="sm"
                                variant="outline"
                                class="h-7 text-xs"
                                :disabled="removingBackground"
                                @click="resetBackground"
                            >
                                <LoaderCircle v-if="removingBackground" class="mr-1 h-3 w-3 animate-spin" />
                                <RotateCcw v-else class="mr-1 h-3 w-3" /> Reset
                            </Button>
                        </div>
                    </div>

                    <div v-if="isSuperAdmin" class="flex items-center justify-between gap-3 border-t pt-3">
                        <p class="text-[11px] text-muted-foreground">
                            Superadmin only: permanently delete this batch's evaluation form and start over.
                        </p>
                        <Button size="sm" variant="destructive" class="h-7 shrink-0 text-xs" :disabled="deletingForm" @click="deleteForm">
                            <LoaderCircle v-if="deletingForm" class="mr-1 h-3 w-3 animate-spin" />
                            <Trash2 v-else class="mr-1 h-3 w-3" /> Delete Evaluation Form
                        </Button>
                    </div>
                </div>

                <!-- Sections & Questions -->
                <div class="flex flex-col gap-3">
                    <h2 class="flex items-center gap-1.5 text-sm font-extrabold">
                        <ListChecks class="h-4 w-4 text-rose-600" /> Sections & Questions
                    </h2>

                    <div v-for="(section, sIdx) in form.sections" :key="section.id" class="overflow-hidden rounded-2xl border bg-background">
                        <div class="flex items-start justify-between gap-3 border-b bg-rose-50/50 px-5 py-4 dark:bg-rose-950/10">
                            <div class="min-w-0 flex-1">
                                <div v-if="editingSectionId !== section.id" class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold">{{ section.title }}</h3>
                                    <button class="text-muted-foreground transition-colors hover:text-foreground" @click="openEditSection(section)">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <div v-else class="flex max-w-lg flex-col gap-2">
                                    <Input v-model="sectionDraft.title" class="h-8 text-xs" placeholder="Section title" />
                                    <Textarea
                                        v-model="sectionDraft.description"
                                        class="text-xs"
                                        rows="2"
                                        placeholder="Optional description shown under the title"
                                    />
                                    <div class="flex items-center gap-2">
                                        <Button
                                            size="sm"
                                            class="h-7 bg-rose-600 text-xs hover:bg-rose-700 dark:text-white"
                                            @click="saveSection(section)"
                                        >
                                            <Check class="mr-1 h-3 w-3" /> Save
                                        </Button>
                                        <Button size="sm" variant="outline" class="h-7 text-xs" @click="editingSectionId = null">
                                            <X class="mr-1 h-3 w-3" /> Cancel
                                        </Button>
                                    </div>
                                </div>
                                <p v-if="editingSectionId !== section.id && section.description" class="mt-1 text-xs text-muted-foreground">
                                    {{ section.description }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button
                                    class="rounded p-1 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                    :disabled="sIdx === 0"
                                    @click="moveSection(section, 'up')"
                                >
                                    <ChevronUp class="h-4 w-4" />
                                </button>
                                <button
                                    class="rounded p-1 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                    :disabled="sIdx === form.sections.length - 1"
                                    @click="moveSection(section, 'down')"
                                >
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col divide-y">
                            <div
                                v-for="(question, qIdx) in section.questions"
                                :key="question.id"
                                class="flex items-start justify-between gap-3 px-5 py-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold">{{ question.label }}</p>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <Badge variant="outline" class="text-[10px]">{{ TYPE_LABELS[question.type] }}</Badge>
                                        <Badge v-if="question.is_required" variant="outline" class="border-rose-200 text-[10px] text-rose-600"
                                            >Required</Badge
                                        >
                                        <span
                                            v-if="(question.type === 'checkbox' || question.type === 'radio') && question.options"
                                            class="truncate text-[10px] text-muted-foreground"
                                        >
                                            {{ question.options.join(', ') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        class="rounded p-1 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                        :disabled="qIdx === 0"
                                        @click="moveQuestion(question, 'up')"
                                    >
                                        <ChevronUp class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        class="rounded p-1 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                        :disabled="qIdx === section.questions.length - 1"
                                        @click="moveQuestion(question, 'down')"
                                    >
                                        <ChevronDown class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        class="rounded p-1 text-muted-foreground transition-colors hover:text-blue-600"
                                        @click="openEditQuestion(section, question)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        class="rounded p-1 text-muted-foreground transition-colors hover:text-red-600"
                                        @click="deleteQuestion(question)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            <p v-if="!section.questions.length" class="px-5 py-4 text-xs text-muted-foreground">No questions in this section yet.</p>
                        </div>

                        <div class="border-t px-5 py-3">
                            <Button size="sm" variant="outline" class="h-7 text-xs" @click="openAddQuestion(section)">
                                <Plus class="mr-1 h-3 w-3" /> Add Question
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Facilitators -->
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-1.5 text-sm font-extrabold"><Users class="h-4 w-4 text-rose-600" /> Facilitators to Rate</h2>
                        <div class="flex items-center gap-2">
                            <Button size="sm" variant="outline" class="h-7 text-xs" @click="openBulkAdd">
                                <ClipboardPaste class="mr-1 h-3 w-3" /> Bulk Add
                            </Button>
                            <Button size="sm" class="h-7 bg-rose-600 text-xs hover:bg-rose-700 dark:text-white" @click="openAddFacilitator">
                                <Plus class="mr-1 h-3 w-3" /> Add Facilitator
                            </Button>
                        </div>
                    </div>
                    <p class="-mt-2 text-xs text-muted-foreground">
                        Every facilitator added here will be rated separately by each respondent using the Section III questions above.
                    </p>

                    <div v-if="!form.facilitators.length" class="rounded-2xl border border-dashed py-10 text-center text-sm text-muted-foreground">
                        No facilitators added yet.
                    </div>

                    <div v-else class="divide-y overflow-hidden rounded-2xl border bg-background">
                        <div
                            v-for="(facilitator, fIdx) in form.facilitators"
                            :key="facilitator.id"
                            class="flex items-center justify-between gap-3 px-5 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold">{{ facilitator.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ facilitator.role }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button
                                    class="rounded p-1 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                    :disabled="fIdx === 0"
                                    @click="moveFacilitator(facilitator, 'up')"
                                >
                                    <ChevronUp class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    class="rounded p-1 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                    :disabled="fIdx === form.facilitators.length - 1"
                                    @click="moveFacilitator(facilitator, 'down')"
                                >
                                    <ChevronDown class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    class="rounded p-1 text-muted-foreground transition-colors hover:text-blue-600"
                                    @click="openEditFacilitator(facilitator)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    class="rounded p-1 text-muted-foreground transition-colors hover:text-red-600"
                                    @click="deleteFacilitator(facilitator)"
                                >
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
                    <DialogTitle class="flex items-center gap-2"
                        ><ListChecks class="h-4 w-4" /> {{ editingQuestion ? 'Edit Question' : 'Add Question' }}</DialogTitle
                    >
                    <DialogDescription class="text-xs text-muted-foreground">
                        {{ editingQuestion ? 'Update this question.' : 'Add a new question to this section.' }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4 py-2" @submit.prevent="submitQuestion">
                    <div class="grid gap-1">
                        <Label class="text-xs">Question Type <span class="text-red-500">*</span></Label>
                        <Select v-model="questionForm.type">
                            <SelectTrigger class="h-8 text-xs">
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
                        <Textarea
                            v-model="questionForm.options"
                            class="text-xs"
                            rows="3"
                            placeholder="One option per line, e.g.&#10;just right&#10;too slow&#10;too fast"
                        />
                        <p v-if="questionErrors.options" class="text-xs text-red-500">{{ questionErrors.options }}</p>
                    </div>

                    <label class="flex w-fit cursor-pointer items-center gap-2 text-xs">
                        <input type="checkbox" v-model="questionForm.is_required" class="h-3.5 w-3.5" />
                        Required question
                    </label>
                </form>

                <div class="flex justify-end gap-2 border-t pt-2">
                    <Button type="button" variant="outline" size="sm" @click="showQuestionModal = false">Cancel</Button>
                    <Button
                        type="button"
                        size="sm"
                        class="bg-rose-600 hover:bg-rose-700 dark:text-white"
                        :disabled="savingQuestion"
                        @click="submitQuestion"
                    >
                        <LoaderCircle v-if="savingQuestion" class="mr-1 h-3 w-3 animate-spin" />
                        {{ editingQuestion ? 'Save Changes' : 'Add Question' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Facilitator Dialog -->
        <Dialog :open="showFacilitatorModal" @update:open="showFacilitatorModal = $event">
            <DialogContent class="max-w-sm !rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"
                        ><Users class="h-4 w-4" /> {{ editingFacilitator ? 'Edit Facilitator' : 'Add Facilitator' }}</DialogTitle
                    >
                </DialogHeader>

                <form class="grid gap-4 py-2" @submit.prevent="submitFacilitator">
                    <div class="grid gap-1">
                        <Label class="text-xs">Name <span class="text-red-500">*</span></Label>
                        <Input v-model="facilitatorForm.name" class="h-8 text-xs" placeholder="Full name" />
                        <p v-if="facilitatorErrors.name" class="text-xs text-red-500">{{ facilitatorErrors.name }}</p>
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs">Role</Label>
                        <Input v-model="facilitatorForm.role" class="h-8 text-xs" placeholder="e.g. Resource Person/Facilitator" />
                    </div>
                </form>

                <div class="flex justify-end gap-2 border-t pt-2">
                    <Button type="button" variant="outline" size="sm" @click="showFacilitatorModal = false">Cancel</Button>
                    <Button
                        type="button"
                        size="sm"
                        class="bg-rose-600 hover:bg-rose-700 dark:text-white"
                        :disabled="savingFacilitator"
                        @click="submitFacilitator"
                    >
                        <LoaderCircle v-if="savingFacilitator" class="mr-1 h-3 w-3 animate-spin" />
                        {{ editingFacilitator ? 'Save Changes' : 'Add Facilitator' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Bulk Add Facilitators Dialog -->
        <Dialog :open="showBulkModal" @update:open="showBulkModal = $event">
            <DialogContent class="max-w-2xl !rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><ClipboardPaste class="h-4 w-4" /> Bulk Add Facilitators</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Paste directly from Excel/Google Sheets into any cell below — the grid fills in and grows rows automatically. You can also
                        type into cells manually. Empty rows are ignored.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2 py-2">
                    <p v-if="bulkErrors.facilitators" class="text-xs text-red-500">{{ bulkErrors.facilitators }}</p>

                    <div class="overflow-hidden rounded-xl border">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b bg-muted/40">
                                    <th class="w-8 px-2 py-2 text-left font-bold uppercase tracking-wide text-muted-foreground">#</th>
                                    <th class="px-2 py-2 text-left font-bold uppercase tracking-wide text-muted-foreground">Name</th>
                                    <th class="px-2 py-2 text-left font-bold uppercase tracking-wide text-muted-foreground">Role</th>
                                    <th class="w-8 px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(row, i) in bulkRows" :key="i">
                                    <td class="px-2 py-1 text-center text-muted-foreground">{{ i + 1 }}</td>
                                    <td class="px-1 py-1">
                                        <input
                                            v-model="row.name"
                                            type="text"
                                            class="w-full rounded-md border bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-rose-500"
                                            placeholder="Full name"
                                            @paste="handleBulkPaste($event, i, 'name')"
                                        />
                                    </td>
                                    <td class="px-1 py-1">
                                        <input
                                            v-model="row.role"
                                            type="text"
                                            class="w-full rounded-md border bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-rose-500"
                                            placeholder="Role (optional)"
                                            @paste="handleBulkPaste($event, i, 'role')"
                                        />
                                    </td>
                                    <td class="px-1 py-1 text-center">
                                        <button
                                            type="button"
                                            class="text-muted-foreground transition-colors hover:text-red-600"
                                            title="Remove row"
                                            @click="removeBulkRow(i)"
                                        >
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            class="flex w-fit items-center gap-1 text-xs font-semibold text-rose-600 transition-colors hover:text-rose-700"
                            @click="addBulkRow"
                        >
                            <Plus class="h-3.5 w-3.5" /> Add Row
                        </button>
                        <p class="text-[11px] text-muted-foreground">{{ filledBulkRows.length }} facilitator(s) will be added</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t pt-2">
                    <Button type="button" variant="outline" size="sm" @click="showBulkModal = false">Cancel</Button>
                    <Button
                        type="button"
                        size="sm"
                        class="bg-rose-600 hover:bg-rose-700 dark:text-white"
                        :disabled="savingBulk || !filledBulkRows.length"
                        @click="submitBulkFacilitators"
                    >
                        <LoaderCircle v-if="savingBulk" class="mr-1 h-3 w-3 animate-spin" />
                        Add {{ filledBulkRows.length }} Facilitator{{ filledBulkRows.length === 1 ? '' : 's' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
