<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { REGISTRATION_THEMES } from '@/lib/registrationThemes';
import RegistrationReviewPanel, { type RegistrationRow } from '@/pages/programs/RegistrationReviewPanel.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    ChevronUp,
    Copy,
    ExternalLink,
    Eye,
    Image as ImageIcon,
    ListChecks,
    LoaderCircle,
    Lock,
    Palette,
    Pencil,
    Plus,
    Save,
    Settings2,
    Trash2,
    Upload,
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
    questions: Question[];
}

const props = defineProps<{
    batch: { id: number; batch: string; program_code: string; date_start: string | null; date_end: string | null; venue: string | null };
    program: { id: number; title: string; pax: string | null } | null;
    registrationForm: RegistrationFormData | null;
    registrations: RegistrationRow[];
    canManageRegistrations: boolean;
    defaultBackgroundUrl: string;
    themes: string[];
    fieldOptions: { region: string[]; office_division: Record<string, string[]>; sex: string[]; plantilla_status: string[] };
}>();

const form = computed(() => props.registrationForm);
const canManage = computed(() => props.canManageRegistrations);

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
                v-if="!canManage"
                class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300"
            >
                <Lock class="mt-0.5 h-4 w-4 shrink-0" />
                Only the admin who created this program or a super admin can set up this registration form and approve registrations. You can view it,
                but you can't change it.
            </p>

            <!-- Empty state: set up the form -->
            <EmptyState
                v-if="!form"
                :icon="UserPlus"
                title="No registration form yet for this batch"
                description="Set one up to share a public link where people register for this batch. You review every registration before anyone becomes a participant."
            >
                <Button v-if="canManage" :disabled="creating" class="bg-blue-600 hover:bg-blue-700 dark:text-white" @click="createForm">
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

                <!-- Settings -->
                <div v-show="activeTab === 'settings'" class="flex flex-col gap-4 rounded-2xl border bg-background p-5">
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
                            <p v-if="statusNote" class="max-w-xs text-[11px] text-amber-600 dark:text-amber-400 sm:text-right">{{ statusNote }}</p>
                        </div>
                    </div>

                    <!-- Closing date and slots -->
                    <div class="grid gap-3 border-t pt-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <div class="grid gap-1">
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
                        <div class="grid gap-1">
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
                        <Button
                            v-if="canManage"
                            size="sm"
                            class="h-8 bg-blue-600 text-xs hover:bg-blue-700 dark:text-white"
                            :disabled="!scheduleChanged || savingSettings"
                            @click="saveSchedule"
                        >
                            <Save class="mr-1 h-3 w-3" /> Save
                        </Button>
                    </div>

                    <!-- Public link -->
                    <div class="flex flex-wrap items-center gap-2 border-t pt-3">
                        <code class="max-w-full truncate rounded-md bg-muted px-2 py-1 text-[11px] sm:max-w-xs">{{ publicUrl }}</code>
                        <Button size="sm" variant="outline" class="h-7 text-xs" @click="copyLink">
                            <Check v-if="linkCopied" class="mr-1 h-3 w-3 text-emerald-600" />
                            <Copy v-else class="mr-1 h-3 w-3" /> {{ linkCopied ? 'Copied!' : 'Copy Link' }}
                        </Button>
                        <a :href="publicUrl" target="_blank" rel="noopener">
                            <Button size="sm" variant="outline" class="h-7 text-xs"><ExternalLink class="mr-1 h-3 w-3" /> Open Form</Button>
                        </a>
                        <a v-if="canManage" :href="previewUrl" target="_blank" rel="noopener">
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-7 border-blue-200 text-xs text-blue-600 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-400"
                            >
                                <Eye class="mr-1 h-3 w-3" /> Preview
                            </Button>
                        </a>
                    </div>

                    <!-- Theme -->
                    <div class="flex flex-col gap-2 border-t pt-3">
                        <p class="flex items-center gap-1.5 text-xs font-bold"><Palette class="h-3.5 w-3.5 text-blue-600" /> Color Theme</p>
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
                    </div>

                    <!-- Background image -->
                    <div class="flex flex-col gap-2 border-t pt-3">
                        <p class="flex items-center gap-1.5 text-xs font-bold"><ImageIcon class="h-3.5 w-3.5 text-blue-600" /> Background Image</p>
                        <div class="flex flex-wrap items-center gap-3">
                            <img
                                v-if="previewBackgroundUrl"
                                :src="previewBackgroundUrl"
                                alt="Registration form background"
                                class="h-14 w-20 shrink-0 rounded-lg object-cover"
                            />
                            <div
                                v-else
                                class="flex h-14 w-20 shrink-0 items-center justify-center rounded-lg border border-dashed bg-gray-100 text-[10px] font-semibold text-gray-400"
                            >
                                None
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col gap-2">
                                <div v-if="canManage" class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="Background image">
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
                                </div>
                                <p class="text-[11px] text-muted-foreground">{{ backgroundNote }}</p>
                            </div>
                            <div v-if="canManage && backgroundChoice === 'custom'" class="flex shrink-0 items-center gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-7 text-xs"
                                    :disabled="uploadingBackground"
                                    @click="backgroundFileInput?.click()"
                                >
                                    <LoaderCircle v-if="uploadingBackground" class="mr-1 h-3 w-3 animate-spin" />
                                    <Upload v-else class="mr-1 h-3 w-3" /> Change Image
                                </Button>
                            </div>
                            <LoaderCircle
                                v-if="removingBackground || (uploadingBackground && backgroundChoice !== 'custom')"
                                class="h-4 w-4 animate-spin text-muted-foreground"
                            />
                        </div>
                        <input
                            v-if="canManage"
                            ref="backgroundFileInput"
                            type="file"
                            accept="image/jpeg,image/jpg,image/png,image/webp"
                            class="hidden"
                            @change="handleBackgroundFileChange"
                        />
                    </div>
                </div>

                <!-- Questions -->
                <div v-show="activeTab === 'settings'" class="flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="flex items-center gap-1.5 text-sm font-extrabold"><ListChecks class="h-4 w-4 text-blue-600" /> Form Questions</h2>
                        <Button v-if="canManage" size="sm" class="h-7 bg-blue-600 text-xs hover:bg-blue-700 dark:text-white" @click="openAddQuestion">
                            <Plus class="mr-1 h-3 w-3" /> Add Question
                        </Button>
                    </div>

                    <!-- Fixed steps -->
                    <div class="rounded-2xl border bg-blue-50/50 px-5 py-4 dark:bg-blue-950/10">
                        <p class="text-xs font-bold">Always included</p>
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
                        class="rounded-2xl border border-dashed"
                        title="No additional questions yet."
                        :description="
                            canManage
                                ? 'Add questions here if you need more information from registrants, e.g. dietary needs or shirt size.'
                                : undefined
                        "
                    />

                    <div v-else class="divide-y overflow-hidden rounded-2xl border bg-background">
                        <div v-for="(question, qIdx) in form.questions" :key="question.id" class="flex items-start justify-between gap-3 px-5 py-3">
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
