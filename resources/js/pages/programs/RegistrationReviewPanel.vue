<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import SearchEmptyState from '@/components/SearchEmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useConfirm } from '@/composables/useConfirm';
import { suggestSearch } from '@/lib/searchSuggestion';
import { router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    CheckCheck,
    ChevronDown,
    ChevronUp,
    KeyRound,
    LoaderCircle,
    Mail,
    Pencil,
    Phone,
    Search,
    UserCheck,
    UserX,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

export interface RegistrationRow {
    id: number;
    source: 'listed' | 'manual';
    status: 'pending' | 'approved' | 'rejected';
    empcode: string;
    name: string;
    lastname: string;
    firstname: string;
    mi: string | null;
    position: string | null;
    sg: string | null;
    plantilla_status: string | null;
    sex: string | null;
    region: string | null;
    office: string | null;
    office_division: string | null;
    location: string | null;
    section: string | null;
    unit: string | null;
    email: string;
    contact_number: string;
    custom_answers: { question_id: number; label: string; type: string; value: string | string[] | null }[] | null;
    remarks: string | null;
    submitted_at: string | null;
    reviewed_by_name: string | null;
    reviewed_at: string | null;
    in_employee_list: boolean;
    account: 'existing' | 'will_create' | 'email_taken' | 'created' | null;
    can_resend_login: boolean;
    holding_status: 'waiting' | 'merged' | null;
}

const props = defineProps<{
    formId: number;
    registrations: RegistrationRow[];
    canManage: boolean;
    fieldOptions: { region: string[]; office_division: Record<string, string[]>; sex: string[]; plantilla_status: string[] };
}>();

const { confirmDialog } = useConfirm();

const STATUS_FILTERS = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'all', label: 'All' },
] as const;

const statusFilter = ref<'pending' | 'approved' | 'rejected' | 'all'>('pending');
const search = ref('');

const counts = computed(() => ({
    pending: props.registrations.filter((r) => r.status === 'pending').length,
    approved: props.registrations.filter((r) => r.status === 'approved').length,
    rejected: props.registrations.filter((r) => r.status === 'rejected').length,
    all: props.registrations.length,
}));

const visible = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.registrations.filter((r) => {
        if (statusFilter.value !== 'all' && r.status !== statusFilter.value) return false;
        if (!q) return true;
        return [r.name, r.empcode, r.email, r.office ?? ''].some((v) => v.toLowerCase().includes(q));
    });
});

const statusClass: Record<RegistrationRow['status'], string> = {
    pending: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
    approved: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
    rejected: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300',
};

function accountNote(row: RegistrationRow): { text: string; warn: boolean } | null {
    switch (row.account) {
        case 'will_create':
            return { text: 'Approving creates a guest account and emails a temporary password.', warn: false };
        case 'existing':
            return { text: row.status === 'pending' ? 'Already has an account. It will be reused.' : 'Used their existing account.', warn: false };
        case 'email_taken':
            return { text: "This email belongs to another person's account. Edit the email before approving.", warn: true };
        case 'created':
            return {
                text: row.can_resend_login ? 'Account created. Waiting for them to set their own password.' : 'Account created and activated.',
                warn: false,
            };
        default:
            return null;
    }
}

const expanded = ref<Set<number>>(new Set());
function toggleExpanded(id: number) {
    const next = new Set(expanded.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expanded.value = next;
}

function answerText(value: string | string[] | null): string {
    if (Array.isArray(value)) return value.length ? value.join(', ') : '—';
    return value ?? '—';
}

/* ── Actions ──────────────────────────────────────────────────────────────── */
const busyId = ref<number | null>(null);

async function approve(row: RegistrationRow) {
    const what =
        row.account === 'will_create'
            ? `This adds ${row.name} to the batch, creates their guest account and emails a temporary password to ${row.email}.`
            : `This adds ${row.name} to the batch and emails them at ${row.email}.`;
    const extra = row.source === 'manual' && !row.in_employee_list ? ' Their details will also be added to the employees data.' : '';

    if (!(await confirmDialog(`${what}${extra} Approve this registration?`, { confirmText: 'Approve' }))) return;

    busyId.value = row.id;
    router.post(
        route('program-registrations.approve', row.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busyId.value = null;
            },
        },
    );
}

const approvingAll = ref(false);
async function approveAllPending() {
    if (!counts.value.pending) return;
    const confirmed = await confirmDialog(
        `Approve all ${counts.value.pending} pending registration(s)? Each person is added to the batch and emailed, and accounts are created where needed. Registrations that can't be approved (for example, an email that belongs to someone else) are skipped and listed.`,
        { confirmText: 'Approve All' },
    );
    if (!confirmed) return;

    approvingAll.value = true;
    router.post(
        route('registration-forms.approve', props.formId),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                approvingAll.value = false;
            },
        },
    );
}

