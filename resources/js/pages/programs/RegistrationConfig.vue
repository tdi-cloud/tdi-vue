<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import SearchEmptyState from '@/components/SearchEmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { SETTINGS_CARD } from '@/lib/cardStyles';
import { suggestionFrom } from '@/lib/http';
import { REGISTRATION_THEMES } from '@/lib/registrationThemes';
import RegistrationReviewPanel, { type RegistrationRow } from '@/pages/programs/RegistrationReviewPanel.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    CalendarClock,
    Check,
    ChevronDown,
    ChevronUp,
    Copy,
    Crown,
    ExternalLink,
    Eye,
    Image as ImageIcon,
    Link2,
    ListChecks,
    LoaderCircle,
    Lock,
    Palette,
    Pencil,
    Plus,
    Save,
    Search,
    Settings2,
    ShieldCheck,
    Trash2,
    Upload,
    UserCog,
    UserMinus,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const { confirmDialog } = useConfirm();

type QuestionType = 'text' | 'textarea' | 'radio' | 'checkbox' | 'select';

interface Question {
    id: number;
    label: string;
    help_text: string | null;
    type: QuestionType;
    options: string[] | null;
    allow_other: boolean;
    is_required: boolean;
    sort_order: number;
}

interface RegistrationFormData {
    id: number;
    slug: string;
    title: string;
    intro_text: string | null;
    theme: string;
    is_open: boolean;
    closes_at_input: string | null;
    closes_at_label: string | null;
    slot_limit: number | null;
    slots_taken: number;
    closed_reason: 'not_open' | 'deadline' | 'full' | null;
    background_image_url: string | null;
    hide_background: boolean;
    created_by_name: string | null;
    owner: { name: string | null; empcode: string } | null;
    editors: FormEditor[];
    questions: Question[];
}

interface FormEditor {
    id: number;
    name: string;
    empcode: string | null;
    has_admin_access: boolean;
    added_by_name: string | null;
    added_at: string | null;
}

interface EditorCandidate {
    id: number;
    name: string;
    empcode: string | null;
    // Why they can't be added (e.g. "Owner"), or null when they can.
    unavailable: string | null;
}

const props = defineProps<{
    batch: { id: number; batch: string; program_code: string; date_start: string | null; date_end: string | null; venue: string | null };
    program: { id: number; title: string; pax: string | null } | null;
    registrationForm: RegistrationFormData | null;
    registrations: RegistrationRow[];
    canCreateForm: boolean;
    canEdit: boolean;
    canManageEditors: boolean;
    defaultBackgroundUrl: string;
    themes: string[];
    fieldOptions: { region: string[]; office_division: Record<string, string[]>; sex: string[]; plantilla_status: string[] };
}>();

const form = computed(() => props.registrationForm);
const canManage = computed(() => props.canEdit);

const pendingCount = computed(() => props.registrations.filter((r) => r.status === 'pending').length);

// Opens on the review list when people are waiting, otherwise on the form settings.
const activeTab = ref<'registrations' | 'settings'>(props.registrations.length ? 'registrations' : 'settings');

const TYPE_LABELS: Record<QuestionType, string> = {
    text: 'Short answer',
    textarea: 'Paragraph',
    radio: 'Single choice',
    checkbox: 'Checkboxes',
    select: 'Dropdown',
};

const CHOICE_TYPES: QuestionType[] = ['radio', 'checkbox', 'select'];

const publicUrl = computed(() => (form.value ? route('join.show', form.value.slug) : ''));
const previewUrl = computed(() => (form.value ? route('join.show', { slug: form.value.slug, preview: 1 }) : ''));

const statusNote = computed(() => {
    if (!form.value?.is_open) return null;
    if (form.value.closed_reason === 'deadline') return 'The closing date has passed, so the public form shows as closed.';
    if (form.value.closed_reason === 'full') return 'All slots are taken, so the public form shows as full.';
    return null;
});

/* ── Set up form (empty state) ───────────────────────────────────────────── */
const creating = ref(false);

function createForm() {
    creating.value = true;
    router.post(
        route('batches.registration-form.store', props.batch.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                creating.value = false;
            },
        },
    );
}

/* ── Settings ─────────────────────────────────────────────────────────────── */
interface SettingsPayload {
    title: string;
    intro_text: string | null;
    theme: string;
    is_open: boolean;
    closes_at: string | null;
    slot_limit: number | null;
    hide_background: boolean;
}

const savingSettings = ref(false);
const settingsErrors = ref<Record<string, string>>({});

function currentSettings(): SettingsPayload {
    const f = form.value!;
    return {
        title: f.title,
        intro_text: f.intro_text,
        theme: f.theme,
        is_open: f.is_open,
        closes_at: f.closes_at_input,
        slot_limit: f.slot_limit,
        hide_background: f.hide_background,
    };
}

function saveSettings(changes: Partial<SettingsPayload>, onSuccess?: () => void) {
    if (!form.value) return;
    savingSettings.value = true;
    settingsErrors.value = {};
    router.put(
        route('registration-forms.update', form.value.id),
        { ...currentSettings(), ...changes },
        {
            preserveScroll: true,
            onSuccess: () => onSuccess?.(),
            onError: (errors) => {
                settingsErrors.value = errors;
            },
            onFinish: () => {
                savingSettings.value = false;
            },
        },
    );
}

// Title and intro text
const editingText = ref(false);
const textDraft = ref({ title: '', intro_text: '' });

function openEditText() {
    if (!form.value) return;
    textDraft.value = { title: form.value.title, intro_text: form.value.intro_text ?? '' };
    editingText.value = true;
}

