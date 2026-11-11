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
    SIGNATURE_TOOLBAR,
    toEmailHtml,
    useEmailEditors,
} from '@/composables/useEmailEditors';
import { useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCheck,
    ChevronDown,
    Clock,
    Eye,
    History,
    Info,
    Loader2,
    Mail,
    MailCheck,
    MessageSquareText,
    PenLine,
    Plus,
    Send,
    Type,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

type Attendance = 'Complete' | 'Pending' | 'Absent';
type AttendanceFilter = 'all' | Attendance;

interface NotificationLog {
    id: number;
    sent_by: string | null;
    sent_by_name: string | null;
    subject: string;
    recipients_count: number;
    created_at: string;
}

interface BatchParticipant {
    id: number;
    empcode: string;
    attendance?: string | null;
    user_email?: string | null;
    employee?: { name?: string | null } | null;
}

interface NotifyBatch {
    id: number;
    batch: string;
    status?: string | null;
    date_start?: string | null;
    date_end?: string | null;
    venue?: string | null;
    participants?: BatchParticipant[];
    participant_notification_logs?: NotificationLog[];
}

interface Person {
    empcode: string;
    name: string;
    email: string | null;
    attendance: Attendance;
}

interface NotifyFormFields {
    to: string[];
    subject: string;
    body: string;
    signature: string;
}

const props = defineProps<{
    open: boolean;
    program: { id: number; title: string } | null;
    batch: NotifyBatch | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { confirmDialog } = useConfirm();
const page = usePage();

const SUBJECT_MAX = 500;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const form = useForm<NotifyFormFields>({
    to: [],
    subject: '',
    body: '',
    signature: '',
});

const formErrors = computed(() => form.errors as Record<string, string>);

/* ===================== SCHEDULE ===================== */

// Same wording as the email's program card, e.g. "March 3–5, 2026" or "March 30 – April 2, 2026".
const scheduleLabel = computed(() => formatEmailSchedule(props.batch?.date_start, props.batch?.date_end));

/* ===================== RECIPIENTS ===================== */

const normalizeAttendance = (value?: string | null): Attendance => (value === 'Complete' || value === 'Absent' ? value : 'Pending');

const people = computed<Person[]>(() =>
    (props.batch?.participants ?? []).map((participant) => ({
        empcode: participant.empcode,
        name: participant.employee?.name?.trim() || participant.empcode,
        email: participant.user_email?.trim() || null,
        attendance: normalizeAttendance(participant.attendance),
    })),
);

const reachablePeople = computed(() => people.value.filter((person) => person.email));
const unreachablePeople = computed(() => people.value.filter((person) => !person.email));
const personByEmail = computed(() => new Map(reachablePeople.value.map((person) => [person.email!.toLowerCase(), person])));

const peopleIn = (filter: AttendanceFilter) => reachablePeople.value.filter((person) => filter === 'all' || person.attendance === filter);

const filterOptions = computed(() => [
    { value: 'all' as const, label: 'All', dot: 'bg-blue-500', count: peopleIn('all').length },
    { value: 'Complete' as const, label: 'Complete', dot: 'bg-emerald-500', count: peopleIn('Complete').length },
    { value: 'Pending' as const, label: 'Pending', dot: 'bg-amber-500', count: peopleIn('Pending').length },
    { value: 'Absent' as const, label: 'Absent', dot: 'bg-red-500', count: peopleIn('Absent').length },
]);

const activeFilter = ref<AttendanceFilter>('all');
const toList = ref<string[]>([]);
const newEmail = ref('');
const newEmailError = ref('');
const showUnreachable = ref(false);

const uniqueEmails = (emails: string[]) => {
    const seen = new Set<string>();
    return emails.filter((email) => {
        const key = email.toLowerCase();
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
};

const isListed = (email: string) => toList.value.some((listed) => listed.toLowerCase() === email.toLowerCase());

// Addresses typed in by hand that don't belong to any participant of this batch.
const extraEmails = computed(() => toList.value.filter((email) => !personByEmail.value.has(email.toLowerCase())));

const clearRecipientErrors = () => {
    const keys = Object.keys(formErrors.value).filter((key) => key === 'to' || key.startsWith('to.'));
    // Per-item keys such as "to.1" come from the server; clearErrors accepts them at runtime.
    if (keys.length) form.clearErrors(...(keys as Array<keyof NotifyFormFields>));
};

const recipientErrors = computed(() =>
    Object.entries(formErrors.value)
        .filter(([key]) => key === 'to' || key.startsWith('to.'))
        .map(([, message]) => message),
);

// Picking a filter swaps the participant recipients for that attendance group; hand-added addresses stay.
const applyFilter = (filter: AttendanceFilter) => {
    activeFilter.value = filter;
    toList.value = uniqueEmails([...peopleIn(filter).map((person) => person.email!), ...extraEmails.value]);
    clearRecipientErrors();
};

const selectAll = () => {
    toList.value = uniqueEmails([...toList.value, ...peopleIn(activeFilter.value).map((person) => person.email!)]);
    clearRecipientErrors();
};

const clearRecipients = () => {
    toList.value = [];
};

const removeEmail = (email: string) => {
    toList.value = toList.value.filter((listed) => listed !== email);
};

// Accepts one address or several separated by commas, semicolons or spaces.
const addEmail = () => {
    const candidates = newEmail.value
        .split(/[\s,;]+/)
        .map((value) => value.trim())
        .filter(Boolean);
    if (!candidates.length) return;

    const invalid = candidates.filter((email) => !EMAIL_PATTERN.test(email));
    const duplicates = candidates.filter((email) => EMAIL_PATTERN.test(email) && isListed(email));
    const additions = uniqueEmails(candidates.filter((email) => EMAIL_PATTERN.test(email) && !isListed(email)));

    toList.value = [...toList.value, ...additions];
    clearRecipientErrors();

    if (invalid.length) {
        newEmailError.value = `${invalid.join(', ')} ${invalid.length === 1 ? 'is not a valid email address' : 'are not valid email addresses'}.`;
        newEmail.value = invalid.join(', ');
    } else if (duplicates.length && !additions.length) {
        newEmailError.value = `${duplicates.join(', ')} ${duplicates.length === 1 ? 'is' : 'are'} already in the list.`;
    } else {
        newEmailError.value = '';
        newEmail.value = '';
    }
};

const initials = (name: string) =>
    name
        .split(/[\s@._-]+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

const recipientChips = computed(() =>
    toList.value.map((email) => {
        const person = personByEmail.value.get(email.toLowerCase());
        return {
            email,
            person,
            label: person?.name ?? email,
            hint: person ? `${person.name} <${email}> · ${person.attendance}` : `${email} (added manually)`,
        };
    }),
);

const recipientCountLabel = computed(() => `${toList.value.length} recipient${toList.value.length === 1 ? '' : 's'}`);

/* ===================== EDITORS (shared Quill setup, see useEmailEditors) ===================== */

const { editorsReady, editorLoadFailed, mountEditors, unmountEditors } = useEmailEditors(() => props.open);

let bodyEditor: QuillEditor = null;
let signatureEditor: QuillEditor = null;
const bodyContainer = ref<HTMLElement | null>(null);
const signatureContainer = ref<HTMLElement | null>(null);

const buildDefaultBody = () => {
    const title = escapeHtml(props.program?.title ?? 'the program');
    const batch = props.batch?.batch ? ` (${escapeHtml(props.batch.batch)})` : '';
    const dates = scheduleLabel.value ? `, scheduled on <strong>${escapeHtml(scheduleLabel.value)}</strong>` : '';
    return `<p>Good day, participants!</p>
<p>We are reaching out to everyone enrolled in <strong>${title}</strong>${batch}${dates}.</p>
<p><br></p>
<p>Thank you, and we look forward to your participation.</p>`;
};

const initEditors = async () => {
    const editors = await mountEditors([
        { container: bodyContainer, toolbar: BODY_TOOLBAR, placeholder: 'Write your message to the participants…', html: buildDefaultBody() },
        {
            container: signatureContainer,
            toolbar: SIGNATURE_TOOLBAR,
            placeholder: 'Your signature (optional)…',
            html: buildDefaultSignature((page.props as any).auth?.user?.name ?? ''),
        },
    ]);
    if (!editors) return;

    [bodyEditor, signatureEditor] = editors;
    bodyEditor.on('text-change', () => {
        if (formErrors.value.body) form.clearErrors('body');
    });
    signatureEditor.on('text-change', () => {
        if (formErrors.value.signature) form.clearErrors('signature');
    });
};

const destroyEditors = () => {
    unmountEditors();
    bodyEditor = null;
    signatureEditor = null;
};

/* ===================== WRITE / PREVIEW ===================== */

const activeTab = ref<'write' | 'preview'>('write');
const previewBody = ref('');
const previewSignature = ref('');

const openWriteTab = () => {
    activeTab.value = 'write';
};

const openPreviewTab = () => {
    previewBody.value = toEmailHtml(bodyEditor);
    previewSignature.value = toEmailHtml(signatureEditor);
    activeTab.value = 'preview';
};

/* ===================== SENT HISTORY ===================== */

const history = computed(() => props.batch?.participant_notification_logs ?? []);
const showHistory = ref(false);

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

/* ===================== SEND ===================== */

const validateBeforeSend = () => {
    form.clearErrors();
    const errors: Record<string, string> = {};

    if (!toList.value.length) errors.to = 'Add at least one recipient.';
    if (!form.subject.trim()) errors.subject = 'Enter a subject for this notification.';
    else if (form.subject.length > SUBJECT_MAX) errors.subject = `Keep the subject within ${SUBJECT_MAX} characters.`;
    if (isEditorEmpty(bodyEditor)) errors.body = 'Write a message before sending.';

    if (Object.keys(errors).length) {
        form.setError(errors as any);
        activeTab.value = 'write';
        return false;
    }
    return true;
};

const send = async () => {
    if (form.processing || !editorsReady.value || !props.program || !props.batch || !validateBeforeSend()) return;

    const count = toList.value.length;
    const confirmed = await confirmDialog(
        `Send this email to ${count} ${count === 1 ? 'recipient' : 'recipients'}? Everyone receives it as BCC, so no one sees the other addresses.`,
        { title: 'Send notification?', confirmText: 'Send notification', cancelText: 'Keep editing', variant: 'default' },
    );
    if (!confirmed) return;

    form.to = [...toList.value];
    form.body = toEmailHtml(bodyEditor);
    form.signature = toEmailHtml(signatureEditor);

    form.post(route('programs.notify-participants', { program: props.program.id, batch: props.batch.id }), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
        onError: () => {
            activeTab.value = 'write';
        },
    });
};

/* ===================== OPEN / CLOSE ===================== */

const handleOpenChange = (value: boolean) => {
    if (!value && form.processing) return;
    emit('update:open', value);
};

// A half-written email shouldn't vanish on a stray click outside; Esc, Cancel and × still close it.
const keepOpenOnOutsideClick = (event: Event) => {
    event.preventDefault();
};

const resetComposer = () => {
    activeTab.value = 'write';
    activeFilter.value = 'all';
    toList.value = uniqueEmails(reachablePeople.value.map((person) => person.email!));
    newEmail.value = '';
    newEmailError.value = '';
    showUnreachable.value = false;
    showHistory.value = false;
    previewBody.value = '';
    previewSignature.value = '';
    form.reset();
    form.clearErrors();
};

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            resetComposer();
            destroyEditors();
            await nextTick();
            await initEditors();
        } else {
            destroyEditors();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            class="flex max-h-[94vh] max-w-3xl flex-col gap-0 overflow-hidden !rounded-2xl border-0 p-0 shadow-2xl dark:border dark:border-slate-800 [&>button:last-child]:text-white [&>button:last-child]:opacity-80"
            @interact-outside="keepOpenOnOutsideClick"
        >
            <!-- ── Gradient header ── -->
            <div
                class="relative shrink-0 overflow-hidden bg-gradient-to-br from-blue-600 via-blue-500 to-indigo-500 px-6 pb-4 pt-5 dark:from-blue-900 dark:via-blue-800 dark:to-indigo-900"
            >
                <div
                    class="pointer-events-none absolute inset-0 opacity-20"
                    style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 18px 18px"
                ></div>
                <div class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 rounded-full bg-white/10 blur-2xl"></div>

                <div class="relative z-10 flex items-center gap-3 pr-6">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                        <Send class="h-5 w-5 text-white" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <DialogTitle class="text-base font-bold leading-tight text-white">Notify Participants</DialogTitle>
                        <DialogDescription class="mt-0.5 truncate text-xs text-blue-100 dark:text-blue-200">
                            {{ program?.title }} · {{ batch?.batch }}<template v-if="scheduleLabel"> · {{ scheduleLabel }}</template>
                        </DialogDescription>
                    </div>
                    <span
                        v-if="batch?.status === 'Rescheduled'"
                        class="hidden shrink-0 rounded-full bg-amber-300/20 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-50 ring-1 ring-amber-200/40 sm:inline-flex"
                        title="Rescheduled batches can still be notified — a good way to announce the new schedule."
                    >
                        Rescheduled
                    </span>
                </div>

                <!-- Write / Preview tabs -->
                <div class="relative z-10 mt-4 inline-flex rounded-xl bg-white/10 p-1 ring-1 ring-white/20" role="tablist" aria-label="Compose mode">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'write'"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="
                            activeTab === 'write'
                                ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-900 dark:text-blue-300'
                                : 'text-blue-50 hover:bg-white/10'
                        "
                        @click="openWriteTab"
                    >
                        <PenLine class="h-3.5 w-3.5" /> Write
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'preview'"
                        :disabled="!editorsReady"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors disabled:opacity-50"
                        :class="
                            activeTab === 'preview'
                                ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-900 dark:text-blue-300'
                                : 'text-blue-50 hover:bg-white/10'
                        "
                        @click="openPreviewTab"
                    >
                        <Eye class="h-3.5 w-3.5" /> Preview
                    </button>
                </div>
            </div>

            <!-- ── Scrollable body ── -->
            <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50/80 px-4 py-4 dark:bg-slate-950/60 sm:px-5">
                <!-- ════════ WRITE ════════ -->
                <div v-show="activeTab === 'write'" class="flex flex-col gap-4" role="tabpanel">
                    <!-- a. Recipients -->
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-300"
                                >
                                    <Users class="h-3.5 w-3.5" />
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-100">
                                        Recipients <span class="font-normal text-muted-foreground">(To)</span>
                                    </p>
                                    <p class="text-[11px] text-muted-foreground">Sent as BCC, so recipients won't see each other's addresses</p>
                                </div>
                            </div>
                            <span
                                class="rounded-full bg-blue-600 px-2.5 py-1 text-[11px] font-bold tabular-nums text-white shadow-sm dark:bg-blue-500"
                                aria-live="polite"
                            >
                                {{ recipientCountLabel }}
                            </span>
                        </div>

                        <!-- Quick filters -->
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <div
                                class="inline-flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800/80"
                                role="group"
                                aria-label="Filter by attendance"
                            >
                                <button
                                    v-for="option in filterOptions"
                                    :key="option.value"
                                    type="button"
                                    :aria-pressed="activeFilter === option.value"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold transition-colors"
                                    :class="
                                        activeFilter === option.value
                                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white'
                                            : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'
                                    "
                                    @click="applyFilter(option.value)"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="option.dot" />
                                    {{ option.label }}
                                    <span class="rounded-full bg-slate-200/80 px-1.5 text-[10px] tabular-nums dark:bg-slate-700 dark:text-slate-200">
                                        {{ option.count }}
                                    </span>
                                </button>
                            </div>
                            <div class="ml-auto flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 px-2 text-[11px] text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950/50"
                                    @click="selectAll"
                                >
                                    <CheckCheck class="mr-1 h-3.5 w-3.5" /> Select all
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 px-2 text-[11px] text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40"
                                    :disabled="!toList.length"
                                    @click="clearRecipients"
                                >
                                    <X class="mr-1 h-3.5 w-3.5" /> Clear
                                </Button>
                            </div>
                        </div>

                        <!-- Recipient chips -->
                        <div
                            class="mt-3 flex max-h-40 min-h-[44px] flex-wrap content-start gap-1.5 overflow-y-auto rounded-xl border bg-slate-50/70 p-2 dark:bg-slate-950/50"
                            :class="recipientErrors.length ? 'border-red-300 dark:border-red-800' : 'border-slate-200 dark:border-slate-700'"
                        >
                            <span
                                v-for="chip in recipientChips"
                                :key="chip.email"
                                :title="chip.hint"
                                class="inline-flex max-w-full items-center gap-1 rounded-full py-0.5 pl-0.5 pr-1 text-[11px] font-medium ring-1"
                                :class="
                                    chip.person
                                        ? 'bg-blue-50 text-blue-800 ring-blue-200 dark:bg-blue-950/60 dark:text-blue-200 dark:ring-blue-800'
                                        : 'bg-violet-50 text-violet-800 ring-violet-200 dark:bg-violet-950/60 dark:text-violet-200 dark:ring-violet-800'
                                "
                            >
                                <span
                                    class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full text-[8px] font-bold text-white"
                                    :class="chip.person ? 'bg-blue-600 dark:bg-blue-500' : 'bg-violet-600 dark:bg-violet-500'"
                                >
                                    <template v-if="chip.person">{{ initials(chip.label) }}</template>
                                    <Mail v-else class="h-2.5 w-2.5" />
                                </span>
                                <span class="truncate">{{ chip.label }}</span>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-full p-0.5 transition-colors hover:bg-black/10 hover:text-red-600 dark:hover:bg-white/10 dark:hover:text-red-400"
                                    :aria-label="`Remove ${chip.email}`"
                                    @click="removeEmail(chip.email)"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </span>
                            <p v-if="!recipientChips.length" class="self-center px-1 text-[11px] italic text-muted-foreground">
                                No recipients yet. Pick a filter, use Select all, or add an email below.
                            </p>
                        </div>
                        <p v-for="message in recipientErrors" :key="message" class="mt-1.5 text-[11px] font-medium text-red-600 dark:text-red-400">
                            {{ message }}
                        </p>

                        <!-- Add an email manually -->
                        <div class="mt-3 flex gap-2">
                            <div class="relative flex-1">
                                <Mail class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="newEmail"
                                    type="text"
                                    inputmode="email"
                                    autocomplete="off"
                                    class="h-8 pl-8 text-xs dark:bg-slate-950/50"
                                    :class="newEmailError ? 'border-red-400 focus-visible:ring-red-400 dark:border-red-700' : ''"
                                    placeholder="Add another email address…"
                                    :aria-invalid="!!newEmailError"
                                    aria-label="Add an email address"
                                    @keydown.enter.prevent="addEmail"
                                    @input="newEmailError = ''"
                                />
                            </div>
                            <Button variant="outline" size="sm" class="h-8 text-xs dark:border-slate-700" @click="addEmail">
                                <Plus class="mr-1 h-3.5 w-3.5" /> Add
                            </Button>
                        </div>
                        <p v-if="newEmailError" class="mt-1.5 text-[11px] font-medium text-red-600 dark:text-red-400">{{ newEmailError }}</p>

                        <!-- Participants without an email -->
                        <div
                            v-if="unreachablePeople.length"
                            class="mt-3 overflow-hidden rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800/70 dark:bg-amber-950/30"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-[11px] font-semibold text-amber-800 transition-colors hover:bg-amber-100/60 dark:text-amber-300 dark:hover:bg-amber-900/30"
                                :aria-expanded="showUnreachable"
                                @click="showUnreachable = !showUnreachable"
                            >
                                <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                                {{ unreachablePeople.length }} participant{{ unreachablePeople.length === 1 ? '' : 's' }} without an email won't
                                receive this
                                <ChevronDown class="ml-auto h-3.5 w-3.5 shrink-0 transition-transform" :class="showUnreachable ? 'rotate-180' : ''" />
                            </button>
                            <ul v-if="showUnreachable" class="max-h-32 overflow-y-auto border-t border-amber-200 px-3 py-2 dark:border-amber-800/70">
                                <li
                                    v-for="person in unreachablePeople"
                                    :key="person.empcode"
                                    class="flex items-center justify-between gap-2 py-0.5 text-[11px] text-amber-800 dark:text-amber-300"
                                >
                                    <span class="truncate">{{ person.name }}</span>
                                    <span class="shrink-0 font-mono text-[10px] opacity-75">{{ person.empcode }}</span>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <!-- b. Subject -->
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300"
                                >
                                    <Type class="h-3.5 w-3.5" />
                                </span>
                                <Label for="notify-subject" class="text-xs font-bold text-slate-800 dark:text-slate-100">
                                    Subject <span class="text-red-500">*</span>
                                </Label>
                            </div>
                            <span
                                class="text-[10px] tabular-nums"
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
                            id="notify-subject"
                            v-model="form.subject"
                            :maxlength="SUBJECT_MAX"
                            placeholder="Enter a clear subject"
                            class="mt-2.5 h-9 text-sm dark:bg-slate-950/50"
                            :class="form.errors.subject ? 'border-red-400 focus-visible:ring-red-400 dark:border-red-700' : ''"
                            :aria-invalid="!!form.errors.subject"
                            @input="form.clearErrors('subject')"
                        />
                        <p v-if="form.errors.subject" class="mt-1.5 text-[11px] font-medium text-red-600 dark:text-red-400">
                            {{ form.errors.subject }}
                        </p>
                    </section>

                    <!-- c. Message -->
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-300"
                            >
                                <MessageSquareText class="h-3.5 w-3.5" />
                            </span>
                            <div>
                                <p class="text-xs font-bold text-slate-800 dark:text-slate-100">Message <span class="text-red-500">*</span></p>
                                <p class="text-[11px] text-muted-foreground">
                                    Format it the way you would in Gmail; the preview shows the final email
                                </p>
                            </div>
                        </div>
                        <div
                            class="email-quill relative mt-3 overflow-hidden rounded-xl border"
                            :class="form.errors.body ? 'border-red-400 dark:border-red-700' : 'border-slate-200 dark:border-slate-700'"
                        >
                            <div
                                v-if="!editorsReady"
                                class="absolute inset-0 z-10 flex items-center justify-center bg-white/85 px-6 text-center dark:bg-slate-900/85"
                            >
                                <p v-if="editorLoadFailed" class="text-xs text-red-600 dark:text-red-400">
                                    The editor couldn't load. Check your internet connection, then close and reopen this window.
                                </p>
                                <p v-else class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Loader2 class="h-4 w-4 animate-spin" /> Loading editor…
                                </p>
                            </div>
                            <div ref="bodyContainer" style="min-height: 260px" />
                        </div>
                        <p v-if="form.errors.body" class="mt-1.5 text-[11px] font-medium text-red-600 dark:text-red-400">{{ form.errors.body }}</p>
                    </section>

                    <!-- d. Signature -->
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300"
                            >
                                <PenLine class="h-3.5 w-3.5" />
                            </span>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100">
                                Signature <span class="font-normal text-muted-foreground">(optional)</span>
                            </p>
                        </div>
                        <div
                            class="email-quill email-quill-signature relative mt-3 overflow-hidden rounded-xl border"
                            :class="form.errors.signature ? 'border-red-400 dark:border-red-700' : 'border-slate-200 dark:border-slate-700'"
                        >
                            <div v-if="!editorsReady" class="absolute inset-0 z-10 bg-white/85 dark:bg-slate-900/85" />
                            <div ref="signatureContainer" style="min-height: 120px" />
                        </div>
                        <p v-if="form.errors.signature" class="mt-1.5 text-[11px] font-medium text-red-600 dark:text-red-400">
                            {{ form.errors.signature }}
                        </p>
                    </section>

                    <!-- f. Sent history -->
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            :aria-expanded="showHistory"
                            @click="showHistory = !showHistory"
                        >
                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                <History class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800 dark:text-slate-100">Sent history</p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{
                                        history.length
                                            ? `${history.length} notification${history.length === 1 ? '' : 's'} sent to this batch`
                                            : 'Nothing has been sent to this batch yet'
                                    }}
                                </p>
                            </div>
                            <span
                                v-if="history.length"
                                class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ history.length }}
                            </span>
                            <ChevronDown
                                class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                                :class="[showHistory ? 'rotate-180' : '', history.length ? '' : 'ml-auto']"
                            />
                        </button>
                        <div v-if="showHistory" class="border-t border-slate-200 dark:border-slate-800">
                            <p v-if="!history.length" class="px-4 py-6 text-center text-[11px] text-muted-foreground">
                                Notifications you send will appear here with the date, sender and number of recipients.
                            </p>
                            <ul v-else class="max-h-64 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                                <li v-for="log in history" :key="log.id" class="flex items-start gap-3 px-4 py-2.5">
                                    <span
                                        class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300"
                                    >
                                        <MailCheck class="h-3.5 w-3.5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-semibold text-slate-800 dark:text-slate-100" :title="log.subject">
                                            {{ log.subject }}
                                        </p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-muted-foreground">
                                            <span class="inline-flex items-center gap-1"
                                                ><Clock class="h-3 w-3" /> {{ formatDateTime(log.created_at) }}</span
                                            >
                                            <span>
                                                by
                                                <span class="font-medium text-slate-700 dark:text-slate-200">{{
                                                    log.sent_by_name ?? log.sent_by ?? 'system'
                                                }}</span>
                                            </span>
                                        </p>
                                    </div>
                                    <span
                                        class="shrink-0 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold tabular-nums text-blue-700 dark:bg-blue-950/60 dark:text-blue-300"
                                    >
                                        {{ log.recipients_count }} recipient{{ log.recipients_count === 1 ? '' : 's' }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>

                <!-- ════════ PREVIEW ════════ -->
                <div v-show="activeTab === 'preview'" class="flex flex-col gap-3" role="tabpanel">
                    <div
                        class="grid grid-cols-[64px_1fr] gap-y-1.5 rounded-2xl border border-slate-200 bg-white p-4 text-xs shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <span class="text-muted-foreground">From</span>
                        <span class="font-medium text-slate-800 dark:text-slate-100">tdi.noreply@tesda.gov.ph</span>
                        <span class="text-muted-foreground">To</span>
                        <span class="font-medium text-slate-800 dark:text-slate-100">
                            {{ recipientCountLabel }} <span class="font-normal text-muted-foreground">(BCC)</span>
                        </span>
                        <span class="text-muted-foreground">Subject</span>
                        <span class="break-words font-semibold text-slate-900 dark:text-white">
                            {{ form.subject.trim() || '(no subject)' }}
                        </span>
                    </div>

                    <p class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                        <Info class="h-3.5 w-3.5 shrink-0" /> Emails are designed for a light background, which is how most inboxes show them.
                    </p>

                    <!-- Mirrors resources/views/emails/participant-notification.blade.php -->
                    <div class="rounded-2xl bg-[#F5F7FB] p-3 ring-1 ring-slate-200 dark:ring-slate-800 sm:p-6">
                        <div
                            class="mx-auto max-w-[640px] overflow-hidden rounded-2xl border border-[#E7EBF3] bg-white text-left"
                            style="font-family: Arial, Helvetica, sans-serif"
                        >
                            <div class="h-[3px]" style="background-image: linear-gradient(90deg, #c8a45d 0%, #e8d4a2 50%, #c8a45d 100%)" />
                            <div class="px-6 py-8 sm:px-8" style="background-image: linear-gradient(135deg, #0b1f44 0%, #123b82 60%, #1d4ed8 100%)">
                                <div class="flex items-center gap-4">
                                    <span class="flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-full bg-white/10 text-xl"
                                        >📣</span
                                    >
                                    <div>
                                        <p class="text-[11px] font-bold uppercase tracking-[2px] text-[#E8D4A2]">TESDA Development Institute</p>
                                        <p class="mt-1.5 text-[22px] font-extrabold leading-tight text-white sm:text-[25px]">
                                            Participant Notification
                                        </p>
                                    </div>
                                </div>
                                <div class="my-4 h-0.5 w-[42px] bg-[#C8A45D]" />
                                <p class="text-[13.5px] leading-normal text-[#C9D6EF]">
                                    An update for participants of <strong class="text-white">{{ program?.title }}</strong> · {{ batch?.batch }}
                                </p>
                            </div>

                            <div
                                v-if="previewBody"
                                class="email-preview-body px-6 pb-1.5 pt-8 text-[14.5px] leading-[1.55] text-[#1E293B] sm:px-8"
                                v-html="previewBody"
                            />
                            <p v-else class="px-6 pb-1.5 pt-8 text-[14.5px] italic text-[#94A3B8] sm:px-8">Your message will appear here.</p>

                            <div class="px-6 pt-5 sm:px-8">
                                <div class="rounded-xl border border-[#E7EBF3] bg-white px-6 py-5" style="border-left: 3px solid #1d4ed8">
                                    <p class="text-[11.5px] font-extrabold uppercase tracking-[1.2px] text-[#0B1F44]">Program Details</p>
                                    <div class="mb-3 mt-3 h-0.5 w-[30px] bg-[#C8A45D]" />
                                    <dl class="grid grid-cols-[110px_1fr] text-[13.5px] sm:grid-cols-[130px_1fr]">
                                        <dt class="py-1.5 text-[11.5px] font-semibold text-[#64748B]">Training Program</dt>
                                        <dd class="py-1.5 font-bold text-[#0B1F44]">{{ program?.title }}</dd>
                                        <dt class="border-t border-[#F1F5F9] py-1.5 text-[11.5px] font-semibold text-[#64748B]">Batch</dt>
                                        <dd class="border-t border-[#F1F5F9] py-1.5 font-bold text-[#0B1F44]">{{ batch?.batch }}</dd>
                                        <template v-if="scheduleLabel">
                                            <dt class="border-t border-[#F1F5F9] py-1.5 text-[11.5px] font-semibold text-[#64748B]">Dates</dt>
                                            <dd class="border-t border-[#F1F5F9] py-1.5 font-bold text-[#0B1F44]">{{ scheduleLabel }}</dd>
                                        </template>
                                        <template v-if="batch?.venue">
                                            <dt class="border-t border-[#F1F5F9] py-1.5 text-[11.5px] font-semibold text-[#64748B]">Venue</dt>
                                            <dd class="border-t border-[#F1F5F9] py-1.5 font-bold text-[#0B1F44]">{{ batch.venue }}</dd>
                                        </template>
                                    </dl>
                                </div>
                            </div>

                            <div class="px-6 pb-8 pt-7 sm:px-8">
                                <template v-if="previewSignature">
                                    <div class="mb-4 h-px bg-[#E7EBF3]" />
                                    <div class="email-preview-signature text-[12.5px] leading-normal text-[#64748B]" v-html="previewSignature" />
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
                </div>
            </div>

            <!-- ── Footer ── -->
            <div
                class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-white px-5 py-3 dark:border-slate-800 dark:bg-slate-900"
            >
                <p class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                    <Users class="h-3.5 w-3.5" />
                    <span class="font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ recipientCountLabel }}</span>
                    <span>· BCC</span>
                </p>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" class="dark:border-slate-700" :disabled="form.processing" @click="handleOpenChange(false)">
                        Cancel
                    </Button>
                    <Button
                        size="sm"
                        class="bg-blue-600 text-white shadow-sm hover:bg-blue-700 dark:bg-blue-600 dark:text-white dark:hover:bg-blue-500"
                        :disabled="form.processing || !editorsReady"
                        @click="send"
                    >
                        <Loader2 v-if="form.processing" class="mr-1 h-3.5 w-3.5 animate-spin" />
                        <Send v-else class="mr-1 h-3.5 w-3.5" />
                        {{ form.processing ? 'Sending…' : 'Send notification' }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
