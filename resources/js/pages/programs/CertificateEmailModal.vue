<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import {
    BODY_TOOLBAR,
    buildDefaultSignature,
    escapeHtml,
    formatEmailSchedule,
    isEditorEmpty,
    type QuillEditor,
    setEditorHtml,
    SIGNATURE_TOOLBAR,
    toEmailHtml,
    useEmailEditors,
} from '@/composables/useEmailEditors';
import {
    CERTIFICATE_TYPE_ACCENTS,
    CERTIFICATE_TYPE_COLORS,
    CERTIFICATE_TYPES,
    type CertificateEmailHistoryEntry,
    type CertificateEmailPreset,
    type CertificateEmailTemplate,
    formatDate,
    formatHours,
    isReadyToEmail,
    typeMeta,
} from '@/lib/certificates';
import { useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Award,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    FileText,
    History,
    Info,
    Loader2,
    MailCheck,
    PenLine,
    RotateCcw,
    Search,
    Send,
    Sparkles,
    Users,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

interface EmailCertificate {
    id: number;
    type: string;
    status: string;
    certificate_number: string | null;
    issued_date: string | null;
    hours: number | string | null;
    file_path: string | null;
    file_name: string | null;
    has_file?: boolean;
    emailed_at?: string | null;
    emailed_by?: string | null;
}

interface EmailParticipant {
    id: number;
    empcode: string;
    attendance: string;
    user_email?: string | null;
    employee: { name?: string | null; FIRSTNAME?: string | null; initials?: string | null; avatar_color?: string | null } | null;
    certificates?: EmailCertificate[];
}

interface EmailBatch {
    id: number;
    batch: string;
    date_start: string | null;
    date_end: string | null;
    participants?: EmailParticipant[];
}

/** One certificate of the chosen batch and type, with what the window needs to show it. */
interface Recipient {
    certificate: EmailCertificate;
    participant: EmailParticipant;
    name: string;
    firstName: string;
    email: string | null;
    initials: string;
    avatarColor: string;
    ready: boolean;
    emailed: boolean;
}

interface SendResult {
    sent: number;
    failed: Array<{ name: string; reason: string }>;
}

interface EmailFormFields {
    type: string;
    certificate_ids: number[];
    subject: string;
    body: string;
    signature: string;
}

type Step = 1 | 2 | 3;
type PlaceholderKey = (typeof PLACEHOLDERS)[number];

const props = defineProps<{
    open: boolean;
    program: { id: number; title: string; batches?: EmailBatch[] };
    templates: Record<string, CertificateEmailTemplate>;
    history: CertificateEmailHistoryEntry[];
    preset: CertificateEmailPreset | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { confirmDialog } = useConfirm();
const page = usePage();

const SUBJECT_MAX = 500;
const SEARCH_THRESHOLD = 12;
const PILL_BATCH_LIMIT = 4;
const SENDER_ADDRESS = 'tdi.noreply@tesda.gov.ph';
/** Same keys and order as CertificateEmailPlaceholders::KEYS. */
const PLACEHOLDERS = ['first_name', 'full_name', 'program_title', 'batch', 'schedule', 'certificate_type', 'certificate_number'] as const;
/** Same as CertificateEmailPlaceholders::SCHEDULE_FALLBACK. */
const SCHEDULE_FALLBACK = 'the scheduled dates';
const PLACEHOLDER_PATTERN = new RegExp(`\\{(${PLACEHOLDERS.join('|')})\\}`, 'g');
const STEPS: Array<{ number: Step; label: string }> = [
    { number: 1, label: 'Choose' },
    { number: 2, label: 'Message' },
    { number: 3, label: 'Review' },
];

const form = useForm<EmailFormFields>({
    type: '',
    certificate_ids: [],
    subject: '',
    body: '',
    signature: '',
});

const formErrors = computed(() => form.errors as Record<string, string>);
const senderName = computed(() => String((page.props as { auth?: { user?: { name?: string } } }).auth?.user?.name ?? ''));
const plural = (count: number, word: string, pluralWord = `${word}s`) => `${count} ${count === 1 ? word : pluralWord}`;

/* ===================== VIEW & STEPS ===================== */

const view = ref<'steps' | 'history' | 'result'>('steps');
const step = ref<Step>(1);
const scroller = ref<HTMLElement | null>(null);
const result = ref<SendResult | null>(null);

watch([step, view], () => nextTick(() => scroller.value?.scrollTo({ top: 0 })));

/* ===================== BATCH, TYPE & RECIPIENTS ===================== */

const batches = computed<EmailBatch[]>(() => props.program.batches ?? []);
const selectedBatchId = ref<number | null>(null);
const selectedType = ref<string>('Completion');
const selectedBatch = computed(() => batches.value.find((batch) => batch.id === selectedBatchId.value) ?? null);
const scheduleLabel = computed(() => formatEmailSchedule(selectedBatch.value?.date_start, selectedBatch.value?.date_end));

const accent = computed(() => CERTIFICATE_TYPE_ACCENTS[selectedType.value] ?? CERTIFICATE_TYPE_ACCENTS.Participation);
const typeColors = (type: string | null) => CERTIFICATE_TYPE_COLORS[type ?? ''] ?? CERTIFICATE_TYPE_COLORS.Participation;

const toRecipient = (participant: EmailParticipant, certificate: EmailCertificate): Recipient => {
    const name = participant.employee?.name?.trim() || participant.empcode;
    const email = participant.user_email?.trim() || null;

    return {
        certificate,
        participant,
        name,
        firstName: participant.employee?.FIRSTNAME?.trim() || name,
        email,
        initials: participant.employee?.initials || name.slice(0, 2).toUpperCase(),
        avatarColor: participant.employee?.avatar_color || 'bg-slate-400',
        ready: isReadyToEmail(certificate, email),
        emailed: !!certificate.emailed_at,
    };
};

// Absent participants aren't managed on the Certificates tab, so they aren't offered here either.
const certificatesIn = (batch: EmailBatch | null, type: string): Recipient[] =>
    (batch?.participants ?? [])
        .filter((participant) => participant.attendance !== 'Absent')
        .flatMap((participant) =>
            (participant.certificates ?? [])
                .filter((certificate) => certificate.type === type)
                .map((certificate) => toRecipient(participant, certificate)),
        );

const typeRecipients = computed(() => certificatesIn(selectedBatch.value, selectedType.value));
const readyRecipients = computed(() => typeRecipients.value.filter((recipient) => recipient.ready));

const typeTiles = computed(() =>
    CERTIFICATE_TYPES.map((type) => {
        const ready = certificatesIn(selectedBatch.value, type).filter((recipient) => recipient.ready);
        return { type, ready: ready.length, emailed: ready.filter((recipient) => recipient.emailed).length, icon: typeMeta(type).icon };
    }),
);

/** The type with the most certificates still to email in a batch, then the most ready ones. */
const bestTypeFor = (batch: EmailBatch | null): string => {
    const ranked = CERTIFICATE_TYPES.map((type) => {
        const ready = certificatesIn(batch, type).filter((recipient) => recipient.ready);
        return { type, ready: ready.length, waiting: ready.filter((recipient) => !recipient.emailed).length };
    })
        .filter((entry) => entry.ready > 0)
        .sort((a, b) => b.waiting - a.waiting || b.ready - a.ready);

    return ranked[0]?.type ?? selectedType.value;
};

const hasReadyCertificates = (batch: EmailBatch) =>
    CERTIFICATE_TYPES.some((type) => certificatesIn(batch, type).some((recipient) => recipient.ready));

// Recipients
const recipientFilter = ref<'waiting' | 'all'>('waiting');
const search = ref('');
const selectedIds = ref<number[]>([]);

const filterOptions = computed(() => [
    { value: 'waiting' as const, label: 'Not yet emailed', count: readyRecipients.value.filter((recipient) => !recipient.emailed).length },
    { value: 'all' as const, label: 'All', count: readyRecipients.value.length },
]);
const filteredRecipients = computed(() => readyRecipients.value.filter((recipient) => recipientFilter.value === 'all' || !recipient.emailed));
const showSearch = computed(() => filteredRecipients.value.length > SEARCH_THRESHOLD);

const visibleRecipients = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!showSearch.value || !query) return filteredRecipients.value;

    return filteredRecipients.value.filter((recipient) =>
        [recipient.name, recipient.participant.empcode, recipient.certificate.certificate_number ?? ''].some((value) =>
            value.toLowerCase().includes(query),
        ),
    );
});