function saveText() {
    saveSettings({ title: textDraft.value.title, intro_text: textDraft.value.intro_text || null }, () => {
        editingText.value = false;
    });
}

function toggleOpen() {
    if (!form.value) return;
    saveSettings({ is_open: !form.value.is_open });
}

// Closing date and slot limit
const scheduleDraft = ref({ closes_at: '', slot_limit: '' as string | number });

watch(
    form,
    (f) => {
        scheduleDraft.value = { closes_at: f?.closes_at_input ?? '', slot_limit: f?.slot_limit ?? '' };
    },
    { immediate: true },
);

const scheduleChanged = computed(
    () =>
        !!form.value &&
        (scheduleDraft.value.closes_at !== (form.value.closes_at_input ?? '') ||
            String(scheduleDraft.value.slot_limit ?? '') !== String(form.value.slot_limit ?? '')),
);

function saveSchedule() {
    const limit = String(scheduleDraft.value.slot_limit ?? '').trim();
    saveSettings({
        closes_at: scheduleDraft.value.closes_at || null,
        slot_limit: limit === '' ? null : Number(limit),
    });
}

function chooseTheme(theme: string) {
    if (!form.value || form.value.theme === theme) return;
    saveSettings({ theme });
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

/* ── Background image ────────────────────────────────────────────────────── */
const backgroundFileInput = ref<HTMLInputElement | null>(null);
const uploadingBackground = ref(false);
const removingBackground = ref(false);

type BackgroundChoice = 'default' | 'custom' | 'none';

const BACKGROUND_CHOICES: { value: BackgroundChoice; label: string }[] = [
    { value: 'default', label: 'Site default' },
    { value: 'custom', label: 'Custom image' },
    { value: 'none', label: 'No background' },
];

const backgroundChoice = computed<BackgroundChoice>(() => {
    if (form.value?.hide_background) return 'none';
    return form.value?.background_image_url ? 'custom' : 'default';
});

const previewBackgroundUrl = computed(() => {
    if (backgroundChoice.value === 'none') return null;
    return form.value?.background_image_url ?? props.defaultBackgroundUrl;
});

const backgroundNote = computed(() => {
    switch (backgroundChoice.value) {
        case 'none':
            return 'No background image. The form shows on a plain light gray page.';
        case 'custom':
            return 'Custom background for this batch.';
        default:
            return 'Using the site-wide default background.';
    }
});

async function chooseBackground(choice: BackgroundChoice) {
    if (!form.value || choice === backgroundChoice.value) return;

    if (choice === 'none') {
        saveSettings({ hide_background: true });
        return;
    }

    if (choice === 'custom') {
        // A custom image that was only hidden comes back without uploading it again.
        if (form.value.background_image_url) {
            saveSettings({ hide_background: false });
        } else {
            backgroundFileInput.value?.click();
        }
        return;
    }

    if (form.value.background_image_url) {
        await resetBackground();
    } else {
        saveSettings({ hide_background: false });
    }
}

function handleBackgroundFileChange(e: Event) {
    if (!form.value) return;
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;

    uploadingBackground.value = true;
    router.post(
        route('registration-forms.background.upload', form.value.id),
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
    router.delete(route('registration-forms.background.destroy', form.value.id), {
        preserveScroll: true,
        onFinish: () => {
            removingBackground.value = false;
        },
    });
}

/* ── Additional questions ─────────────────────────────────────────────────── */
const showQuestionModal = ref(false);
const editingQuestion = ref<Question | null>(null);

interface QuestionDraft {
    type: QuestionType;
    label: string;
    help_text: string;
    options: string[];
    allow_other: boolean;
    is_required: boolean;
}

function blankQuestion(): QuestionDraft {
    return { type: 'text', label: '', help_text: '', options: ['Option 1'], allow_other: false, is_required: false };
}

const questionForm = ref<QuestionDraft>(blankQuestion());
const questionErrors = ref<Record<string, string>>({});
const savingQuestion = ref(false);

// "Other" (a typed answer) works for single choice and checkboxes, like Google Forms.
const canAddOther = computed(() => questionForm.value.type === 'radio' || questionForm.value.type === 'checkbox');

function openAddQuestion() {
    editingQuestion.value = null;
    questionForm.value = blankQuestion();
    questionErrors.value = {};
    showQuestionModal.value = true;
}

function openEditQuestion(question: Question) {
    editingQuestion.value = question;
    questionForm.value = {
        type: question.type,
        label: question.label,
        help_text: question.help_text ?? '',
        options: question.options?.length ? [...question.options] : ['Option 1'],
        allow_other: question.allow_other,
        is_required: question.is_required,
    };
    questionErrors.value = {};
    showQuestionModal.value = true;
}

/* Option rows: each option is its own input, as in Google Forms. */
const optionInputs = ref<HTMLInputElement[]>([]);

async function focusOption(index: number) {
    await nextTick();
    optionInputs.value[index]?.focus();
    optionInputs.value[index]?.select();
}

function addOption(afterIndex?: number) {
    const options = questionForm.value.options;
    const at = afterIndex === undefined ? options.length : afterIndex + 1;
    options.splice(at, 0, `Option ${options.length + 1}`);
    focusOption(at);
}

function removeOption(index: number) {
    const options = questionForm.value.options;
    if (options.length <= 1) return;
    options.splice(index, 1);
}

function submitQuestion() {
    if (!form.value) return;
    questionErrors.value = {};

    const payload: Record<string, unknown> = {
        type: questionForm.value.type,
        label: questionForm.value.label,
        help_text: questionForm.value.help_text || null,
        allow_other: canAddOther.value && questionForm.value.allow_other,
        is_required: questionForm.value.is_required,
    };
    if (CHOICE_TYPES.includes(questionForm.value.type)) {
        // Drop blank rows first, so error positions match the rows on screen.
        questionForm.value.options = questionForm.value.options.map((o) => o.trim()).filter(Boolean);
        if (!questionForm.value.options.length) {
            questionForm.value.options = [''];
            questionErrors.value = { options: 'Add at least one option.' };
            return;
        }
        payload.options = questionForm.value.options;
    }

    savingQuestion.value = true;

    const options = {
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
        router.put(route('registration-questions.update', editingQuestion.value.id), payload, options);
    } else {
        router.post(route('registration-forms.questions.store', form.value.id), payload, options);
    }
}

async function deleteQuestion(question: Question) {
    if (!(await confirmDialog(`Delete the question "${question.label}"? Answers already submitted keep their copy.`))) return;
    router.delete(route('registration-questions.destroy', question.id), { preserveScroll: true });
}

function moveQuestion(question: Question, dir: 'up' | 'down') {
    router.post(route(`registration-questions.move-${dir}`, question.id), {}, { preserveScroll: true, preserveState: true });
}

/* ── Who can edit ─────────────────────────────────────────────────────────── */
const ownerName = computed(() => form.value?.owner?.name ?? null);

// For admins who can only view the form.
const readOnlyNote = computed(() =>
    ownerName.value
        ? `Only ${ownerName.value} or a superadmin can change this form. Ask them to add you as an editor.`
        : 'Only a superadmin can change this form. Ask one to add you as an editor.',
);

const editorOnlyNote = computed(() =>
    ownerName.value
        ? `You're an editor of this form. Only ${ownerName.value} or a superadmin can add or remove editors.`
        : "You're an editor of this form. Only a superadmin can add or remove editors.",
);

const editorQuery = ref('');
const editorResults = ref<EditorCandidate[]>([]);
const editorSearchOpen = ref(false);
const searchingEditors = ref(false);
const editorSuggestion = ref<string | null>(null);
const editorError = ref('');
const addingEditorId = ref<number | null>(null);
const removingEditorId = ref<number | null>(null);
let editorSearchTimer: ReturnType<typeof setTimeout> | undefined;

watch(editorQuery, (value) => {
    clearTimeout(editorSearchTimer);
    editorError.value = '';
    if (value.trim().length < 2) {
        editorResults.value = [];
        editorSearchOpen.value = false;
        return;
    }
    editorSearchTimer = setTimeout(searchEditors, 300);
});

async function searchEditors() {
    if (!form.value) return;
    searchingEditors.value = true;
    editorSearchOpen.value = true;
    try {
        const response = await axios.get(route('registration-forms.editors.search', form.value.id), { params: { q: editorQuery.value } });
        editorResults.value = response.data;
        editorSuggestion.value = suggestionFrom(response);
    } catch {
        editorResults.value = [];
    } finally {
        searchingEditors.value = false;
    }
}

function addEditor(candidate: EditorCandidate) {
    if (!form.value || candidate.unavailable) return;
    addingEditorId.value = candidate.id;
    editorError.value = '';
    router.post(
        route('registration-forms.editors.store', form.value.id),
        { user_id: candidate.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                editorQuery.value = '';
            },
            onError: (errors) => {
                editorError.value = errors.user_id ?? Object.values(errors)[0] ?? '';
            },
            onFinish: () => {
                addingEditorId.value = null;
            },
        },
    );
}

async function removeEditor(editor: FormEditor) {
    const confirmed = await confirmDialog(
        `Remove ${editor.name} as an editor? They will no longer be able to change this form or review its registrations.`,
        {
            title: 'Remove editor?',
            confirmText: 'Remove',
        },
    );
    if (!confirmed) return;

    removingEditorId.value = editor.id;
    router.delete(route('registration-form-editors.destroy', editor.id), {
        preserveScroll: true,
        onFinish: () => {
            removingEditorId.value = null;
        },
    });
}

function backToProgram() {
    if (props.program) {
        router.visit(route('programs.show', props.program.id));
    } else {
        window.history.back();
    }
}
</script>

<template>
    <Head :title="`Registration — ${batch.batch}`" />

    <AppLayout>
        <div class="flex flex-1 flex-col gap-5 p-4">
            <!-- Back -->
            <button
                class="flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                @click="backToProgram"
            >
                <ArrowLeft class="h-4 w-4" /> Back to Program
            </button>

            <!-- Hero -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-800 via-blue-700 to-sky-600 p-6 text-white shadow-xl">
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div class="absolute -right-8 -top-8 h-48 w-48 rounded-full bg-white/5" />
                    <div class="absolute -bottom-12 -right-4 h-64 w-64 rounded-full bg-white/5" />
                </div>
                <div class="relative flex flex-col gap-2">
                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide"
                    >
                        <UserPlus class="h-3.5 w-3.5" /> Registration Form
                    </span>
                    <h1 class="text-xl font-bold leading-tight md:text-2xl">{{ program?.title }}</h1>
                    <p class="text-sm text-white/80">{{ batch.batch }}</p>
                </div>
            </div>

            <!-- Read-only notice -->
            <p
                v-if="form ? !canManage : !canCreateForm"
                class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300"
            >
                <Lock class="mt-0.5 h-4 w-4 shrink-0" />
                <span v-if="form">You can view this form, but you can't change it or review its registrations. {{ readOnlyNote }}</span>
                <span v-else>Only the admin who created this program or a superadmin can set up a registration form for this batch.</span>
            </p>

            <!-- Empty state: set up the form -->
            <EmptyState
                v-if="!form"
                :icon="UserPlus"
                title="No registration form yet for this batch"
                description="Set one up to share a public link where people register for this batch. You review every registration before anyone becomes a participant."
            >
                <Button v-if="canCreateForm" :disabled="creating" class="bg-blue-600 hover:bg-blue-700 dark:text-white" @click="createForm">
                    <LoaderCircle v-if="creating" class="mr-1 h-4 w-4 animate-spin" />
                    <UserPlus v-else class="mr-1 h-4 w-4" /> {{ creating ? 'Setting up…' : 'Set Up Registration Form' }}
                </Button>
            </EmptyState>

            <template v-else>
                <!-- Tabs -->
                <div class="flex gap-1 border-b" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'registrations'"
                        class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-semibold transition-colors"
                        :class="
                            activeTab === 'registrations'
                                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = 'registrations'"
                    >
                        <Users class="h-4 w-4" /> Registrations
                        <span v-if="pendingCount" class="rounded-full bg-blue-600 px-1.5 text-[10px] font-bold leading-4 text-white">{{
                            pendingCount
                        }}</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'settings'"
                        class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-semibold transition-colors"
                        :class="
                            activeTab === 'settings'
                                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = 'settings'"
                    >
                        <Settings2 class="h-4 w-4" /> Form Settings
                    </button>
                </div>

                <RegistrationReviewPanel
                    v-if="activeTab === 'registrations'"
                    :form-id="form.id"
                    :registrations="registrations"
                    :can-manage="canManage"
                    :field-options="fieldOptions"
                />

                <!-- Settings — one idea per card -->
                <div v-show="activeTab === 'settings'" class="flex flex-col gap-4">
                    <!-- Form details and status -->
                    <Card :class="SETTINGS_CARD.blue" class="p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div v-if="!editingText" class="flex items-center gap-2">
                                    <h2 class="text-sm font-extrabold">{{ form.title }}</h2>
                                    <button
                                        v-if="canManage"
                                        class="text-muted-foreground transition-colors hover:text-foreground"
                                        aria-label="Edit title and instructions"
                                        @click="openEditText"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <div v-else class="flex max-w-md flex-col gap-2">
                                    <Input v-model="textDraft.title" class="h-8 text-xs" placeholder="Form title" />
                                    <Textarea
                                        v-model="textDraft.intro_text"
                                        class="text-xs"
                                        rows="3"
                                        placeholder="Optional instructions shown at the top of the form"
                                    />
                                    <p v-if="settingsErrors.title" class="text-xs text-red-500">{{ settingsErrors.title }}</p>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            size="sm"
                                            class="h-7 bg-blue-600 text-xs hover:bg-blue-700 dark:text-white"
                                            :disabled="savingSettings"
                                            @click="saveText"
                                        >
                                            <Check class="mr-1 h-3 w-3" /> Save
                                        </Button>
                                        <Button size="sm" variant="outline" class="h-7 text-xs" @click="editingText = false">
                                            <X class="mr-1 h-3 w-3" /> Cancel
                                        </Button>
                                    </div>
                                </div>
                                <p v-if="!editingText && form.intro_text" class="mt-1 max-w-md whitespace-pre-line text-xs text-muted-foreground">
                                    {{ form.intro_text }}
                                </p>
                                <p class="mt-1 text-[11px] text-muted-foreground">Set up by {{ form.created_by_name ?? 'Unknown' }}</p>
                            </div>

                            <div class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-bold transition-colors disabled:cursor-default"
                                    :class="
                                        form.is_open
                                            ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                                    "
                                    :disabled="!canManage || savingSettings"
                                    @click="toggleOpen"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="form.is_open ? 'bg-emerald-500' : 'bg-slate-400'" />
                                    {{ form.is_open ? 'Accepting Registrations' : 'Closed' }}
                                </button>
                                <p v-if="statusNote" class="max-w-xs text-[11px] text-amber-600 dark:text-amber-400 sm:text-right">
                                    {{ statusNote }}
                                </p>
                            </div>
                        </div>
                    </Card>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <!-- Closing date and slots -->
                        <Card :class="SETTINGS_CARD.blue" class="flex flex-col gap-4 p-5">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 ring-1 ring-blue-200/70 dark:bg-blue-900/50 dark:text-blue-300 dark:ring-blue-800/60"
                                >
                                    <CalendarClock class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-sm font-semibold">Schedule &amp; Slots</h2>
                                    <p class="text-[11px] text-muted-foreground">When the form closes and how many people can register</p>
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid content-start gap-1">
                                    <Label for="closes-at" class="text-xs">Closing Date &amp; Time</Label>
                                    <Input
                                        id="closes-at"
                                        v-model="scheduleDraft.closes_at"
                                        type="datetime-local"
                                        class="h-8 text-xs"
                                        :disabled="!canManage"
                                    />
                                    <p v-if="settingsErrors.closes_at" class="text-xs text-red-500">{{ settingsErrors.closes_at }}</p>
                                    <p v-else class="text-[11px] text-muted-foreground">Leave empty to keep it open until you close it.</p>
                                </div>
                                <div class="grid content-start gap-1">
                                    <Label for="slot-limit" class="text-xs">Registration Slots</Label>
                                    <Input
                                        id="slot-limit"
                                        v-model="scheduleDraft.slot_limit"
                                        type="number"
                                        min="1"
                                        class="h-8 text-xs"
                                        placeholder="No limit"
                                        :disabled="!canManage"
                                    />
                                    <p v-if="settingsErrors.slot_limit" class="text-xs text-red-500">{{ settingsErrors.slot_limit }}</p>
                                    <p v-else class="text-[11px] text-muted-foreground">
                                        {{ form.slots_taken }} taken{{ form.slot_limit ? ` of ${form.slot_limit}` : '' }} (pending + approved).
                                        <template v-if="program?.pax"> Program pax: {{ program.pax }}.</template>
                                    </p>
                                </div>
                            </div>
                            <div v-if="canManage" class="mt-auto flex justify-end">
                                <Button
                                    size="sm"
                                    class="h-8 bg-blue-600 text-xs hover:bg-blue-700 dark:text-white"
                                    :disabled="!scheduleChanged || savingSettings"
                                    @click="saveSchedule"
                                >
                                    <Save class="mr-1 h-3 w-3" /> Save
                                </Button>
                            </div>
                        </Card>

                        <!-- Public link -->
                        <Card :class="SETTINGS_CARD.blue" class="flex flex-col gap-4 p-5">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 ring-1 ring-blue-200/70 dark:bg-blue-900/50 dark:text-blue-300 dark:ring-blue-800/60"
                                >
                                    <Link2 class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-sm font-semibold">Share</h2>
                                    <p class="text-[11px] text-muted-foreground">Send this link to the people who should register</p>
                                </div>
                            </div>
                            <code class="block truncate rounded-lg bg-muted px-3 py-2 text-[11px]" :title="publicUrl">{{ publicUrl }}</code>
                            <div class="mt-auto flex flex-wrap gap-2">
                                <Button size="sm" variant="outline" class="h-8 text-xs" @click="copyLink">
                                    <Check v-if="linkCopied" class="mr-1 h-3 w-3 text-emerald-600" />
                                    <Copy v-else class="mr-1 h-3 w-3" /> {{ linkCopied ? 'Copied!' : 'Copy Link' }}
                                </Button>
                                <a :href="publicUrl" target="_blank" rel="noopener">
                                    <Button size="sm" variant="outline" class="h-8 text-xs"><ExternalLink class="mr-1 h-3 w-3" /> Open Form</Button>
                                </a>
                                <a v-if="canManage" :href="previewUrl" target="_blank" rel="noopener">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-8 border-blue-200 text-xs text-blue-600 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-400"
                                    >
                                        <Eye class="mr-1 h-3 w-3" /> Preview
                                    </Button>
                                </a>
                            </div>
                        </Card>

                        <!-- Theme -->
                        <Card :class="SETTINGS_CARD.blue" class="flex flex-col gap-4 p-5">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 ring-1 ring-blue-200/70 dark:bg-blue-900/50 dark:text-blue-300 dark:ring-blue-800/60"
                                >
                                    <Palette class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-sm font-semibold">Color Theme</h2>
                                    <p class="text-[11px] text-muted-foreground">The colors of the public form</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="key in themes"
                                    :key="key"
                                    type="button"
                                    class="flex items-center gap-2 rounded-xl border px-2.5 py-1.5 text-xs font-semibold transition disabled:cursor-default"
                                    :class="form.theme === key ? 'border-blue-500 ring-2 ring-blue-100 dark:ring-blue-900' : 'hover:bg-muted'"
                                    :disabled="!canManage || savingSettings"
                                    :aria-pressed="form.theme === key"
                                    @click="chooseTheme(key)"
                                >
                                    <span class="h-5 w-8 rounded-md" :class="REGISTRATION_THEMES[key]?.swatch" />
                                    {{ REGISTRATION_THEMES[key]?.label ?? key }}
                                    <Check v-if="form.theme === key" class="h-3.5 w-3.5 text-blue-600" />
                                </button>
                            </div>
                        </Card>

                        <!-- Background image -->
                        <Card :class="SETTINGS_CARD.blue" class="flex flex-col gap-4 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 ring-1 ring-blue-200/70 dark:bg-blue-900/50 dark:text-blue-300 dark:ring-blue-800/60"
                                    >
                                        <ImageIcon class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <h2 class="text-sm font-semibold">Background Image</h2>
                                        <p class="text-[11px] text-muted-foreground">Shown behind the public form</p>
                                    </div>
                                </div>
                                <Button
                                    v-if="canManage && backgroundChoice === 'custom'"
                                    size="sm"
                                    variant="outline"
                                    class="h-8 shrink-0 text-xs"
                                    :disabled="uploadingBackground"
                                    @click="backgroundFileInput?.click()"
                                >
                                    <LoaderCircle v-if="uploadingBackground" class="mr-1 h-3 w-3 animate-spin" />
                                    <Upload v-else class="mr-1 h-3 w-3" /> Change Image
                                </Button>
                            </div>
                            <div class="flex items-center gap-4">
                                <img
                                    v-if="previewBackgroundUrl"
                                    :src="previewBackgroundUrl"
                                    alt="Registration form background"
                                    class="h-16 w-24 shrink-0 rounded-lg object-cover ring-1 ring-border"
                                />
                                <div
                                    v-else
                                    class="flex h-16 w-24 shrink-0 items-center justify-center rounded-lg border border-dashed bg-muted/50 text-[10px] font-semibold text-muted-foreground"
                                >
                                    None
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col gap-2">
                                    <div v-if="canManage" class="flex flex-wrap items-center gap-1.5" role="radiogroup" aria-label="Background image">
                                        <button
                                            v-for="choice in BACKGROUND_CHOICES"
                                            :key="choice.value"
                                            type="button"
                                            role="radio"
                                            :aria-checked="backgroundChoice === choice.value"
                                            class="rounded-full border px-3 py-1 text-xs font-semibold transition-colors disabled:cursor-default"
                                            :class="
                                                backgroundChoice === choice.value
                                                    ? 'border-blue-600 bg-blue-600 text-white'
                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                            "
                                            :disabled="savingSettings || uploadingBackground || removingBackground"
                                            @click="chooseBackground(choice.value)"
                                        >
                                            {{ choice.label }}
                                        </button>
                                        <LoaderCircle
                                            v-if="removingBackground || (uploadingBackground && backgroundChoice !== 'custom')"
                                            class="h-4 w-4 animate-spin text-muted-foreground"
                                        />
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">{{ backgroundNote }}</p>
                                </div>
                            </div>
                            <input
                                v-if="canManage"
                                ref="backgroundFileInput"
                                type="file"
                                accept="image/jpeg,image/jpg,image/png,image/webp"
                                class="hidden"
                                @change="handleBackgroundFileChange"
                            />
                        </Card>
                    </div>
                </div>

                <!-- Questions -->
                <Card v-show="activeTab === 'settings'" :class="SETTINGS_CARD.blue" class="flex flex-col gap-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 ring-1 ring-blue-200/70 dark:bg-blue-900/50 dark:text-blue-300 dark:ring-blue-800/60"
                            >
                                <ListChecks class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold">Form Questions</h2>
                                <p class="text-[11px] text-muted-foreground">Asked after the standard registration steps</p>
                            </div>
                        </div>
                        <Button
                            v-if="canManage"
                            size="sm"
                            class="h-8 shrink-0 bg-blue-600 text-xs hover:bg-blue-700 dark:text-white"
                            @click="openAddQuestion"
                        >
                            <Plus class="mr-1 h-3 w-3" /> Add Question
                        </Button>
                    </div>

                    <!-- Fixed steps -->
                    <div class="rounded-xl bg-blue-50/60 px-4 py-3.5 dark:bg-blue-950/20">
                        <p class="text-xs font-semibold">Always included</p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            These steps can't be removed, because approving a registration needs this information.
                        </p>
                        <ul class="mt-2 grid gap-1 text-xs sm:grid-cols-2">
                            <li class="flex items-center gap-1.5"><Check class="h-3 w-3 text-blue-600" /> Find your name in the employee list</li>
                            <li class="flex items-center gap-1.5"><Check class="h-3 w-3 text-blue-600" /> Employee information (or manual entry)</li>
                            <li class="flex items-center gap-1.5"><Check class="h-3 w-3 text-blue-600" /> Email address and contact number</li>
                            <li class="flex items-center gap-1.5"><Check class="h-3 w-3 text-blue-600" /> Data Privacy Act consent</li>
                        </ul>
                    </div>

                    <EmptyState
                        v-if="!form.questions.length"
                        compact
                        class="rounded-xl border border-dashed"
                        title="No additional questions yet."
                        :description="
                            canManage
                                ? 'Add questions here if you need more information from registrants, e.g. dietary needs or shirt size.'
                                : undefined
                        "
                    />

                    <div v-else class="divide-y overflow-hidden rounded-xl border">
                        <div v-for="(question, qIdx) in form.questions" :key="question.id" class="flex items-start justify-between gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold">{{ question.label }}</p>
                                <p v-if="question.help_text" class="mt-0.5 text-[11px] text-muted-foreground">{{ question.help_text }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <Badge variant="outline" class="text-[10px]">{{ TYPE_LABELS[question.type] }}</Badge>
                                    <Badge
                                        v-if="question.is_required"
                                        variant="outline"
                                        class="border-blue-200 text-[10px] text-blue-600 dark:border-blue-900 dark:text-blue-400"
                                    >
                                        Required
                                    </Badge>
                                    <span v-if="question.options?.length" class="truncate text-[10px] text-muted-foreground">
                                        {{ [...question.options, ...(question.allow_other ? ['Other…'] : [])].join(', ') }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="canManage" class="flex shrink-0 items-center gap-1">
                                <button
                                    class="rounded p-1 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                    :disabled="qIdx === 0"
                                    aria-label="Move up"
                                    @click="moveQuestion(question, 'up')"
                                >
                                    <ChevronUp class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    class="rounded p-1 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                    :disabled="qIdx === form.questions.length - 1"
                                    aria-label="Move down"
                                    @click="moveQuestion(question, 'down')"
                                >
                                    <ChevronDown class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    class="rounded p-1 text-muted-foreground transition-colors hover:text-blue-600"
                                    aria-label="Edit question"
                                    @click="openEditQuestion(question)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    class="rounded p-1 text-muted-foreground transition-colors hover:text-red-600"
                                    aria-label="Delete question"
                                    @click="deleteQuestion(question)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Who can edit -->
                <Card v-show="activeTab === 'settings'" :class="SETTINGS_CARD.blue" class="flex flex-col gap-4 p-5">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 ring-1 ring-blue-200/70 dark:bg-blue-900/50 dark:text-blue-300 dark:ring-blue-800/60"
                        >
                            <ShieldCheck class="h-4 w-4" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold">Who Can Edit</h2>
                            <p class="text-[11px] text-muted-foreground">
                                The people below and every superadmin can change this form and approve or reject its registrations. Other admins can
                                only view it.
                            </p>
                        </div>
                    </div>

                    <ul class="divide-y overflow-hidden rounded-xl border" aria-label="People who can edit this form">
                        <!-- Owner -->
                        <li class="flex items-center gap-3 px-4 py-3">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300"
                            >
                                <Crown class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <template v-if="form.owner">
                                    <p class="break-words text-xs font-semibold">{{ form.owner.name ?? form.owner.empcode }}</p>
                                    <p class="break-words text-[11px] text-muted-foreground">{{ form.owner.empcode }} · Set up this form</p>
                                </template>
                                <template v-else>
                                    <p class="text-xs font-semibold text-muted-foreground">No owner on record</p>
                                    <p class="text-[11px] text-muted-foreground">Only superadmins and the editors below can change this form.</p>
                                </template>
                            </div>
                            <Badge
                                variant="outline"
                                class="shrink-0 border-blue-200 text-[10px] text-blue-600 dark:border-blue-900 dark:text-blue-400"
                            >
                                Owner
                            </Badge>
                        </li>

                        <!-- Editors -->
                        <li v-for="editor in form.editors" :key="editor.id" class="flex items-center gap-3 px-4 py-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <UserCog class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs font-semibold">
                                    <span class="break-words">{{ editor.name }}</span>
                                    <span
                                        v-if="!editor.has_admin_access"
                                        class="rounded-full bg-amber-100 px-1.5 text-[10px] font-bold leading-4 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                                        title="They can't edit this form until they have admin access again."
                                    >
                                        No admin access
                                    </span>
                                </p>
                                <p class="break-words text-[11px] text-muted-foreground">
                                    {{ editor.empcode }}<template v-if="editor.added_by_name"> · Added by {{ editor.added_by_name }}</template
                                    ><template v-if="editor.added_at"> on {{ editor.added_at }}</template>
                                </p>
                            </div>
                            <Badge variant="outline" class="shrink-0 text-[10px]">Editor</Badge>
                            <button
                                v-if="canManageEditors"
                                type="button"
                                class="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:text-red-600 disabled:opacity-40"
                                :disabled="removingEditorId !== null"
                                :aria-label="`Remove ${editor.name} as an editor`"
                                @click="removeEditor(editor)"
                            >
                                <LoaderCircle v-if="removingEditorId === editor.id" class="h-3.5 w-3.5 animate-spin" />
                                <UserMinus v-else class="h-3.5 w-3.5" />
                            </button>
                        </li>

                        <li v-if="!form.editors.length" class="px-4 py-3 text-[11px] text-muted-foreground">
                            No editors yet.<template v-if="canManageEditors"> Search below to let another admin help manage this form.</template>
                        </li>
                    </ul>

                    <!-- Add editor (owner and superadmins only) -->
                    <div v-if="canManageEditors" class="grid gap-1">
                        <Label for="editor-search" class="text-xs">Add Editor</Label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="editor-search"
                                v-model="editorQuery"
                                type="text"
                                autocomplete="off"
                                class="h-8 pl-8 text-xs"
                                placeholder="Search an admin by name or employee code…"
                                @focus="editorQuery.trim().length >= 2 && (editorSearchOpen = true)"
                                @keydown.esc="editorSearchOpen = false"
                            />
                            <div
                                v-if="editorSearchOpen"
                                class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border bg-background shadow-lg"
                            >
                                <div v-if="searchingEditors" class="flex items-center gap-1.5 px-3 py-2 text-xs text-muted-foreground">
                                    <LoaderCircle class="h-3 w-3 animate-spin" /> Searching…
                                </div>
                                <template v-else>
                                    <button
                                        v-for="candidate in editorResults"
                                        :key="candidate.id"
                                        type="button"
                                        class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs transition-colors hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!!candidate.unavailable || addingEditorId !== null"
                                        @click="addEditor(candidate)"
                                    >
                                        <span class="min-w-0 break-words">
                                            <span class="font-semibold">{{ candidate.name }}</span>
                                            <span v-if="candidate.empcode" class="text-muted-foreground"> · {{ candidate.empcode }}</span>
                                        </span>
                                        <span v-if="candidate.unavailable" class="shrink-0 text-[10px] font-bold text-muted-foreground">
                                            {{ candidate.unavailable }}
                                        </span>
                                        <LoaderCircle
                                            v-else-if="addingEditorId === candidate.id"
                                            class="h-3.5 w-3.5 shrink-0 animate-spin text-blue-600"
                                        />
                                        <Plus v-else class="h-3.5 w-3.5 shrink-0 text-blue-600" />
                                    </button>
                                    <SearchEmptyState
                                        v-if="!editorResults.length"
                                        compact
                                        :query="editorQuery"
                                        :suggestion="editorSuggestion"
                                        description="Only admins can be editors. Check the spelling of the name or employee code."
                                        @search="editorQuery = $event"
                                        @clear-filters="editorQuery = ''"
                                    />
                                </template>
                            </div>
                        </div>
                        <p v-if="editorError" class="text-xs text-red-500">{{ editorError }}</p>
                        <p v-else class="text-[11px] text-muted-foreground">Only admins can be added. Editors can't add or remove other editors.</p>
                    </div>
                    <p v-else class="flex items-start gap-2 text-[11px] text-muted-foreground">
                        <Lock class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        {{ canManage ? editorOnlyNote : readOnlyNote }}
                    </p>
                </Card>
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
                        It appears in the "Additional Questions" step of the registration form.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4 py-2" @submit.prevent="submitQuestion">
                    <div class="grid gap-1">
                        <Label class="text-xs">Answer Type <span class="text-red-500">*</span></Label>
                        <Select v-model="questionForm.type">
                            <SelectTrigger class="h-8 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="text">Short answer (one line)</SelectItem>
                                <SelectItem class="text-xs" value="textarea">Paragraph (several lines)</SelectItem>
                                <SelectItem class="text-xs" value="radio">Single choice (radio buttons)</SelectItem>
                                <SelectItem class="text-xs" value="checkbox">Checkboxes (pick any)</SelectItem>
                                <SelectItem class="text-xs" value="select">Dropdown (pick one)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-1">
                        <Label class="text-xs">Question <span class="text-red-500">*</span></Label>
                        <Textarea v-model="questionForm.label" class="text-xs" rows="2" placeholder="e.g. Do you have any dietary restrictions?" />
                        <p v-if="questionErrors.label" class="text-xs text-red-500">{{ questionErrors.label }}</p>
                    </div>

                    <div class="grid gap-1">
                        <Label class="text-xs">Help Text</Label>
                        <Input v-model="questionForm.help_text" class="h-8 text-xs" placeholder="Optional hint shown under the question" />
                        <p v-if="questionErrors.help_text" class="text-xs text-red-500">{{ questionErrors.help_text }}</p>
                    </div>

                    <div v-if="CHOICE_TYPES.includes(questionForm.type)" class="grid gap-1">
                        <Label class="text-xs">Options <span class="text-red-500">*</span></Label>
                        <div class="flex flex-col">
                            <div v-for="(_, index) in questionForm.options" :key="index" class="group flex items-center gap-2 py-1">
                                <!-- Marker shows how the option looks to people: circle, square or number -->
                                <span
                                    v-if="questionForm.type === 'radio'"
                                    class="h-4 w-4 shrink-0 rounded-full border-2 border-muted-foreground/40"
                                />
                                <span
                                    v-else-if="questionForm.type === 'checkbox'"
                                    class="h-4 w-4 shrink-0 rounded border-2 border-muted-foreground/40"
                                />
                                <span v-else class="w-4 shrink-0 text-right text-xs text-muted-foreground">{{ index + 1 }}.</span>
                                <div class="min-w-0 flex-1">
                                    <input
                                        :ref="(el) => (optionInputs[index] = el as HTMLInputElement)"
                                        v-model="questionForm.options[index]"
                                        type="text"
                                        :aria-label="`Option ${index + 1}`"
                                        class="w-full border-0 border-b border-transparent bg-transparent px-1 py-1 text-xs outline-none transition-colors hover:border-border focus:border-b-2 focus:border-blue-600"
                                        @keydown.enter.prevent="addOption(index)"
                                    />
                                    <p v-if="questionErrors[`options.${index}`]" class="px-1 text-[11px] text-red-500">
                                        {{ questionErrors[`options.${index}`] }}
                                    </p>
                                </div>
                                <button
                                    v-if="questionForm.options.length > 1"
                                    type="button"
                                    class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    :aria-label="`Remove option ${index + 1}`"
                                    @click="removeOption(index)"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <!-- "Other" row: people type their own answer -->
                            <div v-if="canAddOther && questionForm.allow_other" class="flex items-center gap-2 py-1">
                                <span
                                    v-if="questionForm.type === 'radio'"
                                    class="h-4 w-4 shrink-0 rounded-full border-2 border-muted-foreground/40"
                                />
                                <span v-else class="h-4 w-4 shrink-0 rounded border-2 border-muted-foreground/40" />
                                <span class="flex-1 border-b border-dotted px-1 py-1 text-xs text-muted-foreground">Other…</span>
                                <button
                                    type="button"
                                    class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    aria-label="Remove the Other option"
                                    @click="questionForm.allow_other = false"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <!-- Add option / add "Other" -->
                            <div class="flex items-center gap-2 py-1">
                                <span
                                    v-if="questionForm.type === 'radio'"
                                    class="h-4 w-4 shrink-0 rounded-full border-2 border-muted-foreground/25"
                                />
                                <span
                                    v-else-if="questionForm.type === 'checkbox'"
                                    class="h-4 w-4 shrink-0 rounded border-2 border-muted-foreground/25"
                                />
                                <span v-else class="w-4 shrink-0 text-right text-xs text-muted-foreground/60"
                                    >{{ questionForm.options.length + 1 }}.</span
                                >
                                <p class="px-1 text-xs">
                                    <button type="button" class="text-muted-foreground hover:text-foreground hover:underline" @click="addOption()">
                                        Add option
                                    </button>
                                    <template v-if="canAddOther && !questionForm.allow_other">
                                        <span class="text-muted-foreground"> or </span>
                                        <button
                                            type="button"
                                            class="font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                            @click="questionForm.allow_other = true"
                                        >
                                            add "Other"
                                        </button>
                                    </template>
                                </p>
                            </div>
                        </div>
                        <p v-if="questionErrors.options" class="text-xs text-red-500">{{ questionErrors.options }}</p>
                    </div>

                    <label class="flex w-fit cursor-pointer items-center gap-2 text-xs">
                        <input v-model="questionForm.is_required" type="checkbox" class="h-3.5 w-3.5" />
                        Required question
                    </label>
                </form>

                <div class="flex justify-end gap-2 border-t pt-2">
                    <Button type="button" variant="outline" size="sm" @click="showQuestionModal = false">Cancel</Button>
                    <Button
                        type="button"
                        size="sm"
                        class="bg-blue-600 hover:bg-blue-700 dark:text-white"
                        :disabled="savingQuestion"
                        @click="submitQuestion"
                    >
                        <LoaderCircle v-if="savingQuestion" class="mr-1 h-3 w-3 animate-spin" />
                        {{ editingQuestion ? 'Save Changes' : 'Add Question' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
