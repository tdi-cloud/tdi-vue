<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FormQuestionsEditor from '@/components/public-forms/admin/FormQuestionsEditor.vue';
import PublicLinkField from '@/components/public-forms/PublicLinkField.vue';
import SearchEmptyState from '@/components/SearchEmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { type EditorData, type PublicFormSummary, type ReadableAnswer, type SurveySection } from '@/lib/publicForms';
import { registrationTheme } from '@/lib/registrationThemes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    Image as ImageIcon,
    Inbox,
    ListChecks,
    LoaderCircle,
    MailWarning,
    PanelTop,
    Pencil,
    Search,
    Settings2,
    Trash2,
    Type,
    Upload,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface AdminPublicForm extends PublicFormSummary {
    id: number;
    public_url: string;
    /** Opens the form even while it's closed (admins only). */
    preview_url: string;
    question_count: number;
    is_open: boolean;
    /** For the datetime-local input (Y-m-d\TH:i), in the app's timezone. */
    closes_at: string | null;
    /** This form's own background picture, if one was uploaded. */
    background_image_url: string | null;
    /** "No background": a plain light gray page. */
    hide_background: boolean;
    /** The wide banner picture above the title, if one was uploaded. */
    header_image_url: string | null;
}

interface ResponseRow {
    id: number;
    reference: string;
    empcode: string;
    name: string;
    email: string;
    position: string | null;
    office_division: string | null;
    region: string | null;
    submitted_at: string | null;
    confirmation_sent: boolean;
    answers: ReadableAnswer[];
}

type Tab = 'responses' | 'questions' | 'settings';

const props = defineProps<{
    form: AdminPublicForm;
    sections: SurveySection[];
    editor: EditorData;
    responses: {
        data: ResponseRow[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search: string };
    stats: { total: number; emails_not_sent: number };
    /** The site-wide default background (Homepage Images). */
    defaultBackgroundUrl: string;
    initialTab: Tab;
}>();

// Computed, so a renamed form shows its new title here right after saving.
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Public Forms', href: route('public-forms.admin.index') },
    { title: props.form.title, href: route('public-forms.admin.show', props.form.slug) },
]);

const { confirmDialog } = useConfirm();

/* ── Tabs ─────────────────────────────────────────────────────────────────── */

// Saving keeps the page's state, so the open tab stays put after each change.
const tab = ref<Tab>(props.initialTab);

const TABS = computed<{ value: Tab; label: string; count?: number; icon: typeof Inbox }[]>(() => [
    { value: 'responses', label: 'Responses', count: props.stats.total, icon: Inbox },
    { value: 'questions', label: 'Questions', count: props.form.question_count, icon: ListChecks },
    { value: 'settings', label: 'Settings', icon: Settings2 },
]);

/* ── Title and description (like the registration form's) ──────────────────── */

const theme = registrationTheme('blue');

type DetailKey = 'kicker' | 'title' | 'description';

// In the order they appear on the banner.
const DETAIL_FIELDS: { key: DetailKey; label: string; maxLength: number; required?: boolean; multiline?: boolean; hint?: string }[] = [
    { key: 'kicker', label: 'Text above the title', maxLength: 100, hint: 'e.g. "CY 2026 · TESDA Development Institute". Optional.' },
    { key: 'title', label: 'Title', maxLength: 150, required: true },
    { key: 'description', label: 'Description', maxLength: 500, multiline: true, hint: 'Optional.' },
];

const editingDetails = ref(false);
const details = useForm({ kicker: '', title: '', description: '' });

/** What the banner shows: the boxes being edited, or the saved texts. */
const bannerPreview = computed(() =>
    editingDetails.value
        ? { kicker: details.kicker.trim(), title: details.title.trim() || 'Untitled form', description: details.description.trim() }
        : { kicker: props.form.kicker, title: props.form.title, description: props.form.description },
);