async function resendLogin(row: RegistrationRow) {
    const confirmed = await confirmDialog(`Send ${row.name} a new temporary password? The one they got before will stop working.`, {
        confirmText: 'Send New Password',
    });
    if (!confirmed) return;

    busyId.value = row.id;
    router.post(
        route('program-registrations.resend-login', row.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busyId.value = null;
            },
        },
    );
}

// Reject
const rejecting = ref<RegistrationRow | null>(null);
const rejectReason = ref('');
const rejectError = ref('');
const savingReject = ref(false);

function openReject(row: RegistrationRow) {
    rejecting.value = row;
    rejectReason.value = '';
    rejectError.value = '';
}

function onRejectDialogToggle(open: boolean) {
    if (!open) rejecting.value = null;
}

function submitReject() {
    if (!rejecting.value) return;
    savingReject.value = true;
    router.post(
        route('program-registrations.reject', rejecting.value.id),
        { remarks: rejectReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                rejecting.value = null;
            },
            onError: (errors) => {
                rejectError.value = errors.remarks ?? '';
            },
            onFinish: () => {
                savingReject.value = false;
            },
        },
    );
}

// Edit
type EditableField =
    | 'empcode'
    | 'lastname'
    | 'firstname'
    | 'mi'
    | 'position'
    | 'sg'
    | 'plantilla_status'
    | 'sex'
    | 'region'
    | 'office_division'
    | 'email'
    | 'contact_number';

// Office, location, section and unit follow from the office/division, so they aren't edited here.
const EMPLOYEE_FIELDS: { key: EditableField; label: string; required: boolean; list?: keyof typeof props.fieldOptions }[] = [
    { key: 'empcode', label: 'Employee Code', required: true },
    { key: 'lastname', label: 'Last Name', required: true },
    { key: 'firstname', label: 'First Name', required: true },
    { key: 'mi', label: 'Middle Initial', required: false },
    { key: 'position', label: 'Position', required: true },
    { key: 'sg', label: 'Salary Grade', required: false },
    { key: 'plantilla_status', label: 'Plantilla Status', required: true, list: 'plantilla_status' },
    { key: 'sex', label: 'Sex', required: true, list: 'sex' },
    { key: 'region', label: 'Region', required: true, list: 'region' },
    { key: 'office_division', label: 'Office / Division', required: true, list: 'office_division' },
];

const editing = ref<RegistrationRow | null>(null);
const editForm = ref<Record<EditableField, string>>({} as Record<EditableField, string>);

// Suggestions for the edit boxes; office/divisions follow the region being edited.
const suggestions = computed<Record<string, string[]>>(() => ({
    region: props.fieldOptions.region,
    sex: props.fieldOptions.sex,
    plantilla_status: props.fieldOptions.plantilla_status,
    office_division: props.fieldOptions.office_division[editForm.value.region ?? ''] ?? [],
}));
const editErrors = ref<Record<string, string>>({});
const savingEdit = ref(false);

function openEdit(row: RegistrationRow) {
    editing.value = row;
    editErrors.value = {};
    const values = {} as Record<EditableField, string>;
    for (const field of EMPLOYEE_FIELDS) values[field.key] = (row[field.key] as string | null) ?? '';
    values.email = row.email;
    values.contact_number = row.contact_number;
    editForm.value = values;
}

function onEditDialogToggle(open: boolean) {
    if (!open) editing.value = null;
}