const selectedRecipients = computed(() => readyRecipients.value.filter((recipient) => selectedIds.value.includes(recipient.certificate.id)));
const selectedCount = computed(() => selectedRecipients.value.length);

const recipientErrors = computed(() =>
    Object.entries(formErrors.value)
        .filter(([key]) => key === 'type' || key.startsWith('certificate_ids'))
        .map(([, message]) => message),
);

const clearRecipientErrors = () => {
    const keys = Object.keys(formErrors.value).filter((key) => key === 'type' || key.startsWith('certificate_ids'));
    // Per-item keys such as "certificate_ids.1" come from the server; clearErrors accepts them at runtime.
    if (keys.length) form.clearErrors(...(keys as Array<keyof EmailFormFields>));
};

const isSelected = (recipient: Recipient) => selectedIds.value.includes(recipient.certificate.id);

const toggleRecipient = (recipient: Recipient) => {
    const id = recipient.certificate.id;
    selectedIds.value = isSelected(recipient) ? selectedIds.value.filter((selected) => selected !== id) : [...selectedIds.value, id];
    clearRecipientErrors();
};

const selectAllVisible = () => {
    selectedIds.value = [...new Set([...selectedIds.value, ...visibleRecipients.value.map((recipient) => recipient.certificate.id)])];
    clearRecipientErrors();
};

const clearSelection = () => {
    selectedIds.value = [];
};

// "Not yet emailed" hides people who already have it, so they shouldn't stay selected out of sight.
const setRecipientFilter = (filter: 'waiting' | 'all') => {
    recipientFilter.value = filter;
    if (filter === 'waiting') {
        const emailedIds = new Set(readyRecipients.value.filter((recipient) => recipient.emailed).map((recipient) => recipient.certificate.id));
        selectedIds.value = selectedIds.value.filter((id) => !emailedIds.has(id));
    }
};

const selectWaitingRecipients = () => {
    recipientFilter.value = 'waiting';
    search.value = '';
    selectedIds.value = readyRecipients.value.filter((recipient) => !recipient.emailed).map((recipient) => recipient.certificate.id);
    clearRecipientErrors();
};

// Certificates of this type that can't be sent yet, explained in the two quiet rows under the list.
const showMissingEmail = ref(false);
const showNotReady = ref(false);
const missingEmail = computed(() => typeRecipients.value.filter((recipient) => !recipient.email && recipient.certificate.status !== 'Revoked'));
const notReady = computed(() =>
    typeRecipients.value.filter(
        (recipient) =>
            recipient.email && (recipient.certificate.status === 'Pending' || (recipient.certificate.status === 'Issued' && !recipient.ready)),
    ),
);
const notReadyLabel = computed(() => {
    const count = notReady.value.length;
    if (notReady.value.every((recipient) => recipient.certificate.status === 'Pending')) {
        return `${plural(count, 'certificate')} ${count === 1 ? 'is' : 'are'} still Pending — mark ${count === 1 ? 'it' : 'them'} as Issued to email ${count === 1 ? 'it' : 'them'}`;
    }
    return `${plural(count, 'certificate')} ${count === 1 ? "isn't" : "aren't"} ready yet — still Pending or missing the PDF`;
});

const emailedLabel = (certificate: EmailCertificate) =>
    certificate.emailed_at
        ? `Emailed ${new Date(certificate.emailed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}${certificate.emailed_by ? ` by ${certificate.emailed_by}` : ''}`
        : '';

/* ===================== MESSAGE (shared Quill setup, see useEmailEditors) ===================== */

const { editorsReady, editorLoadFailed, mountEditors, unmountEditors } = useEmailEditors(() => props.open);

let bodyEditor: QuillEditor = null;
let signatureEditor: QuillEditor = null;
let subjectInput: HTMLInputElement | null = null;
const bodyContainer = ref<HTMLElement | null>(null);
const signatureContainer = ref<HTMLElement | null>(null);

const messageEdited = ref(false);
const showPlaceholders = ref(false);
const showSignature = ref(false);
const signaturePreview = ref('');
const lastFocus = ref<'subject' | 'body'>('body');

const templateFor = (type: string): CertificateEmailTemplate =>
    props.templates[type] ?? props.templates.Participation ?? { subject: '', body: '', emoji: '🎓' };

const applyTemplate = (type: string) => {
    const template = templateFor(type);
    form.subject = template.subject;
    setEditorHtml(bodyEditor, template.body);
    messageEdited.value = false;
    form.clearErrors('subject', 'body');
};

const resetToTemplate = async () => {
    if (messageEdited.value) {
        const confirmed = await confirmDialog(`Replace your subject and message with the original ${selectedType.value} template?`, {
            title: 'Reset to template?',
            confirmText: 'Reset',
            cancelText: 'Keep my text',
            variant: 'default',
        });
        if (!confirmed) return;
    }
    applyTemplate(selectedType.value);
};

// Choosing another type swaps in its template, but never silently over an edited message.
const selectType = async (type: string) => {
    if (type === selectedType.value) return;

    const replaceText =
        !messageEdited.value ||
        (await confirmDialog(`You've edited the message. Replace it with the ${type} template?`, {
            title: 'Use the new template?',
            confirmText: 'Use template',
            cancelText: 'Keep my text',
            variant: 'default',
        }));

    selectedType.value = type;
    selectWaitingRecipients();
    if (replaceText) applyTemplate(type);
};

const selectBatch = async (batchId: number) => {
    if (batchId === selectedBatchId.value) return;
    selectedBatchId.value = batchId;

    if (!readyRecipients.value.length) {
        const type = bestTypeFor(selectedBatch.value);
        if (type !== selectedType.value) {
            await selectType(type);
            return;
        }
    }
    selectWaitingRecipients();
};