function openDetailsEditor(): void {
    details.defaults({ kicker: props.form.kicker ?? '', title: props.form.title, description: props.form.description ?? '' });
    details.reset();
    details.clearErrors();
    editingDetails.value = true;
}

function saveDetails(): void {
    details.put(route('public-forms.admin.details.update', props.form.slug), {
        preserveScroll: true,
        onSuccess: () => {
            editingDetails.value = false;
        },
    });
}

/* ── Open / close ─────────────────────────────────────────────────────────── */

const settings = useForm({
    is_open: props.form.is_open,
    closes_at: props.form.closes_at ?? '',
});

function saveSettings(): void {
    settings
        .transform((data) => ({ ...data, closes_at: data.closes_at || null }))
        .put(route('public-forms.admin.update', props.form.slug), {
            preserveScroll: true,
            // The saved values become the new starting point, so Save turns off until the next change.
            onSuccess: () => settings.defaults(),
        });
}

/* ── Background picture (like the registration form's) ────────────────────── */

const backgroundFileInput = ref<HTMLInputElement | null>(null);
const backgroundUpload = useForm<{ image: File | null }>({ image: null });
const savingBackground = ref(false);

type BackgroundChoice = 'default' | 'custom' | 'none';

const BACKGROUND_CHOICES: { value: BackgroundChoice; label: string }[] = [
    { value: 'default', label: 'Site default' },
    { value: 'custom', label: 'Custom picture' },
    { value: 'none', label: 'No background' },
];

const backgroundChoice = computed<BackgroundChoice>(() => {
    if (props.form.hide_background) return 'none';
    return props.form.background_image_url ? 'custom' : 'default';
});

const previewBackgroundUrl = computed(() =>
    backgroundChoice.value === 'none' ? null : (props.form.background_image_url ?? props.defaultBackgroundUrl),
);

const backgroundNote = computed(() => {
    switch (backgroundChoice.value) {
        case 'none':
            return 'No background picture. The form shows on a plain light gray page.';
        case 'custom':
            return "This form's own picture.";
        default:
            return 'The site-wide default picture, set by a superadmin in Homepage Images.';
    }
});

const isBackgroundBusy = computed(() => savingBackground.value || backgroundUpload.processing);

function setBackgroundHidden(hidden: boolean): void {
    savingBackground.value = true;
    router.put(
        route('public-forms.admin.background.update', props.form.slug),
        { hide_background: hidden },
        {
            preserveScroll: true,
            onFinish: () => {
                savingBackground.value = false;
            },
        },
    );
}

async function chooseBackground(choice: BackgroundChoice): Promise<void> {
    if (choice === backgroundChoice.value || isBackgroundBusy.value) return;

    if (choice === 'none') {
        setBackgroundHidden(true);
        return;
    }

    if (choice === 'custom') {
        // A custom picture that was only hidden comes back without uploading it again.
        if (props.form.background_image_url) {
            setBackgroundHidden(false);
        } else {
            backgroundFileInput.value?.click();
        }
        return;
    }

    // Site default: the form's own picture is deleted (after confirming); a hidden default is shown again.
    if (props.form.background_image_url) {
        await resetBackground();
    } else {
        setBackgroundHidden(false);
    }
}

function uploadBackground(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    backgroundUpload.image = file;
    backgroundUpload.post(route('public-forms.admin.background.upload', props.form.slug), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            // Lets the same file be picked again after an error.
            input.value = '';
            backgroundUpload.reset('image');
        },
    });
}

async function resetBackground(): Promise<void> {
    const confirmed = await confirmDialog("Delete this form's own picture and use the site-wide default picture instead?", {
        confirmText: 'Use Default',
    });
    if (!confirmed) return;

    savingBackground.value = true;
    router.delete(route('public-forms.admin.background.destroy', props.form.slug), {
        preserveScroll: true,
        onFinish: () => {
            savingBackground.value = false;
        },
    });
}

/* ── Header banner picture (above the title; separate from the background) ── */