function submitEdit() {
    if (!editing.value) return;
    savingEdit.value = true;

    const payload: Record<string, string | null> = {
        email: editForm.value.email,
        contact_number: editForm.value.contact_number,
    };
    if (editing.value.source === 'manual') {
        for (const field of EMPLOYEE_FIELDS) payload[field.key] = editForm.value[field.key].trim() || null;
    }

    router.put(route('program-registrations.update', editing.value.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
        onError: (errors) => {
            editErrors.value = errors;
        },
        onFinish: () => {
            savingEdit.value = false;
        },
    });
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Toolbar -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="filter in STATUS_FILTERS"
                    :key="filter.value"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-semibold transition-colors"
                    :class="
                        statusFilter === filter.value
                            ? 'border-blue-600 bg-blue-600 text-white'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    @click="statusFilter = filter.value"
                >
                    {{ filter.label }} <span class="opacity-75">({{ counts[filter.value] }})</span>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative w-full sm:w-56">
                    <Search class="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" class="h-8 pl-7 text-xs" placeholder="Search name, code, email…" />
                </div>
                <Button
                    v-if="canManage && counts.pending"
                    size="sm"
                    class="h-8 shrink-0 bg-blue-600 text-xs hover:bg-blue-700 dark:text-white"
                    :disabled="approvingAll"
                    @click="approveAllPending"
                >
                    <LoaderCircle v-if="approvingAll" class="mr-1 h-3 w-3 animate-spin" />
                    <CheckCheck v-else class="mr-1 h-3 w-3" /> Approve All Pending
                </Button>
            </div>
        </div>

        <!-- Empty states -->
        <EmptyState
            v-if="!registrations.length"
            :icon="Users"
            compact
            class="rounded-2xl border border-dashed"
            title="No registrations yet"
            description="When people submit the public form, they show up here for you to approve or reject."
        />
        <SearchEmptyState
            v-else-if="!visible.length && search.trim()"
            compact
            class="rounded-2xl border border-dashed"
            :query="search"
            :suggestion="
                suggestSearch(
                    search,
                    registrations.flatMap((r) => [r.name, r.empcode]),
                )
            "
            @search="search = $event"
            @clear-filters="
                search = '';
                statusFilter = 'all';
            "
        />
        <EmptyState v-else-if="!visible.length" compact class="rounded-2xl border border-dashed" :title="`No ${statusFilter} registrations.`" />

        <!-- List -->
        <div v-else class="divide-y overflow-hidden rounded-2xl border bg-background">
            <div v-for="row in visible" :key="row.id" class="flex flex-col gap-2 px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <p class="text-sm font-bold">{{ row.name }}</p>
                            <Badge variant="outline" class="text-[10px] capitalize" :class="statusClass[row.status]">{{ row.status }}</Badge>
                            <Badge
                                v-if="row.source === 'manual' && !row.in_employee_list && row.status !== 'approved'"
                                variant="outline"
                                class="border-blue-200 bg-blue-50 text-[10px] text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300"
                            >
                                Not in employees list
                            </Badge>
                            <Badge
                                v-if="row.holding_status === 'waiting'"
                                variant="outline"
                                class="border-blue-200 text-[10px] text-blue-700 dark:border-blue-900 dark:text-blue-300"
                            >
                                Waiting for HR list
                            </Badge>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-muted-foreground">
                            {{ row.empcode }}<template v-if="row.position"> · {{ row.position }}</template
                            ><template v-if="row.office_division"> · {{ row.office_division }}</template>
                        </p>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                            <span class="flex items-center gap-1"><Mail class="h-3 w-3" /> {{ row.email }}</span>
                            <span class="flex items-center gap-1"><Phone class="h-3 w-3" /> {{ row.contact_number }}</span>
                        </p>
                    </div>

                    <div v-if="canManage" class="flex shrink-0 flex-wrap items-center gap-1.5">
                        <template v-if="row.status === 'pending'">
                            <Button
                                size="sm"
                                class="h-7 bg-emerald-600 text-xs hover:bg-emerald-700 dark:text-white"
                                :disabled="busyId === row.id || row.account === 'email_taken'"
                                @click="approve(row)"
                            >
                                <LoaderCircle v-if="busyId === row.id" class="mr-1 h-3 w-3 animate-spin" />
                                <UserCheck v-else class="mr-1 h-3 w-3" /> Approve
                            </Button>
                            <Button size="sm" variant="outline" class="h-7 text-xs" @click="openEdit(row)">
                                <Pencil class="mr-1 h-3 w-3" /> Edit
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-7 border-rose-200 text-xs text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-400"
                                @click="openReject(row)"
                            >
                                <UserX class="mr-1 h-3 w-3" /> Reject
                            </Button>
                        </template>
                        <Button
                            v-else-if="row.can_resend_login"
                            size="sm"
                            variant="outline"
                            class="h-7 text-xs"
                            :disabled="busyId === row.id"
                            @click="resendLogin(row)"
                        >
                            <LoaderCircle v-if="busyId === row.id" class="mr-1 h-3 w-3 animate-spin" />
                            <KeyRound v-else class="mr-1 h-3 w-3" /> Resend Login Details
                        </Button>
                    </div>
                </div>

                <p
                    v-if="accountNote(row)"
                    class="flex items-start gap-1.5 text-[11px]"
                    :class="accountNote(row)!.warn ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                >
                    <AlertTriangle v-if="accountNote(row)!.warn" class="mt-px h-3 w-3 shrink-0" />
                    <Check v-else class="mt-px h-3 w-3 shrink-0" />
                    {{ accountNote(row)!.text }}
                </p>

                <p v-if="row.status === 'rejected' && row.remarks" class="rounded-lg bg-muted/50 px-3 py-2 text-xs">
                    <span class="font-semibold">Reason:</span> {{ row.remarks }}
                </p>

                <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground">
                    <span>
                        Registered {{ row.submitted_at }}
                        <template v-if="row.reviewed_at">
                            · {{ row.status === 'approved' ? 'Approved' : 'Rejected' }} by {{ row.reviewed_by_name }} on
                            {{ row.reviewed_at }}</template
                        >
                    </span>
                    <button type="button" class="flex items-center gap-1 font-semibold hover:text-foreground" @click="toggleExpanded(row.id)">
                        {{ expanded.has(row.id) ? 'Hide details' : 'Show details' }}
                        <ChevronUp v-if="expanded.has(row.id)" class="h-3 w-3" />
                        <ChevronDown v-else class="h-3 w-3" />
                    </button>
                </div>

                <dl v-if="expanded.has(row.id)" class="grid gap-x-6 gap-y-1 rounded-xl bg-muted/40 px-4 py-3 text-xs sm:grid-cols-2">
                    <template v-for="field in EMPLOYEE_FIELDS" :key="field.key">
                        <div v-if="row[field.key]" class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">{{ field.label }}</dt>
                            <dd class="text-right font-medium">{{ row[field.key] }}</dd>
                        </div>
                    </template>
                    <div v-for="answer in row.custom_answers ?? []" :key="answer.question_id" class="flex justify-between gap-3 sm:col-span-2">
                        <dt class="text-muted-foreground">{{ answer.label }}</dt>
                        <dd class="text-right font-medium">{{ answerText(answer.value) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Reject dialog -->
        <Dialog :open="!!rejecting" @update:open="onRejectDialogToggle">
            <DialogContent class="max-w-md !rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><UserX class="h-4 w-4" /> Reject Registration</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        {{ rejecting?.name }} gets an email with the reason you write here, and their slot opens up for someone else.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-1">
                    <Label class="text-xs">Reason <span class="text-red-500">*</span></Label>
                    <Textarea v-model="rejectReason" rows="3" class="text-xs" placeholder="e.g. This batch is only for Region IV-A personnel." />
                    <p v-if="rejectError" class="text-xs text-red-500">{{ rejectError }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t pt-2">
                    <Button type="button" variant="outline" size="sm" @click="rejecting = null">Cancel</Button>
                    <Button type="button" size="sm" variant="destructive" :disabled="savingReject || !rejectReason.trim()" @click="submitReject">
                        <LoaderCircle v-if="savingReject" class="mr-1 h-3 w-3 animate-spin" /> Reject Registration
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Edit dialog -->
        <Dialog :open="!!editing" @update:open="onEditDialogToggle">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto !rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><Pencil class="h-4 w-4" /> Edit Registration</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        <template v-if="editing?.source === 'manual'">Correct the details they typed in before you approve.</template>
                        <template v-else
                            >Their employee details come from the employee list, so only the email and contact number can be changed.</template
                        >
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <template v-if="editing?.source === 'manual'">
                        <div v-for="field in EMPLOYEE_FIELDS" :key="field.key" class="grid gap-1">
                            <Label :for="`edit-${field.key}`" class="text-xs">
                                {{ field.label }} <span v-if="field.required" class="text-red-500">*</span>
                            </Label>
                            <Input
                                :id="`edit-${field.key}`"
                                v-model="editForm[field.key]"
                                class="h-8 text-xs"
                                :list="field.list ? `edit-list-${field.list}` : undefined"
                            />
                            <p v-if="editErrors[field.key]" class="text-xs text-red-500">{{ editErrors[field.key] }}</p>
                        </div>
                    </template>
                    <div class="grid gap-1">
                        <Label for="edit-email" class="text-xs">Email <span class="text-red-500">*</span></Label>
                        <Input id="edit-email" v-model="editForm.email" type="email" class="h-8 text-xs" />
                        <p v-if="editErrors.email" class="text-xs text-red-500">{{ editErrors.email }}</p>
                    </div>
                    <div class="grid gap-1">
                        <Label for="edit-contact" class="text-xs">Contact Number <span class="text-red-500">*</span></Label>
                        <Input id="edit-contact" v-model="editForm.contact_number" class="h-8 text-xs" />
                        <p v-if="editErrors.contact_number" class="text-xs text-red-500">{{ editErrors.contact_number }}</p>
                    </div>
                </form>

                <datalist v-for="(values, key) in suggestions" :id="`edit-list-${key}`" :key="key">
                    <option v-for="value in values" :key="value" :value="value" />
                </datalist>

                <div class="flex justify-end gap-2 border-t pt-2">
                    <Button type="button" variant="outline" size="sm" @click="editing = null">Cancel</Button>
                    <Button type="button" size="sm" class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="savingEdit" @click="submitEdit">
                        <LoaderCircle v-if="savingEdit" class="mr-1 h-3 w-3 animate-spin" /> Save Changes
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
