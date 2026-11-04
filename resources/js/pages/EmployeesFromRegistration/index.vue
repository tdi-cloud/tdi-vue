<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import SearchEmptyState from '@/components/SearchEmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, CircleCheck, History, LoaderCircle, Pencil, RefreshCw, Search, TriangleAlert, UserPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    | 'contact_number';

interface HoldingRecord extends Record<EditableField, string | null> {
    id: number;
    name: string;
    office: string | null;
    email: string | null;
    status: 'waiting' | 'merged';
    program_title: string | null;
    batch_label: string | null;
    has_account: boolean;
    in_employees_table: boolean | null;
    added_at: string | null;
    last_checked_at: string | null;
    merged_at: string | null;
}

const props = defineProps<{
    records: {
        data: HoldingRecord[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search: string; status: 'waiting' | 'merged' };
    counts: { waiting: number; merged: number };
    fieldOptions: { region: string[]; office_division: Record<string, string[]>; sex: string[]; plantilla_status: string[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Employees from Registration', href: '/employees-from-registration' }];

const { confirmDialog } = useConfirm();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status);

let debounce: ReturnType<typeof setTimeout>;
watch([search, status], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('employees-from-registration.index'),
            {
                search: search.value || undefined,
                status: status.value === 'merged' ? 'merged' : undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 350);
});

/* ── Check now ────────────────────────────────────────────────────────────── */
const checking = ref(false);

async function checkNow() {
    const confirmed = await confirmDialog(
        'Check everyone on this list against the official employee list now? People HR has already added are taken off this list, and rows the monthly re-import removed are put back.',
        { confirmText: 'Check Now' },
    );
    if (!confirmed) return;

    checking.value = true;
    router.post(
        route('employees-from-registration.sync'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                checking.value = false;
            },
        },
    );
}

/* ── Edit ─────────────────────────────────────────────────────────────────── */
const FIELDS: { key: EditableField; label: string; required: boolean; list?: keyof typeof props.fieldOptions }[] = [
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
    { key: 'contact_number', label: 'Contact Number', required: false },
];

const editing = ref<HoldingRecord | null>(null);
const editForm = ref<Record<EditableField, string>>({} as Record<EditableField, string>);

// Suggestions for the edit boxes; office/divisions follow the region being edited.
const suggestions = computed<Record<string, string[]>>(() => ({
    region: props.fieldOptions.region,
    sex: props.fieldOptions.sex,
    plantilla_status: props.fieldOptions.plantilla_status,
    office_division: props.fieldOptions.office_division[editForm.value.region ?? ''] ?? [],
}));
const editErrors = ref<Record<string, string>>({});
const saving = ref(false);

function openEdit(record: HoldingRecord) {
    editing.value = record;
    editErrors.value = {};
    const values = {} as Record<EditableField, string>;
    for (const field of FIELDS) values[field.key] = record[field.key] ?? '';
    editForm.value = values;
}

function onEditDialogToggle(open: boolean) {
    if (!open) editing.value = null;
}

async function submitEdit() {
    if (!editing.value) return;

    const codeChanged = editForm.value.empcode.trim() !== (editing.value.empcode ?? '');
    if (codeChanged) {
        const confirmed = await confirmDialog(
            `Change the employee code from ${editing.value.empcode} to ${editForm.value.empcode.trim()}? It also changes on their account, participations, certificates and registrations.`,
            { confirmText: 'Change Code' },
        );
        if (!confirmed) return;
    }

    saving.value = true;
    const payload = Object.fromEntries(FIELDS.map((field) => [field.key, editForm.value[field.key].trim() || null]));

    router.put(route('employees-from-registration.update', editing.value.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
        onError: (errors) => {
            editErrors.value = errors;
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <Head title="Employees from Registration" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4">
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
                        <UserPlus class="h-3.5 w-3.5" /> Employees from Registration
                    </span>
                    <h1 class="text-xl font-bold leading-tight md:text-2xl">Waiting for HR's employee list</h1>
                    <p class="max-w-2xl text-sm text-white/85">
                        People added to the employees data when their registration was approved. HR replaces the employee list every month, so the
                        system keeps them here and puts their row back after each re-import. Once HR's list includes them, they move to the history.
                        This is checked automatically every day.
                    </p>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-1.5">
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition-colors"
                        :class="
                            status === 'waiting'
                                ? 'border-blue-600 bg-blue-600 text-white'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="status = 'waiting'"
                    >
                        Waiting <span class="opacity-75">({{ counts.waiting }})</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition-colors"
                        :class="
                            status === 'merged'
                                ? 'border-blue-600 bg-blue-600 text-white'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="status = 'merged'"
                    >
                        <History class="h-3 w-3" /> Merged history <span class="opacity-75">({{ counts.merged }})</span>
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative w-full sm:w-60">
                        <Search class="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" class="h-8 pl-7 text-xs" placeholder="Search name, code, email…" />
                    </div>
                    <Button
                        size="sm"
                        class="h-8 shrink-0 bg-blue-600 text-xs hover:bg-blue-700 dark:text-white"
                        :disabled="checking"
                        @click="checkNow"
                    >
                        <LoaderCircle v-if="checking" class="mr-1 h-3 w-3 animate-spin" />
                        <RefreshCw v-else class="mr-1 h-3 w-3" /> Check Now
                    </Button>
                </div>
            </div>

            <!-- Empty states -->
            <SearchEmptyState
                v-if="!records.data.length && search.trim()"
                class="rounded-2xl border border-dashed"
                :query="search"
                description="No one on this list matches that name, code or email."
                @clear-filters="search = ''"
                @search="search = $event"
            />
            <EmptyState
                v-else-if="!records.data.length"
                :icon="UserPlus"
                class="rounded-2xl border border-dashed"
                :title="status === 'merged' ? 'No merged employees yet' : 'No one is waiting'"
                :description="
                    status === 'merged'
                        ? 'People appear here once HR\'s employee list includes them.'
                        : 'Everyone added from registrations is already in HR\'s employee list.'
                "
            />

            <!-- List -->
            <div v-else class="divide-y overflow-hidden rounded-2xl border bg-background">
                <div
                    v-for="record in records.data"
                    :key="record.id"
                    class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-5"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <p class="text-sm font-bold">{{ record.name }}</p>
                            <Badge variant="outline" class="text-[10px]">{{ record.empcode }}</Badge>
                            <Badge
                                v-if="record.status === 'merged'"
                                variant="outline"
                                class="border-emerald-200 bg-emerald-50 text-[10px] text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300"
                            >
                                In HR's list
                            </Badge>
                            <Badge
                                v-else-if="record.in_employees_table === false"
                                variant="outline"
                                class="border-amber-200 bg-amber-50 text-[10px] text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300"
                            >
                                Missing after re-import
                            </Badge>
                            <Badge v-if="!record.has_account" variant="outline" class="text-[10px] text-muted-foreground">No account</Badge>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-muted-foreground">
                            {{ record.position }}<template v-if="record.office_division"> · {{ record.office_division }}</template
                            ><template v-if="record.region"> · {{ record.region }}</template>
                        </p>
                        <p v-if="record.email" class="truncate text-xs text-muted-foreground">{{ record.email }}</p>
                        <p v-if="record.program_title" class="mt-0.5 truncate text-[11px] text-muted-foreground">
                            Registered for {{ record.program_title }}<template v-if="record.batch_label"> ({{ record.batch_label }})</template>
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col items-start gap-1.5 text-[11px] text-muted-foreground sm:items-end">
                        <span>Added {{ record.added_at }}</span>
                        <span v-if="record.status === 'merged'" class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                            <CircleCheck class="h-3 w-3" /> Merged {{ record.merged_at }}
                        </span>
                        <span v-else-if="record.last_checked_at">Last checked {{ record.last_checked_at }}</span>
                        <span v-if="record.in_employees_table === false" class="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                            <TriangleAlert class="h-3 w-3" /> Their row is put back on the next check.
                        </span>
                        <Button v-if="record.status === 'waiting'" size="sm" variant="outline" class="h-7 text-xs" @click="openEdit(record)">
                            <Pencil class="mr-1 h-3 w-3" /> Edit
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="records.last_page > 1" class="flex items-center justify-between text-sm">
                <p class="text-xs text-muted-foreground">Page {{ records.current_page }} of {{ records.last_page }}</p>
                <div class="flex items-center gap-1">
                    <template v-for="link in records.links" :key="link.label">
                        <a
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'border-blue-600 bg-blue-600 text-white' : 'text-muted-foreground hover:bg-muted'"
                        >
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </a>
                        <span v-else class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-xs text-muted-foreground opacity-40">
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Edit dialog -->
        <Dialog :open="!!editing" @update:open="onEditDialogToggle">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto !rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><Pencil class="h-4 w-4" /> Edit {{ editing?.name }}</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Changes also apply to their row in the employees data. If HR's list uses a different employee code, enter it here and they'll
                        be matched right away.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <div v-for="field in FIELDS" :key="field.key" class="grid gap-1">
                        <Label :for="`holding-${field.key}`" class="text-xs">
                            {{ field.label }} <span v-if="field.required" class="text-red-500">*</span>
                        </Label>
                        <Input
                            :id="`holding-${field.key}`"
                            v-model="editForm[field.key]"
                            class="h-8 text-xs"
                            :list="field.list ? `holding-list-${field.list}` : undefined"
                        />
                        <p v-if="editErrors[field.key]" class="text-xs text-red-500">{{ editErrors[field.key] }}</p>
                    </div>
                </form>

                <datalist v-for="(values, key) in suggestions" :id="`holding-list-${key}`" :key="key">
                    <option v-for="value in values" :key="value" :value="value" />
                </datalist>

                <div class="flex justify-end gap-2 border-t pt-2">
                    <Button type="button" variant="outline" size="sm" @click="editing = null">Cancel</Button>
                    <Button type="button" size="sm" class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="saving" @click="submitEdit">
                        <LoaderCircle v-if="saving" class="mr-1 h-3 w-3 animate-spin" /> Save Changes
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