const headerFileInput = ref<HTMLInputElement | null>(null);
const headerUpload = useForm<{ image: File | null }>({ image: null });
const removingHeader = ref(false);
/** The picked file, shown right away while it uploads. */
const pendingHeaderPreview = ref<string | null>(null);

const headerPreviewUrl = computed(() => pendingHeaderPreview.value ?? props.form.header_image_url);
const isHeaderBusy = computed(() => headerUpload.processing || removingHeader.value);
const headerUploadPercentage = computed(() => Math.round(headerUpload.progress?.percentage ?? 0));

function uploadHeaderImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    headerUpload.clearErrors();
    pendingHeaderPreview.value = URL.createObjectURL(file);
    headerUpload.image = file;
    headerUpload.post(route('public-forms.admin.header-image.upload', props.form.slug), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            // Lets the same file be picked again after an error.
            input.value = '';
            headerUpload.reset('image');
            if (pendingHeaderPreview.value) URL.revokeObjectURL(pendingHeaderPreview.value);
            pendingHeaderPreview.value = null;
        },
    });
}

async function removeHeaderImage(): Promise<void> {
    const confirmed = await confirmDialog('Remove the header picture? The public form will start with its title and description.', {
        confirmText: 'Remove Picture',
    });
    if (!confirmed) return;

    removingHeader.value = true;
    router.delete(route('public-forms.admin.header-image.destroy', props.form.slug), {
        preserveScroll: true,
        onFinish: () => {
            removingHeader.value = false;
        },
    });
}

/* ── Delete the form ──────────────────────────────────────────────────────── */

const deletingForm = ref(false);

async function deleteForm(): Promise<void> {
    if (!(await confirmDialog(`Delete "${props.form.title}" and its questions? Its link will stop working.`, { confirmText: 'Delete Form' }))) {
        return;
    }
    deletingForm.value = true;
    router.delete(route('public-forms.admin.destroy', props.form.slug), {
        onFinish: () => {
            deletingForm.value = false;
        },
    });
}

/* ── Responses ────────────────────────────────────────────────────────────── */

const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('public-forms.admin.show', props.form.slug),
            { search: search.value.trim() || undefined, tab: 'responses' },
            { preserveScroll: true, preserveState: true, replace: true, only: ['responses', 'filters'] },
        );
    }, 350);
});

const expanded = reactive(new Set<number>());

function toggleAnswers(id: number): void {
    if (expanded.has(id)) {
        expanded.delete(id);
    } else {
        expanded.add(id);
    }
}

/** The answers grouped by section in order, skipping sections the person wasn't asked; removed questions last. */
function answersBySection(answers: ReadableAnswer[]): { key: string; title: string; answers: ReadableAnswer[] }[] {
    const groups = props.sections.map((section) => ({
        key: section.key,
        title: section.title,
        answers: answers.filter((answer) => !answer.removed && answer.section === section.key),
    }));
    groups.push({ key: 'removed', title: 'Removed questions', answers: answers.filter((answer) => answer.removed) });

    return groups.filter((group) => group.answers.length > 0);
}