const refreshSignaturePreview = () => {
    const lines = String(signatureEditor?.getText() ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
    signaturePreview.value = lines.length > 1 ? `${lines[0]} ${lines.slice(1, 3).join(' · ')}` : (lines[0] ?? '');
};

const onSubjectFocus = (event: FocusEvent) => {
    lastFocus.value = 'subject';
    subjectInput = event.target as HTMLInputElement;
};

const onSubjectInput = () => {
    messageEdited.value = true;
    form.clearErrors('subject');
};

/** Put "{key}" where the cursor is, in the subject or the message (whichever was used last). */
const insertPlaceholder = (key: PlaceholderKey) => {
    const token = `{${key}}`;

    if (lastFocus.value === 'subject') {
        const start = subjectInput?.selectionStart ?? form.subject.length;
        const end = subjectInput?.selectionEnd ?? start;
        form.subject = (form.subject.slice(0, start) + token + form.subject.slice(end)).slice(0, SUBJECT_MAX);
        onSubjectInput();
        nextTick(() => {
            subjectInput?.focus();
            subjectInput?.setSelectionRange(start + token.length, start + token.length);
        });
        return;
    }

    if (!bodyEditor) return;
    const range = bodyEditor.getSelection(true);
    const index = range ? range.index : Math.max(0, bodyEditor.getLength() - 1);
    if (range?.length) bodyEditor.deleteText(range.index, range.length, 'user');
    bodyEditor.insertText(index, token, 'user');
    bodyEditor.setSelection(index + token.length, 0, 'user');
};

const initEditors = async () => {
    const editors = await mountEditors([
        {
            container: bodyContainer,
            toolbar: BODY_TOOLBAR,
            placeholder: 'Write your message to the participants…',
            html: templateFor(selectedType.value).body,
        },
        {
            container: signatureContainer,
            toolbar: SIGNATURE_TOOLBAR,
            placeholder: 'Your signature (optional)…',
            html: buildDefaultSignature(senderName.value),
        },
    ]);
    if (!editors) return;

    [bodyEditor, signatureEditor] = editors;
    bodyEditor.on('text-change', (_delta: unknown, _old: unknown, source: string) => {
        if (source === 'user') messageEdited.value = true;
        if (formErrors.value.body) form.clearErrors('body');
    });
    bodyEditor.on('selection-change', (range: unknown) => {
        if (range) lastFocus.value = 'body';
    });
    signatureEditor.on('text-change', () => {
        refreshSignaturePreview();
        if (formErrors.value.signature) form.clearErrors('signature');
    });
    refreshSignaturePreview();
};

const destroyEditors = () => {
    unmountEditors();
    bodyEditor = null;
    signatureEditor = null;
    subjectInput = null;
};

const validateMessage = () => {
    const errors: Partial<Record<keyof EmailFormFields, string>> = {};

    if (!form.subject.trim()) errors.subject = 'Enter a subject for this email.';
    else if (form.subject.length > SUBJECT_MAX) errors.subject = `Keep the subject within ${SUBJECT_MAX} characters.`;
    if (isEditorEmpty(bodyEditor)) errors.body = 'Write a message before sending.';

    if (Object.keys(errors).length) {
        form.setError(errors);
        return false;
    }
    return true;
};

/* ===================== REVIEW ===================== */

const previewIndex = ref(0);
const previewBodyHtml = ref('');
const previewSignatureHtml = ref('');

const placeholderValues = (recipient: Recipient): Record<PlaceholderKey, string> => ({
    first_name: recipient.firstName,
    full_name: recipient.name,
    program_title: props.program.title,
    batch: selectedBatch.value?.batch ?? '',
    schedule: scheduleLabel.value || SCHEDULE_FALLBACK,
    certificate_type: recipient.certificate.type,
    certificate_number: recipient.certificate.certificate_number ?? '',
});

// Mirrors CertificateEmailPlaceholders: one pass, known keys only, values escaped in HTML.
const fillPlaceholders = (text: string, values: Record<PlaceholderKey, string>, asHtml: boolean) =>
    text.replace(PLACEHOLDER_PATTERN, (_match, key: PlaceholderKey) => (asHtml ? escapeHtml(values[key]) : values[key]));

// Mirrors CertificateAvailableMail::attachmentName().
const attachmentName = (recipient: Recipient) => {
    const safeName = `Certificate of ${recipient.certificate.type} - ${recipient.name}`
        .replace(/[^\p{L}\p{N} .,'()_-]+/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, 150)
        .replace(/^[ .-]+|[ .-]+$/g, '');
    return `${safeName || 'Certificate'}.pdf`;
};

const preparePreview = () => {
    previewBodyHtml.value = toEmailHtml(bodyEditor);
    previewSignatureHtml.value = toEmailHtml(signatureEditor);
    previewIndex.value = 0;
};

const previewRecipient = computed(() => selectedRecipients.value[previewIndex.value] ?? selectedRecipients.value[0] ?? null);
const previewValues = computed(() => (previewRecipient.value ? placeholderValues(previewRecipient.value) : null));
const previewSubject = computed(() => (previewValues.value ? fillPlaceholders(form.subject, previewValues.value, false) : form.subject));
const previewBody = computed(() =>
    previewValues.value ? fillPlaceholders(previewBodyHtml.value, previewValues.value, true) : previewBodyHtml.value,
);
const previewHours = computed(() => {
    const hours = Number(previewRecipient.value?.certificate.hours ?? 0);
    return hours > 0 ? formatHours(hours) : '';
});

const showPreviousRecipient = () => {
    previewIndex.value = (previewIndex.value - 1 + selectedCount.value) % selectedCount.value;
};

const showNextRecipient = () => {
    previewIndex.value = (previewIndex.value + 1) % selectedCount.value;
};

/* ===================== NAVIGATION ===================== */

const goNext = () => {
    if (step.value === 1) {
        if (!selectedCount.value) return;
        step.value = 2;
    } else if (step.value === 2) {
        if (!editorsReady.value || !validateMessage()) return;
        preparePreview();
        step.value = 3;
    }
};

const goBack = () => {
    if (step.value > 1) step.value = (step.value - 1) as Step;
};

const goToStep = (target: Step) => {
    if (target < step.value) step.value = target;
};

const toggleHistory = () => {
    view.value = view.value === 'history' ? 'steps' : 'history';
};

const primaryDisabled = computed(() => {
    if (form.processing || !selectedCount.value) return true;
    return step.value > 1 && !editorsReady.value;
});

const footerSummary = computed(() => {
    if (view.value === 'history') return `${plural(props.history.length, 'send')} from this program`;
    if (view.value === 'result') return `${selectedType.value} · ${selectedBatch.value?.batch ?? ''}`;
    return `${plural(selectedCount.value, 'recipient')} · ${plural(selectedCount.value, 'PDF')}`;
});

/* ===================== SEND ===================== */

const send = async () => {
    if (form.processing || !editorsReady.value || !selectedBatch.value || !selectedCount.value) return;
    if (!validateMessage()) {
        step.value = 2;
        return;
    }

    const count = selectedCount.value;
    const confirmed = await confirmDialog(
        `Email ${selectedType.value} certificates to ${plural(count, 'participant')}? Each participant receives only their own PDF.`,
        { title: 'Send certificates?', confirmText: `Send ${plural(count, 'certificate')}`, cancelText: 'Keep reviewing', variant: 'default' },
    );
    if (!confirmed) return;

    form.type = selectedType.value;
    form.certificate_ids = selectedRecipients.value.map((recipient) => recipient.certificate.id);
    form.body = toEmailHtml(bodyEditor);
    form.signature = toEmailHtml(signatureEditor);

    form.post(route('programs.email-certificates', { program: props.program.id, batch: selectedBatch.value.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (response) => {
            const flash = (response.props as { flash?: { certificateEmailResult?: SendResult | null } }).flash;
            result.value = flash?.certificateEmailResult ?? { sent: count, failed: [] };
            view.value = 'result';
        },
        onError: (errors) => {
            view.value = 'steps';
            step.value = Object.keys(errors).some((key) => key === 'type' || key.startsWith('certificate_ids')) ? 1 : 2;
            if (errors.signature) showSignature.value = true;
        },
    });
};

/* ===================== HISTORY ===================== */

const formatDateTime = (value: string | null) =>
    value ? new Date(value).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';

/* ===================== OPEN / CLOSE ===================== */

const handleOpenChange = (value: boolean) => {
    if (!value && form.processing) return;
    emit('update:open', value);
};

// A half-written email shouldn't vanish on a stray click outside; Esc and × still close it.
const keepOpenOnOutsideClick = (event: Event) => {
    event.preventDefault();
};

const resetWindow = () => {
    const preset = props.preset;
    const batch =
        batches.value.find((candidate) => candidate.id === preset?.batchId) ?? batches.value.find(hasReadyCertificates) ?? batches.value[0] ?? null;

    view.value = 'steps';
    step.value = 1;
    result.value = null;
    search.value = '';
    showMissingEmail.value = false;
    showNotReady.value = false;
    showPlaceholders.value = false;
    showSignature.value = false;
    lastFocus.value = 'body';
    previewIndex.value = 0;
    form.reset();
    form.clearErrors();

    selectedBatchId.value = batch?.id ?? null;
    selectedType.value = preset?.type ?? bestTypeFor(batch);

    const presetRecipient = readyRecipients.value.find((recipient) => recipient.certificate.id === preset?.certificateId);
    if (presetRecipient) {
        recipientFilter.value = presetRecipient.emailed ? 'all' : 'waiting';
        selectedIds.value = [presetRecipient.certificate.id];
    } else {
        selectWaitingRecipients();
    }

    applyTemplate(selectedType.value);
};

watch(
    () => props.open,
    async (isOpen) => {
        destroyEditors();
        if (!isOpen) return;

        resetWindow();
        await nextTick();
        await initEditors();
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            class="flex max-h-[94vh] max-w-4xl flex-col gap-0 overflow-hidden !rounded-2xl p-0 shadow-2xl dark:border-slate-800"
            @interact-outside="keepOpenOnOutsideClick"
        >
            <!-- ── Header ── -->
            <div class="shrink-0 border-b bg-gradient-to-br px-5 pb-4 pt-5 transition-colors duration-300 sm:px-6" :class="accent.header">
                <div class="flex items-center gap-3 pr-8">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl shadow-sm transition-colors duration-300"
                        :class="accent.solid"
                    >
                        <Send class="h-[18px] w-[18px]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <DialogTitle class="text-base font-semibold leading-tight text-slate-900 dark:text-white">Email Certificates</DialogTitle>
                        <DialogDescription class="mt-0.5 truncate text-xs text-muted-foreground">{{ program.title }}</DialogDescription>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 shrink-0 gap-1.5 px-2.5 text-xs"
                        :class="view === 'history' ? 'bg-black/5 dark:bg-white/10' : ''"
                        :disabled="form.processing || view === 'result'"
                        :aria-pressed="view === 'history'"
                        @click="toggleHistory"
                    >
                        <History class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">History</span>
                    </Button>
                </div>

                <ol v-if="view === 'steps'" class="mt-4 flex items-center gap-1.5 sm:gap-2" aria-label="Steps">
                    <li v-for="(item, index) in STEPS" :key="item.number" class="flex items-center gap-1.5 sm:gap-2">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-full text-xs font-medium transition-opacity disabled:cursor-default"
                            :disabled="item.number >= step || form.processing"
                            :aria-current="item.number === step ? 'step' : undefined"
                            @click="goToStep(item.number)"
                        >
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold transition-all duration-300"
                                :class="
                                    item.number === step
                                        ? [accent.solid, 'shadow-sm']
                                        : item.number < step
                                          ? accent.soft
                                          : 'bg-white text-slate-400 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-500 dark:ring-slate-700'
                                "
                            >
                                <Check v-if="item.number < step" class="h-3.5 w-3.5" />
                                <template v-else>{{ item.number }}</template>
                            </span>
                            <span :class="item.number === step ? 'text-slate-900 dark:text-white' : 'text-muted-foreground'">{{ item.label }}</span>
                        </button>
                        <span v-if="index < STEPS.length - 1" class="h-px w-5 bg-slate-300 dark:bg-slate-700 sm:w-10" aria-hidden="true" />
                    </li>
                </ol>
                <p v-else class="mt-4 text-xs font-medium text-muted-foreground">
                    {{ view === 'history' ? 'Sent history' : 'Done' }}
                </p>
            </div>

            <!-- ── Scrollable body ── -->
            <div ref="scroller" class="min-h-0 flex-1 overflow-y-auto bg-slate-50/70 px-4 py-5 dark:bg-slate-950/50 sm:px-6 sm:py-6">
                <!-- ════════ STEP 1 · CHOOSE ════════ -->
                <section v-show="view === 'steps' && step === 1" class="cem-enter flex flex-col gap-6">
                    <div class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex items-start gap-2.5">
                                <Award class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                <div>
                                    <p class="text-sm font-semibold">What to send</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ selectedBatch?.batch }}<template v-if="scheduleLabel"> · {{ scheduleLabel }}</template>
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="batches.length <= PILL_BATCH_LIMIT"
                                class="inline-flex flex-wrap gap-1 rounded-xl bg-muted/70 p-1"
                                role="radiogroup"
                                aria-label="Batch"
                            >
                                <button
                                    v-for="batch in batches"
                                    :key="batch.id"
                                    type="button"
                                    role="radio"
                                    :aria-checked="batch.id === selectedBatchId"
                                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-all"
                                    :class="
                                        batch.id === selectedBatchId
                                            ? 'bg-background text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="selectBatch(batch.id)"
                                >
                                    {{ batch.batch }}
                                </button>
                            </div>
                            <select
                                v-else
                                :value="selectedBatchId ?? ''"
                                aria-label="Batch"
                                class="h-9 cursor-pointer rounded-lg border bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                @change="selectBatch(Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option v-for="batch in batches" :key="batch.id" :value="batch.id">{{ batch.batch }}</option>
                            </select>
                        </div>

                        <div
                            class="mt-5 grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 md:grid-cols-3"
                            role="radiogroup"
                            aria-label="Certificate type"
                        >
                            <button
                                v-for="tile in typeTiles"
                                :key="tile.type"
                                type="button"
                                role="radio"
                                :aria-checked="tile.type === selectedType"
                                :disabled="!tile.ready || form.processing"
                                class="relative flex items-center gap-3 rounded-xl border bg-card p-4 text-left transition-all duration-200"
                                :class="[
                                    tile.type === selectedType
                                        ? [accent.ring, 'border-transparent shadow-md']
                                        : 'hover:-translate-y-0.5 hover:shadow-md',
                                    tile.ready ? '' : 'cursor-not-allowed opacity-45 hover:translate-y-0 hover:shadow-none',
                                ]"
                                @click="selectType(tile.type)"
                            >
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                    :class="[typeColors(tile.type).bg, typeColors(tile.type).text]"
                                >
                                    <component :is="tile.icon" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0 pr-5">
                                    <span class="block truncate text-sm font-semibold">{{ tile.type }}</span>
                                    <span class="block text-xs tabular-nums text-muted-foreground"
                                        >{{ tile.ready }} ready · {{ tile.emailed }} emailed</span
                                    >
                                </span>
                                <span
                                    v-if="tile.type === selectedType"
                                    class="cem-pop absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full"
                                    :class="accent.solid"
                                >
                                    <Check class="h-3 w-3" />
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                            <div class="flex items-center gap-2.5">
                                <Users class="h-4 w-4 text-muted-foreground" />
                                <p class="text-sm font-semibold">Recipients</p>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums transition-colors duration-300"
                                    :class="accent.soft"
                                    aria-live="polite"
                                >
                                    {{ selectedCount }} selected
                                </span>
                            </div>
                            <div class="inline-flex rounded-lg bg-muted/70 p-0.5" role="group" aria-label="Show recipients">
                                <button
                                    v-for="option in filterOptions"
                                    :key="option.value"
                                    type="button"
                                    :aria-pressed="recipientFilter === option.value"
                                    class="rounded-md px-2.5 py-1 text-xs font-medium transition-all"
                                    :class="
                                        recipientFilter === option.value
                                            ? 'bg-background text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="setRecipientFilter(option.value)"
                                >
                                    {{ option.label }} <span class="tabular-nums opacity-60">{{ option.count }}</span>
                                </button>
                            </div>
                            <div class="ml-auto flex items-center gap-3 text-xs font-medium">
                                <button
                                    type="button"
                                    class="text-muted-foreground transition-colors hover:text-foreground disabled:opacity-40"
                                    :disabled="!visibleRecipients.length"
                                    @click="selectAllVisible"
                                >
                                    Select all
                                </button>
                                <button
                                    type="button"
                                    class="text-muted-foreground transition-colors hover:text-foreground disabled:opacity-40"
                                    :disabled="!selectedIds.length"
                                    @click="clearSelection"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <div v-if="showSearch" class="relative mt-4">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="search" placeholder="Search by name, employee code or certificate number…" class="h-9 pl-9 text-sm" />
                        </div>

                        <div v-if="visibleRecipients.length" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="recipient in visibleRecipients"
                                :key="recipient.certificate.id"
                                type="button"
                                :aria-pressed="isSelected(recipient)"
                                class="relative flex items-start gap-3 rounded-xl border bg-card p-3.5 text-left transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                                :class="isSelected(recipient) ? [accent.ring, 'border-transparent'] : ''"
                                @click="toggleRecipient(recipient)"
                            >
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                    :class="recipient.avatarColor"
                                >
                                    {{ recipient.initials }}
                                </span>
                                <span class="min-w-0 flex-1 pr-5">
                                    <span class="block truncate text-sm font-medium">{{ recipient.name }}</span>
                                    <span class="block truncate font-mono text-[11px] text-muted-foreground">
                                        {{ recipient.certificate.certificate_number ?? 'No certificate number' }}
                                    </span>
                                    <span class="mt-2 flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex max-w-full items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                            :title="recipient.certificate.file_name ?? ''"
                                        >
                                            <FileText class="h-3 w-3 shrink-0" />
                                            <span class="max-w-[9rem] truncate">{{ recipient.certificate.file_name ?? 'certificate.pdf' }}</span>
                                        </span>
                                        <span
                                            v-if="recipient.emailed"
                                            class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                            :title="emailedLabel(recipient.certificate)"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Emailed
                                        </span>
                                    </span>
                                </span>
                                <span
                                    class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full transition-all duration-200"
                                    :class="
                                        isSelected(recipient) ? [accent.solid, 'scale-100'] : 'scale-90 border border-slate-300 dark:border-slate-600'
                                    "
                                >
                                    <Check v-if="isSelected(recipient)" class="h-3 w-3" />
                                </span>
                            </button>
                        </div>
                        <div v-else class="mt-4 rounded-xl border border-dashed px-6 py-10 text-center">
                            <MailCheck class="mx-auto h-6 w-6 text-muted-foreground/60" />
                            <p class="mt-2 text-sm font-medium">
                                {{
                                    search.trim() && showSearch
                                        ? 'No one matches your search'
                                        : recipientFilter === 'waiting' && readyRecipients.length
                                          ? 'Everyone has been emailed'
                                          : 'No certificates ready to email'
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                <template v-if="recipientFilter === 'waiting' && readyRecipients.length && !search.trim()">
                                    Choose
                                    <button type="button" class="font-medium underline underline-offset-2" @click="setRecipientFilter('all')">
                                        All
                                    </button>
                                    to send it again.
                                </template>
                                <template v-else-if="!readyRecipients.length">Issued certificates with a PDF appear here.</template>
                            </p>
                        </div>

                        <ul v-if="recipientErrors.length" class="mt-4 flex flex-col gap-1">
                            <li v-for="message in recipientErrors" :key="message" class="text-xs font-medium text-red-600 dark:text-red-400">
                                {{ message }}
                            </li>
                        </ul>
                    </div>

                    <div v-if="missingEmail.length || notReady.length" class="flex flex-col gap-2">
                        <div
                            v-if="missingEmail.length"
                            class="overflow-hidden rounded-xl border border-amber-200/80 bg-amber-50/70 dark:border-amber-900/60 dark:bg-amber-950/20"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-xs font-medium text-amber-800 dark:text-amber-300"
                                :aria-expanded="showMissingEmail"
                                @click="showMissingEmail = !showMissingEmail"
                            >
                                <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                                {{ plural(missingEmail.length, 'participant') }} {{ missingEmail.length === 1 ? 'has' : 'have' }} no email
                                <ChevronDown
                                    class="ml-auto h-3.5 w-3.5 shrink-0 transition-transform"
                                    :class="showMissingEmail ? 'rotate-180' : ''"
                                />
                            </button>
                            <ul v-if="showMissingEmail" class="border-t border-amber-200/80 px-4 py-2 dark:border-amber-900/60">
                                <li
                                    v-for="recipient in missingEmail"
                                    :key="recipient.certificate.id"
                                    class="flex items-center justify-between gap-2 py-1 text-xs text-amber-800 dark:text-amber-300"
                                >
                                    <span class="truncate">{{ recipient.name }}</span>
                                    <span class="shrink-0 font-mono text-[10px] opacity-75">{{ recipient.participant.empcode }}</span>
                                </li>
                            </ul>
                        </div>

                        <div v-if="notReady.length" class="overflow-hidden rounded-xl border bg-card/60">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-xs font-medium text-muted-foreground"
                                :aria-expanded="showNotReady"
                                @click="showNotReady = !showNotReady"
                            >
                                <Info class="h-3.5 w-3.5 shrink-0" />
                                {{ notReadyLabel }}
                                <ChevronDown class="ml-auto h-3.5 w-3.5 shrink-0 transition-transform" :class="showNotReady ? 'rotate-180' : ''" />
                            </button>
                            <ul v-if="showNotReady" class="border-t px-4 py-2">
                                <li
                                    v-for="recipient in notReady"
                                    :key="recipient.certificate.id"
                                    class="flex items-center justify-between gap-2 py-1 text-xs text-muted-foreground"
                                >
                                    <span class="truncate">{{ recipient.name }}</span>
                                    <span class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium">
                                        {{ recipient.certificate.status === 'Pending' ? 'Pending' : 'No PDF' }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- ════════ STEP 2 · MESSAGE ════════ -->
                <section v-show="view === 'steps' && step === 2" class="cem-enter flex flex-col gap-6">
                    <div class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">Message</p>
                                <p class="text-xs text-muted-foreground">Pre-filled from the {{ selectedType }} template</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-40"
                                :disabled="!editorsReady"
                                @click="resetToTemplate"
                            >
                                <RotateCcw class="h-3.5 w-3.5" /> Reset to template
                            </button>
                        </div>

                        <div class="mt-5">
                            <div class="flex items-center justify-between gap-2">
                                <Label for="certificate-email-subject" class="text-xs font-medium">Subject</Label>
                                <span
                                    class="text-[11px] tabular-nums"
                                    :class="
                                        form.subject.length > SUBJECT_MAX - 50
                                            ? 'font-semibold text-amber-600 dark:text-amber-400'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ form.subject.length }}/{{ SUBJECT_MAX }}
                                </span>
                            </div>
                            <Input
                                id="certificate-email-subject"
                                v-model="form.subject"
                                :maxlength="SUBJECT_MAX"
                                placeholder="Enter a clear subject"
                                class="mt-1.5 h-10 text-sm"
                                :class="form.errors.subject ? 'border-red-400 focus-visible:ring-red-400 dark:border-red-700' : ''"
                                :aria-invalid="!!form.errors.subject"
                                @focus="onSubjectFocus"
                                @input="onSubjectInput"
                            />
                            <p v-if="form.errors.subject" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">
                                {{ form.errors.subject }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <p class="text-xs font-medium">Message</p>
                            <div
                                class="email-quill relative mt-1.5 overflow-hidden rounded-xl border"
                                :class="form.errors.body ? 'border-red-400 dark:border-red-700' : 'border-border'"
                            >
                                <div v-if="!editorsReady" class="absolute inset-0 z-10 flex items-center justify-center bg-card/90 px-6 text-center">
                                    <p v-if="editorLoadFailed" class="text-xs text-red-600 dark:text-red-400">
                                        The editor couldn't load. Check your internet connection, then close and reopen this window.
                                    </p>
                                    <p v-else class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Loader2 class="h-4 w-4 animate-spin" /> Loading editor…
                                    </p>
                                </div>
                                <div ref="bodyContainer" style="min-height: 260px" />
                            </div>
                            <p v-if="form.errors.body" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.body }}</p>
                        </div>

                        <div class="mt-4">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                                :aria-expanded="showPlaceholders"
                                @click="showPlaceholders = !showPlaceholders"
                            >
                                <Sparkles class="h-3.5 w-3.5" /> Insert details
                                <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="showPlaceholders ? 'rotate-180' : ''" />
                            </button>
                            <div v-show="showPlaceholders" class="cem-enter mt-3 rounded-xl bg-muted/50 p-3.5">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="key in PLACEHOLDERS"
                                        :key="key"
                                        type="button"
                                        class="rounded-md border bg-background px-2 py-1 font-mono text-[11px] text-foreground/80 transition-all hover:-translate-y-px hover:shadow-sm"
                                        :title="`Insert {${key}} at the cursor`"
                                        @mousedown.prevent
                                        @click="insertPlaceholder(key)"
                                    >
                                        {{ '{' + key + '}' }}
                                    </button>
                                </div>
                                <p class="mt-2.5 text-xs text-muted-foreground">Replaced with each participant's details when sent.</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border bg-card shadow-sm">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-muted/40 sm:px-6"
                            :aria-expanded="showSignature"
                            @click="showSignature = !showSignature"
                        >
                            <PenLine class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold">Signature</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ signaturePreview || 'No signature' }}</span>
                            </span>
                            <span class="shrink-0 text-xs font-medium text-muted-foreground">{{ showSignature ? 'Done' : 'Edit' }}</span>
                        </button>
                        <div v-show="showSignature" class="border-t px-5 pb-5 pt-4 sm:px-6">
                            <div
                                class="email-quill email-quill-signature relative overflow-hidden rounded-xl border"
                                :class="form.errors.signature ? 'border-red-400 dark:border-red-700' : 'border-border'"
                            >
                                <div v-if="!editorsReady" class="absolute inset-0 z-10 bg-card/90" />
                                <div ref="signatureContainer" style="min-height: 120px" />
                            </div>
                            <p v-if="form.errors.signature" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">
                                {{ form.errors.signature }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- ════════ STEP 3 · REVIEW ════════ -->
                <section v-show="view === 'steps' && step === 3" class="cem-enter flex flex-col gap-6">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border bg-card p-4 shadow-sm">
                            <p class="text-xs text-muted-foreground">Recipients</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ selectedCount }}</p>
                        </div>
                        <div class="rounded-2xl border bg-card p-4 shadow-sm">
                            <p class="text-xs text-muted-foreground">PDFs attached</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ selectedCount }}</p>
                        </div>
                        <div class="rounded-2xl border bg-card p-4 shadow-sm">
                            <p class="text-xs text-muted-foreground">Certificate type</p>
                            <span class="mt-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="accent.soft">
                                <span class="h-1.5 w-1.5 rounded-full" :class="typeColors(selectedType).dot" />
                                {{ selectedType }}
                            </span>
                        </div>
                    </div>

                    <div v-if="previewRecipient" class="overflow-hidden rounded-2xl border bg-card shadow-sm">
                        <div class="flex items-center gap-3 border-b bg-muted/40 px-4 py-2.5">
                            <span class="flex gap-1.5" aria-hidden="true">
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-600" />
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-600" />
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-600" />
                            </span>
                            <p class="text-xs font-medium text-muted-foreground">Preview</p>
                            <div class="ml-auto flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7"
                                    :disabled="selectedCount < 2"
                                    aria-label="Previous recipient"
                                    @click="showPreviousRecipient"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </Button>
                                <span class="min-w-[4.5rem] text-center text-xs tabular-nums text-muted-foreground">
                                    {{ previewIndex + 1 }} of {{ selectedCount }}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7"
                                    :disabled="selectedCount < 2"
                                    aria-label="Next recipient"
                                    @click="showNextRecipient"
                                >
                                    <ChevronRight class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <dl class="grid grid-cols-[64px_1fr] gap-y-2 border-b px-5 py-4 text-xs">
                            <dt class="text-muted-foreground">From</dt>
                            <dd class="font-medium">{{ SENDER_ADDRESS }}</dd>
                            <dt class="text-muted-foreground">To</dt>
                            <dd class="min-w-0 truncate font-medium">
                                {{ previewRecipient.name }}
                                <span class="font-normal text-muted-foreground">&lt;{{ previewRecipient.email }}&gt;</span>
                            </dd>
                            <dt class="text-muted-foreground">Subject</dt>
                            <dd class="break-words font-semibold">{{ previewSubject }}</dd>
                        </dl>

                        <!-- Mirrors resources/views/emails/certificate-available.blade.php -->
                        <div class="bg-[#F5F7FB] p-3 sm:p-6">
                            <div
                                class="mx-auto max-w-[640px] overflow-hidden rounded-2xl border border-[#E7EBF3] bg-white text-left"
                                style="font-family: Arial, Helvetica, sans-serif"
                            >
                                <div class="h-[3px]" style="background-image: linear-gradient(90deg, #c8a45d 0%, #e8d4a2 50%, #c8a45d 100%)" />
                                <div
                                    class="px-6 py-8 sm:px-8"
                                    style="background-image: linear-gradient(135deg, #0b1f44 0%, #123b82 60%, #1d4ed8 100%)"
                                >
                                    <div class="flex items-center gap-4">
                                        <span
                                            class="flex h-[54px] w-[54px] shrink-0 items-center justify-center rounded-full border border-[#E8D4A2]/45 bg-[#E8D4A2]/15 text-[26px]"
                                        >
                                            {{ templateFor(selectedType).emoji }}
                                        </span>
                                        <div>
                                            <p class="text-[11px] font-bold uppercase tracking-[2px] text-[#E8D4A2]">TESDA Development Institute</p>
                                            <p class="mt-1.5 text-[22px] font-extrabold leading-tight text-white sm:text-[25px]">
                                                Certificate of {{ selectedType }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="my-4 h-0.5 w-[42px] bg-[#C8A45D]" />
                                    <p class="text-[13.5px] leading-normal text-[#C9D6EF]">
                                        Awarded to <strong class="text-white">{{ previewRecipient.name }}</strong> · {{ program.title }}
                                    </p>
                                </div>

                                <div
                                    v-if="previewBody"
                                    class="email-preview-body px-6 pb-1.5 pt-8 text-[14.5px] leading-[1.55] text-[#1E293B] sm:px-8"
                                    v-html="previewBody"
                                />

                                <div class="px-6 pt-6 sm:px-8">
                                    <div class="rounded-xl border border-[#C8A45D] bg-[#FFFDF7] px-5 pb-6 pt-7 text-center sm:px-8">
                                        <p class="text-[10.5px] font-extrabold uppercase tracking-[2.4px] text-[#9A7B3C]">Your Certificate</p>
                                        <p
                                            class="mt-2.5 text-[21px] font-bold leading-snug text-[#0B1F44] sm:text-[24px]"
                                            style="font-family: Georgia, 'Times New Roman', serif"
                                        >
                                            Certificate of {{ selectedType }}
                                        </p>
                                        <div class="mx-auto mb-4 mt-3.5 h-0.5 w-9 bg-[#C8A45D]" />
                                        <dl class="grid grid-cols-[110px_1fr] text-left text-[13.5px] sm:grid-cols-[130px_1fr]">
                                            <dt class="py-1.5 text-[11.5px] font-semibold text-[#64748B]">Type</dt>
                                            <dd class="py-1.5 font-bold text-[#0B1F44]">{{ selectedType }}</dd>
                                            <template v-if="previewRecipient.certificate.certificate_number">
                                                <dt class="border-t border-[#F1EAD9] py-1.5 text-[11.5px] font-semibold text-[#64748B]">
                                                    Certificate No.
                                                </dt>
                                                <dd class="border-t border-[#F1EAD9] py-1.5 font-mono font-bold text-[#0B1F44]">
                                                    {{ previewRecipient.certificate.certificate_number }}
                                                </dd>
                                            </template>
                                            <dt class="border-t border-[#F1EAD9] py-1.5 text-[11.5px] font-semibold text-[#64748B]">Program</dt>
                                            <dd class="border-t border-[#F1EAD9] py-1.5 font-bold text-[#0B1F44]">{{ program.title }}</dd>
                                            <dt class="border-t border-[#F1EAD9] py-1.5 text-[11.5px] font-semibold text-[#64748B]">Batch</dt>
                                            <dd class="border-t border-[#F1EAD9] py-1.5 font-bold text-[#0B1F44]">{{ selectedBatch?.batch }}</dd>
                                            <template v-if="scheduleLabel">
                                                <dt class="border-t border-[#F1EAD9] py-1.5 text-[11.5px] font-semibold text-[#64748B]">
                                                    Training Dates
                                                </dt>
                                                <dd class="border-t border-[#F1EAD9] py-1.5 font-bold text-[#0B1F44]">{{ scheduleLabel }}</dd>
                                            </template>
                                            <template v-if="previewRecipient.certificate.issued_date">
                                                <dt class="border-t border-[#F1EAD9] py-1.5 text-[11.5px] font-semibold text-[#64748B]">
                                                    Date Issued
                                                </dt>
                                                <dd class="border-t border-[#F1EAD9] py-1.5 font-bold text-[#0B1F44]">
                                                    {{ formatDate(previewRecipient.certificate.issued_date) }}
                                                </dd>
                                            </template>
                                            <template v-if="previewHours">
                                                <dt class="border-t border-[#F1EAD9] py-1.5 text-[11.5px] font-semibold text-[#64748B]">Hours</dt>
                                                <dd class="border-t border-[#F1EAD9] py-1.5 font-bold text-[#0B1F44]">
                                                    {{ previewHours }} {{ previewHours === '1' ? 'hour' : 'hours' }}
                                                </dd>
                                            </template>
                                        </dl>
                                    </div>
                                </div>

                                <div class="px-6 pt-6 text-center sm:px-8">
                                    <p class="text-[13.5px] text-[#475569]">📎 Your certificate is attached to this email as a PDF.</p>
                                    <span
                                        class="mt-4 inline-block rounded-[10px] border-b-2 border-[#C8A45D] bg-[#0B1F44] px-[26px] py-[13px] text-sm font-bold tracking-[0.2px] text-white"
                                    >
                                        View in My Certificates
                                    </span>
                                </div>

                                <div class="px-6 pb-8 pt-7 sm:px-8">
                                    <template v-if="previewSignatureHtml">
                                        <div class="mb-4 h-px bg-[#E7EBF3]" />
                                        <div
                                            class="email-preview-signature text-[12.5px] leading-normal text-[#64748B]"
                                            v-html="previewSignatureHtml"
                                        />
                                    </template>
                                </div>

                                <div class="h-0.5" style="background-image: linear-gradient(90deg, #c8a45d 0%, #e8d4a2 50%, #c8a45d 100%)" />
                                <div class="flex flex-col gap-2 bg-[#0B1F44] px-6 py-6 sm:flex-row sm:justify-between sm:px-8">
                                    <div>
                                        <p class="text-[11.5px] font-extrabold tracking-[0.3px] text-white">TESDA Development Institute</p>
                                        <p class="mt-0.5 text-[10.5px] text-[#9FB3D9]">TESDA Learning &amp; Development</p>
                                    </div>
                                    <p class="text-[10px] leading-normal text-[#8296C2] sm:text-right">
                                        TDI L&amp;D Monitoring System<br />
                                        Automated Notification · Please do not reply directly to this email<br />
                                        © {{ new Date().getFullYear() }} TESDA
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 border-t px-5 py-3.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400"
                            >
                                <FileText class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-medium">{{ attachmentName(previewRecipient) }}</p>
                                <p class="text-[11px] text-muted-foreground">PDF · only for {{ previewRecipient.name }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ════════ RESULT ════════ -->
                <section v-if="view === 'result' && result" class="cem-enter flex flex-col items-center gap-6 py-6 text-center">
                    <div
                        class="cem-pop flex h-16 w-16 items-center justify-center rounded-full"
                        :class="
                            result.sent
                                ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400'
                                : 'bg-amber-100 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400'
                        "
                    >
                        <svg v-if="result.sent" viewBox="0 0 24 24" class="h-8 w-8" aria-hidden="true">
                            <path
                                class="cem-draw"
                                d="M5 12.5l4.5 4.5L19 7.5"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        <AlertTriangle v-else class="h-7 w-7" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold">
                            {{ result.sent ? `${plural(result.sent, 'certificate')} sent` : 'No certificates were sent' }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                result.sent
                                    ? 'Each participant received only their own PDF.'
                                    : 'Check the mail settings or try again in a few minutes.'
                            }}
                        </p>
                    </div>
                    <div
                        v-if="result.failed.length"
                        class="w-full max-w-lg rounded-2xl border border-amber-200/80 bg-amber-50/70 p-5 text-left dark:border-amber-900/60 dark:bg-amber-950/20"
                    >
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ result.failed.length }} couldn't be sent</p>
                        <p class="mt-0.5 text-xs text-amber-800/80 dark:text-amber-300/80">They stay "not yet emailed", so you can try again.</p>
                        <ul class="mt-3 divide-y divide-amber-200/70 dark:divide-amber-900/50">
                            <li v-for="failure in result.failed" :key="failure.name + failure.reason" class="py-2">
                                <p class="text-xs font-medium text-amber-900 dark:text-amber-200">{{ failure.name }}</p>
                                <p class="text-[11px] text-amber-800/80 dark:text-amber-300/80">{{ failure.reason }}</p>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- ════════ HISTORY ════════ -->
                <section v-if="view === 'history'" class="cem-enter flex flex-col gap-4">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 self-start text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                        @click="view = 'steps'"
                    >
                        <ArrowLeft class="h-3.5 w-3.5" /> Back to email certificates
                    </button>

                    <div v-if="!history.length" class="rounded-2xl border border-dashed bg-card px-6 py-14 text-center">
                        <MailCheck class="mx-auto h-7 w-7 text-muted-foreground/60" />
                        <p class="mt-3 text-sm font-semibold">Nothing sent yet</p>
                        <p class="mt-1 text-xs text-muted-foreground">Certificate emails you send appear here with the date, sender and results.</p>
                    </div>
                    <ul v-else class="flex flex-col gap-3">
                        <li v-for="entry in history" :key="entry.id" class="rounded-2xl border bg-card p-5 shadow-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="[typeColors(entry.certificate_type).bg, typeColors(entry.certificate_type).text]"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="typeColors(entry.certificate_type).dot" />
                                    {{ entry.certificate_type ?? 'Certificate' }}
                                </span>
                                <span class="text-xs text-muted-foreground">{{ entry.batch ?? 'Deleted batch' }}</span>
                                <span class="ml-auto text-xs tabular-nums text-muted-foreground">{{ formatDateTime(entry.sent_at) }}</span>
                            </div>
                            <p class="mt-2.5 truncate text-sm font-medium" :title="entry.subject">{{ entry.subject }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span>
                                    by <span class="font-medium text-foreground">{{ entry.sent_by ?? 'system' }}</span>
                                </span>
                                <span class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                                    <MailCheck class="h-3.5 w-3.5" /> {{ entry.sent_count }} sent
                                </span>
                                <span v-if="entry.failed_count" class="inline-flex items-center gap-1 font-medium text-amber-600 dark:text-amber-400">
                                    <AlertTriangle class="h-3.5 w-3.5" /> {{ entry.failed_count }} failed
                                </span>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- ── Footer ── -->
            <div class="flex shrink-0 items-center justify-between gap-3 border-t bg-card px-5 py-3.5 sm:px-6">
                <p class="truncate text-xs tabular-nums text-muted-foreground">{{ footerSummary }}</p>
                <div class="flex shrink-0 items-center gap-2">
                    <Button v-if="view === 'steps' && step > 1" variant="ghost" size="sm" :disabled="form.processing" @click="goBack">
                        <ChevronLeft class="mr-0.5 h-4 w-4" /> Back
                    </Button>
                    <Button v-if="view === 'history'" variant="ghost" size="sm" @click="view = 'steps'">
                        <ChevronLeft class="mr-0.5 h-4 w-4" /> Back
                    </Button>
                    <Button
                        v-if="view === 'steps'"
                        size="sm"
                        class="min-w-[7rem] shadow-sm transition-colors duration-300"
                        :class="accent.button"
                        :disabled="primaryDisabled"
                        @click="step === 3 ? send() : goNext()"
                    >
                        <template v-if="step < 3"> Next <ChevronRight class="ml-0.5 h-4 w-4" /> </template>
                        <template v-else>
                            <Loader2 v-if="form.processing" class="mr-1.5 h-3.5 w-3.5 animate-spin" />
                            <Send v-else class="mr-1.5 h-3.5 w-3.5" />
                            {{ form.processing ? 'Sending…' : `Send ${plural(selectedCount, 'certificate')}` }}
                        </template>
                    </Button>
                    <Button v-if="view === 'result'" size="sm" class="min-w-[6rem] shadow-sm" :class="accent.button" @click="handleOpenChange(false)">
                        Done
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Soft fade/slide each time a step or view appears (also when shown again via v-show). */
.cem-enter {
    animation: cem-enter 0.28s ease-out;
}

.cem-pop {
    animation: cem-pop 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.cem-draw {
    stroke-dasharray: 24;
    stroke-dashoffset: 24;
    animation: cem-draw 0.45s 0.2s ease-out forwards;
}

@keyframes cem-enter {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes cem-pop {
    from {
        opacity: 0;
        transform: scale(0.6);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes cem-draw {
    to {
        stroke-dashoffset: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cem-enter,
    .cem-pop {
        animation: none;
    }
    .cem-draw {
        animation: none;
        stroke-dashoffset: 0;
    }
}
</style>