async function deleteResponse(response: ResponseRow): Promise<void> {
    const confirmed = await confirmDialog(
        `Delete ${response.name}'s answers (${response.reference})? They will be able to answer the form again. This can't be undone.`,
        { confirmText: 'Delete Response' },
    );
    if (!confirmed) return;

    router.delete(route('public-forms.admin.responses.destroy', { slug: props.form.slug, publicFormResponse: response.id }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${form.title} — Public Forms`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4">
            <!-- Header -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-800 via-blue-700 to-sky-600 p-6 text-white shadow-xl">
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div class="absolute -right-8 -top-8 h-48 w-48 rounded-full bg-white/5" />
                </div>
                <div class="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div class="flex flex-col gap-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <p v-if="form.kicker" class="text-xs font-bold uppercase tracking-wide text-white/75">{{ form.kicker }}</p>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                :class="form.closed_reason ? 'bg-white/20 text-white' : 'bg-emerald-400/90 text-emerald-950'"
                            >
                                {{ form.closed_reason === 'deadline' ? 'Closed (deadline passed)' : form.closed_reason ? 'Not open' : 'Open' }}
                            </span>
                        </div>
                        <h1 class="text-xl font-bold leading-tight md:text-2xl">{{ form.title }}</h1>
                        <p v-if="form.description" class="max-w-2xl whitespace-pre-line text-sm text-white/85">{{ form.description }}</p>
                    </div>
                    <div class="flex gap-3">
                        <div class="rounded-xl bg-white/15 px-4 py-2.5">
                            <p class="text-2xl font-extrabold tabular-nums leading-none">{{ stats.total.toLocaleString() }}</p>
                            <p class="mt-1 text-[11px] uppercase tracking-wide text-white/80">{{ stats.total === 1 ? 'Response' : 'Responses' }}</p>
                        </div>
                        <div v-if="stats.emails_not_sent" class="rounded-xl bg-amber-400/25 px-4 py-2.5">
                            <p class="text-2xl font-extrabold tabular-nums leading-none">{{ stats.emails_not_sent.toLocaleString() }}</p>
                            <p class="mt-1 text-[11px] uppercase tracking-wide text-white/80">Emails not sent</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-b">
                <div class="flex gap-1" role="tablist" aria-label="Form sections">
                    <button
                        v-for="item in TABS"
                        :key="item.value"
                        type="button"
                        role="tab"
                        :aria-selected="tab === item.value"
                        class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-semibold transition-colors"
                        :class="
                            tab === item.value
                                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="tab = item.value"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        {{ item.label }}
                        <span v-if="item.count !== undefined" class="text-xs font-normal opacity-75">({{ item.count.toLocaleString() }})</span>
                    </button>
                </div>
                <Button as-child size="sm" variant="ghost" class="mb-1 h-8 text-xs">
                    <a :href="form.preview_url" target="_blank" rel="noopener"><Eye class="mr-1 h-3.5 w-3.5" /> Preview form</a>
                </Button>
            </div>

            <!-- Questions -->
            <FormQuestionsEditor
                v-if="tab === 'questions'"
                :form-slug="form.slug"
                :editor="editor"
                :preview-url="form.preview_url"
                :responses-count="stats.total"
            />

            <!-- Settings: link, title and description, open/close, background, header picture, delete -->
            <div v-show="tab === 'settings'" class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border bg-background p-5">
                    <div class="mb-3 flex flex-wrap items-center gap-1.5">
                        <h2 class="text-sm font-bold">Public link</h2>
                        <Badge
                            v-if="!form.closed_reason"
                            variant="outline"
                            class="border-emerald-200 bg-emerald-50 text-[10px] text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300"
                        >
                            Open
                        </Badge>
                        <Badge v-else variant="outline" class="text-[10px] text-muted-foreground">
                            {{ form.closed_reason === 'deadline' ? 'Closed (deadline passed)' : 'Not open' }}
                        </Badge>
                    </div>
                    <PublicLinkField :url="form.public_url" :label="`Public link of ${form.title}`" />
                    <p class="mt-2 text-xs text-muted-foreground">
                        Anyone with the link can answer once they find their name in the employee list. Each employee can answer once.
                    </p>
                </div>

                <div class="flex flex-col gap-3 rounded-2xl border bg-background p-5 lg:row-span-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="flex items-center gap-1.5 text-sm font-bold"><Type class="h-4 w-4" /> Title and Description</h2>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">The banner at the top of the public form.</p>
                        </div>
                        <Button
                            v-if="!editingDetails"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-8 shrink-0 text-xs"
                            @click="openDetailsEditor"
                        >
                            <Pencil class="mr-1 h-3 w-3" /> Edit
                        </Button>
                    </div>

                    <!-- Preview of the banner people see -->
                    <div
                        class="overflow-hidden rounded-xl"
                        :class="theme.header"
                        :aria-label="editingDetails ? 'Preview of your changes' : 'Current banner'"
                    >
                        <div class="px-4 py-3.5">
                            <p v-if="bannerPreview.kicker" class="mb-0.5 text-[10px] font-semibold uppercase tracking-widest" :class="theme.kicker">
                                {{ bannerPreview.kicker }}
                            </p>
                            <p class="break-words text-base font-extrabold leading-tight text-white">{{ bannerPreview.title }}</p>
                            <p v-if="bannerPreview.description" class="mt-1 whitespace-pre-line break-words text-xs leading-relaxed text-white/85">
                                {{ bannerPreview.description }}
                            </p>
                        </div>
                    </div>

                    <form v-if="editingDetails" class="flex flex-col gap-3" @submit.prevent="saveDetails">
                        <div v-for="field in DETAIL_FIELDS" :key="field.key">
                            <label :for="`details-${field.key}`" class="mb-1 block text-xs font-semibold">
                                {{ field.label }} <span v-if="field.required" class="text-red-500">*</span>
                            </label>
                            <Textarea
                                v-if="field.multiline"
                                :id="`details-${field.key}`"
                                v-model="details[field.key]"
                                rows="3"
                                class="text-xs"
                                :maxlength="field.maxLength"
                            />
                            <Input
                                v-else
                                :id="`details-${field.key}`"
                                v-model="details[field.key]"
                                class="h-9 text-xs"
                                :maxlength="field.maxLength"
                            />
                            <p v-if="field.hint" class="mt-1 text-[11px] text-muted-foreground">{{ field.hint }}</p>
                            <p v-if="details.errors[field.key]" class="mt-1 text-xs text-red-500">{{ details.errors[field.key] }}</p>
                        </div>

                        <div class="flex justify-end gap-2">
                            <Button type="button" size="sm" variant="outline" class="h-8 text-xs" @click="editingDetails = false">Cancel</Button>
                            <Button
                                type="submit"
                                size="sm"
                                class="h-8 bg-blue-600 text-xs hover:bg-blue-700 dark:text-white"
                                :disabled="details.processing"
                            >
                                <LoaderCircle v-if="details.processing" class="mr-1 h-3 w-3 animate-spin" /> Save
                            </Button>
                        </div>
                    </form>
                </div>

                <form class="rounded-2xl border bg-background p-5" @submit.prevent="saveSettings">
                    <h2 class="mb-3 flex items-center gap-1.5 text-sm font-bold"><Settings2 class="h-4 w-4" /> Open and Close</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border px-3 py-2.5">
                                <input v-model="settings.is_open" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 accent-blue-600" />
                                <span>
                                    <span class="block text-xs font-semibold">Accepting responses</span>
                                    <span class="block text-[11px] text-muted-foreground">Untick to close the form now.</span>
                                </span>
                            </label>
                            <p v-if="settings.errors.is_open" class="mt-1 text-xs text-red-500">{{ settings.errors.is_open }}</p>
                        </div>

                        <div>
                            <label for="closes-at" class="mb-1 block text-xs font-semibold"
                                >Closes on <span class="font-normal">(optional)</span></label
                            >
                            <Input id="closes-at" v-model="settings.closes_at" type="datetime-local" class="h-9 text-xs" />
                            <p v-if="settings.errors.closes_at" class="mt-1 text-xs text-red-500">{{ settings.errors.closes_at }}</p>
                            <button
                                v-else-if="settings.closes_at"
                                type="button"
                                class="mt-1 text-[11px] font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                @click="settings.closes_at = ''"
                            >
                                Remove closing date
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <Button
                            type="submit"
                            size="sm"
                            class="bg-blue-600 hover:bg-blue-700 dark:text-white"
                            :disabled="settings.processing || !settings.isDirty"
                        >
                            <LoaderCircle v-if="settings.processing" class="mr-1 h-3 w-3 animate-spin" /> Save
                        </Button>
                    </div>
                </form>

                <div class="flex flex-col gap-3 rounded-2xl border bg-background p-5 lg:col-span-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="flex items-center gap-1.5 text-sm font-bold"><ImageIcon class="h-4 w-4" /> Background Picture</h2>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                Shown behind the public form, its closed page and its thank-you page.
                            </p>
                        </div>
                        <Button
                            v-if="backgroundChoice === 'custom'"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-8 shrink-0 text-xs"
                            :disabled="isBackgroundBusy"
                            @click="backgroundFileInput?.click()"
                        >
                            <LoaderCircle v-if="backgroundUpload.processing" class="mr-1 h-3 w-3 animate-spin" />
                            <Upload v-else class="mr-1 h-3 w-3" /> Change Picture
                        </Button>
                    </div>

                    <div class="flex items-center gap-4">
                        <img
                            v-if="previewBackgroundUrl"
                            :src="previewBackgroundUrl"
                            :alt="`Background picture of ${form.title}`"
                            class="h-16 w-24 shrink-0 rounded-lg object-cover ring-1 ring-border"
                        />
                        <div
                            v-else
                            class="flex h-16 w-24 shrink-0 items-center justify-center rounded-lg border border-dashed bg-muted/50 text-[10px] font-semibold text-muted-foreground"
                        >
                            None
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <div class="flex flex-wrap items-center gap-1.5" role="radiogroup" aria-label="Background picture">
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
                                    :disabled="isBackgroundBusy"
                                    @click="chooseBackground(choice.value)"
                                >
                                    {{ choice.label }}
                                </button>
                                <LoaderCircle v-if="isBackgroundBusy" class="h-4 w-4 animate-spin text-muted-foreground" />
                            </div>
                            <p class="text-[11px] text-muted-foreground">{{ backgroundNote }}</p>
                        </div>
                    </div>

                    <p v-if="backgroundUpload.errors.image" class="text-xs text-red-500">{{ backgroundUpload.errors.image }}</p>
                    <p class="text-[11px] text-muted-foreground">JPG, PNG or WebP, up to 5 MB. A wide landscape picture works best.</p>

                    <input ref="backgroundFileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadBackground" />
                </div>

                <div class="flex flex-col gap-3 rounded-2xl border bg-background p-5 lg:col-span-2">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="flex items-center gap-1.5 text-sm font-bold"><PanelTop class="h-4 w-4" /> Header Banner Image</h2>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                A wide picture shown on its own above the title and description of the public form.
                            </p>
                        </div>
                        <div v-if="form.header_image_url" class="flex shrink-0 gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 text-xs"
                                :disabled="isHeaderBusy"
                                @click="headerFileInput?.click()"
                            >
                                <LoaderCircle v-if="headerUpload.processing" class="mr-1 h-3 w-3 animate-spin" />
                                <Upload v-else class="mr-1 h-3 w-3" /> Change Picture
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 border-red-200 text-xs text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-900/60 dark:hover:bg-red-950/30"
                                :disabled="isHeaderBusy"
                                @click="removeHeaderImage"
                            >
                                <LoaderCircle v-if="removingHeader" class="mr-1 h-3 w-3 animate-spin" />
                                <Trash2 v-else class="mr-1 h-3 w-3" /> Remove
                            </Button>
                        </div>
                    </div>

                    <!-- Same shape as on the public form (about 16:5) -->
                    <div>
                        <img
                            v-if="headerPreviewUrl"
                            :src="headerPreviewUrl"
                            :alt="`Header picture of ${form.title}`"
                            class="block aspect-[16/5] w-full rounded-xl border object-cover object-center transition-opacity"
                            :class="{ 'opacity-60': headerUpload.processing }"
                        />
                        <button
                            v-else
                            type="button"
                            class="flex aspect-[16/5] w-full flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed bg-muted/40 text-muted-foreground transition-colors hover:border-blue-400 hover:bg-blue-50/60 hover:text-blue-700 disabled:cursor-default dark:hover:bg-blue-950/20 dark:hover:text-blue-300"
                            :disabled="isHeaderBusy"
                            @click="headerFileInput?.click()"
                        >
                            <Upload class="h-5 w-5" />
                            <span class="text-xs font-semibold">Upload a header picture</span>
                            <span class="text-[11px]">No header picture yet. The form starts with its title.</span>
                        </button>
                    </div>

                    <div v-if="headerUpload.processing" class="flex items-center gap-2" role="status" aria-live="polite">
                        <div
                            class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="headerUploadPercentage"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="Upload progress"
                        >
                            <div class="h-full rounded-full bg-blue-600 transition-all" :style="{ width: `${headerUploadPercentage}%` }" />
                        </div>
                        <span class="w-24 shrink-0 text-right text-[11px] tabular-nums text-muted-foreground"
                            >Uploading… {{ headerUploadPercentage }}%</span
                        >
                    </div>

                    <p v-if="headerUpload.errors.image" class="text-xs text-red-500">{{ headerUpload.errors.image }}</p>
                    <p class="text-[11px] text-muted-foreground">
                        JPG, PNG or WebP, up to 5 MB. Use a wide landscape picture, about 16:5 (e.g. 1600 × 500 pixels); the edges may be cropped to
                        fit.
                    </p>

                    <input ref="headerFileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadHeaderImage" />
                </div>

                <div
                    class="flex flex-col gap-3 rounded-2xl border border-red-200 bg-background p-5 dark:border-red-900/60 sm:flex-row sm:items-center sm:justify-between lg:col-span-2"
                >
                    <div class="min-w-0">
                        <h2 class="flex items-center gap-1.5 text-sm font-bold text-red-600 dark:text-red-400">
                            <Trash2 class="h-4 w-4" /> Delete Form
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            <template v-if="stats.total">
                                This form has {{ stats.total.toLocaleString() }} {{ stats.total === 1 ? 'response' : 'responses' }}, so it can't be
                                deleted. Close it instead, or delete its responses first.
                            </template>
                            <template v-else>Deletes the form and its questions. Its link stops working.</template>
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="h-8 shrink-0 border-red-200 text-xs text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-900/60 dark:hover:bg-red-950/30"
                        :disabled="stats.total > 0 || deletingForm"
                        @click="deleteForm"
                    >
                        <LoaderCircle v-if="deletingForm" class="mr-1 h-3 w-3 animate-spin" />
                        <Trash2 v-else class="mr-1 h-3 w-3" /> Delete Form
                    </Button>
                </div>
            </div>

            <!-- Responses -->
            <template v-if="tab === 'responses'">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-bold">
                        Responses <span class="font-normal text-muted-foreground">({{ responses.total.toLocaleString() }})</span>
                    </h2>
                    <div class="flex items-center gap-2">
                        <div class="relative w-full sm:w-64">
                            <Search class="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="search"
                                class="h-8 pl-7 text-xs"
                                placeholder="Search name, code, email, office…"
                                aria-label="Search responses"
                            />
                        </div>
                        <Button as-child size="sm" variant="outline" class="h-8 shrink-0 text-xs">
                            <a :href="route('public-forms.admin.export', form.slug)"><Download class="mr-1 h-3 w-3" /> Download CSV</a>
                        </Button>
                    </div>
                </div>

                <SearchEmptyState
                    v-if="!responses.data.length && search.trim()"
                    class="rounded-2xl border border-dashed"
                    :query="search"
                    description="No response matches that name, code, email or office."
                    @clear-filters="search = ''"
                    @search="search = $event"
                />
                <EmptyState
                    v-else-if="!responses.data.length"
                    :icon="Inbox"
                    class="rounded-2xl border border-dashed"
                    title="No responses yet"
                    :description="
                        form.closed_reason
                            ? 'This form is not open yet. Check its questions, then open it in Settings and share the link.'
                            : 'Responses appear here as soon as employees submit the form. Share the public link to get started.'
                    "
                />

                <ul v-else class="divide-y overflow-hidden rounded-2xl border bg-background">
                    <li v-for="response in responses.data" :key="response.id">
                        <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="text-sm font-bold">{{ response.name }}</p>
                                    <Badge variant="outline" class="text-[10px]">{{ response.empcode }}</Badge>
                                    <Badge
                                        v-if="!response.confirmation_sent"
                                        variant="outline"
                                        class="border-amber-200 bg-amber-50 text-[10px] text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300"
                                    >
                                        <MailWarning class="mr-1 h-3 w-3" /> Email not sent
                                    </Badge>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                    {{ response.position }}<template v-if="response.office_division"> · {{ response.office_division }}</template
                                    ><template v-if="response.region"> · {{ response.region }}</template>
                                </p>
                                <p class="truncate text-xs text-muted-foreground">{{ response.email }}</p>
                            </div>

                            <div class="flex shrink-0 flex-col items-start gap-1.5 text-[11px] text-muted-foreground sm:items-end">
                                <span class="font-mono font-semibold text-foreground">{{ response.reference }}</span>
                                <span>{{ response.submitted_at }}</span>
                                <div class="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="h-7 text-xs"
                                        :aria-expanded="expanded.has(response.id)"
                                        :aria-controls="`response-answers-${response.id}`"
                                        @click="toggleAnswers(response.id)"
                                    >
                                        <ChevronDown class="mr-1 h-3 w-3 transition-transform" :class="{ 'rotate-180': expanded.has(response.id) }" />
                                        {{ expanded.has(response.id) ? 'Hide answers' : 'Show answers' }}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="h-7 w-7 text-red-500 hover:text-red-600"
                                        :aria-label="`Delete ${response.name}'s response`"
                                        @click="deleteResponse(response)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div v-if="expanded.has(response.id)" :id="`response-answers-${response.id}`" class="border-t bg-muted/30 px-4 py-4 sm:px-5">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <section v-for="group in answersBySection(response.answers)" :key="group.key">
                                    <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{{ group.title }}</h3>
                                    <dl class="space-y-2.5">
                                        <div v-for="answer in group.answers" :key="answer.key">
                                            <dt class="text-xs text-muted-foreground">
                                                <template v-if="answer.number">{{ answer.number }}. </template>{{ answer.question }}
                                            </dt>
                                            <dd class="mt-0.5 text-xs">
                                                <template v-if="answer.lines.length">
                                                    <span v-for="line in answer.lines" :key="line" class="block break-words font-semibold">{{
                                                        line
                                                    }}</span>
                                                </template>
                                                <span v-else class="text-muted-foreground">No answer</span>
                                            </dd>
                                        </div>
                                    </dl>
                                </section>
                            </div>
                        </div>
                    </li>
                </ul>

                <nav v-if="responses.last_page > 1" class="flex items-center justify-between text-sm" aria-label="Responses pages">
                    <p class="text-xs text-muted-foreground">Page {{ responses.current_page }} of {{ responses.last_page }}</p>
                    <div class="flex items-center gap-1">
                        <template v-for="link in responses.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                :only="['responses', 'filters']"
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs transition-colors"
                                :class="link.active ? 'border-blue-600 bg-blue-600 text-white' : 'text-muted-foreground hover:bg-muted'"
                                :aria-current="link.active ? 'page' : undefined"
                            >
                                <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" aria-label="Previous page" />
                                <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" aria-label="Next page" />
                                <span v-else v-html="link.label" />
                            </Link>
                            <span
                                v-else
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs text-muted-foreground opacity-40"
                                aria-hidden="true"
                            >
                                <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                                <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                                <span v-else v-html="link.label" />
                            </span>
                        </template>
                    </div>
                </nav>
            </template>
        </div>
    </AppLayout>
</template>
