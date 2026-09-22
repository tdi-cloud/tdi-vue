<script setup lang="ts">
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, CheckCircle2, CloudUpload, Eye, FileText, LoaderCircle, Search, Trash2, Upload, X, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface TrackedItem {
    participant_id: number;
    empcode: string;
    employee_name: string;
    office_division: string;
    region: string;
    program_id: number;
    program_code: string;
    program_title: string;
    batch_id: number;
    batch_label: string;
    requirement_id: number;
    requirement_title: string;
    requirement_name: string;
    due_date: string;
    is_overdue: boolean;
}

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

interface Assignment {
    item: TrackedItem;
    status: 'idle' | 'uploading' | 'done' | 'error';
    error: string | null;
}

interface Row {
    key: string;
    file: File;
    previewUrl: string;
    query: string;
    searching: boolean;
    results: TrackedItem[];
    showResults: boolean;
    // Isang file ay pwedeng i-file sa DALAWA (o higit pa) na empleyado — hal.
    // may dokumentong dalawa ang "Prepared by", kaya kailangang mag-file bilang
    // submission ng PAREHONG tao.
    assignments: Assignment[];
}

const rows = ref<Row[]>([]);
const uploading = ref(false);
const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

function addFiles(fileList: FileList | File[]) {
    for (const file of Array.from(fileList)) {
        if (file.type !== 'application/pdf') continue;
        rows.value.push({
            key: `${file.name}-${file.size}-${file.lastModified}-${rows.value.length}`,
            file,
            previewUrl: URL.createObjectURL(file),
            query: '',
            searching: false,
            results: [],
            showResults: false,
            assignments: [],
        });
    }
}

function onFileInputChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files) addFiles(input.files);
    input.value = '';
}

function onDrop(e: DragEvent) {
    isDragging.value = false;
    if (e.dataTransfer?.files) addFiles(e.dataTransfer.files);
}

function removeRow(key: string) {
    const row = rows.value.find((r) => r.key === key);
    if (row) URL.revokeObjectURL(row.previewUrl);
    rows.value = rows.value.filter((r) => r.key !== key);
}

/* ---------- Per-row search: name/empcode/program → matching missing requirements ---------- */
const debounceTimers = new Map<string, ReturnType<typeof setTimeout>>();

function onQueryInput(row: Row) {
    row.showResults = true;
    clearTimeout(debounceTimers.get(row.key));

    if (!row.query.trim()) {
        row.results = [];
        return;
    }

    debounceTimers.set(
        row.key,
        setTimeout(async () => {
            row.searching = true;
            try {
                const res = await axios.get(route('requirements-tracker.search'), { params: { q: row.query.trim() } });
                row.results = res.data;
            } finally {
                row.searching = false;
            }
        }, 300),
    );
}

function pickResult(row: Row, item: TrackedItem) {
    const alreadyAssigned = row.assignments.some(
        (a) => a.item.participant_id === item.participant_id && a.item.requirement_id === item.requirement_id,
    );
    if (!alreadyAssigned) {
        row.assignments.push({ item, status: 'idle', error: null });
    }
    row.query = '';
    row.results = [];
    row.showResults = false;
}

function removeAssignment(row: Row, index: number) {
    row.assignments.splice(index, 1);
}

/* ---------- File preview (local, not-yet-uploaded PDF) ---------- */
const previewOpen = ref(false);
const previewRow = ref<Row | null>(null);

function openPreview(row: Row) {
    previewRow.value = row;
    previewOpen.value = true;
}

/* ---------- Upload ---------- */
// Lahat ng (row, assignment) pares — pinapa-flatten para sa duplicate check,
// counts, at ang aktwal na upload loop.
const allAssignments = computed(() => rows.value.flatMap((row) => row.assignments.map((a) => ({ row, a }))));

const assignedFileCount = computed(() => rows.value.filter((r) => r.assignments.length > 0).length);
const pendingCount = computed(() => allAssignments.value.filter(({ a }) => a.status === 'idle' || a.status === 'error').length);
const doneCount = computed(() => allAssignments.value.filter(({ a }) => a.status === 'done').length);
const errorCount = computed(() => allAssignments.value.filter(({ a }) => a.status === 'error').length);
const finished = computed(
    () => allAssignments.value.length > 0 && allAssignments.value.every(({ a }) => a.status === 'done' || a.status === 'error'),
);

// Kapag parehong (participant, requirement) ang assigned nang dalawang beses
// (hal. dalawang magkaibang file para sa parehong tao/requirement) — babala
// lang, dahil papalitan lang ng huling na-upload ang una.
const duplicateAssignmentKeys = computed(() => {
    const seen = new Map<string, number>();
    for (const { a } of allAssignments.value) {
        const k = `${a.item.participant_id}-${a.item.requirement_id}`;
        seen.set(k, (seen.get(k) ?? 0) + 1);
    }
    return new Set([...seen.entries()].filter(([, count]) => count > 1).map(([k]) => k));
});

function isDuplicate(item: TrackedItem): boolean {
    return duplicateAssignmentKeys.value.has(`${item.participant_id}-${item.requirement_id}`);
}

const canUpload = computed(() => pendingCount.value > 0 && !uploading.value);

async function uploadAll() {
    uploading.value = true;

    for (const { row, a } of allAssignments.value) {
        if (a.status === 'done') continue;
        a.status = 'uploading';
        a.error = null;

        const formData = new FormData();
        formData.append('participant_id', String(a.item.participant_id));
        formData.append('batch_id', String(a.item.batch_id));
        formData.append('requirement_id', String(a.item.requirement_id));
        formData.append('program_code', a.item.program_code);
        formData.append('status', 'Approved');
        formData.append('file', row.file);

        try {
            await axios.post(route('submissions.store'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            a.status = 'done';
        } catch (err: any) {
            a.status = 'error';
            a.error = err.response?.data?.errors?.file?.[0] ?? err.response?.data?.message ?? 'Upload failed.';
        }
    }

    uploading.value = false;
}

function reset() {
    rows.value.forEach((r) => URL.revokeObjectURL(r.previewUrl));
    rows.value = [];
}

function close() {
    const hadUploads = doneCount.value > 0;
    reset();
    emit('update:open', false);
    if (hadUploads) router.reload({ preserveScroll: true });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && close()">
        <DialogContent class="flex h-[85vh] w-full max-w-4xl flex-col gap-0 overflow-hidden !rounded-2xl p-0">
            <DialogHeader class="shrink-0 border-b px-5 py-4 text-left">
                <DialogTitle class="flex items-center gap-2"><CloudUpload class="h-4 w-4 text-blue-600" /> Bulk Import Submissions</DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    Upload a pile of PDF files with no naming pattern — preview each one, search the employee's name to find which requirement it
                    belongs to, then upload all at once. A file with more than one preparer can be assigned to more than one employee.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                <!-- Drop zone -->
                <div
                    class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-6 py-8 text-center transition-colors"
                    :class="isDragging ? 'border-blue-400 bg-blue-50 dark:bg-blue-950/20' : 'border-muted-foreground/25'"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="onDrop"
                >
                    <Upload class="h-8 w-8 text-muted-foreground" />
                    <p class="text-sm font-semibold">Drag & drop PDF files here</p>
                    <p class="text-xs text-muted-foreground">or</p>
                    <Button size="sm" variant="outline" @click="fileInput?.click()">Browse Files</Button>
                    <input ref="fileInput" type="file" accept="application/pdf" multiple class="hidden" @change="onFileInputChange" />
                </div>

                <!-- File rows -->
                <div v-if="rows.length" class="flex flex-col gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ rows.length }} file(s) — {{ assignedFileCount }} with at least one assignment
                    </p>

                    <div v-for="row in rows" :key="row.key" class="flex flex-col gap-2 rounded-xl border p-3">
                        <div class="flex items-center gap-2">
                            <FileText class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <button type="button" class="min-w-0 truncate text-left text-xs font-semibold hover:underline" @click="openPreview(row)">
                                {{ row.file.name }}
                            </button>
                            <button
                                type="button"
                                class="shrink-0 text-muted-foreground transition-colors hover:text-foreground"
                                title="Preview"
                                @click="openPreview(row)"
                            >
                                <Eye class="h-3.5 w-3.5" />
                            </button>

                            <button
                                type="button"
                                class="ml-auto shrink-0 text-muted-foreground transition-colors hover:text-red-600"
                                title="Remove this file"
                                @click="removeRow(row.key)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Already-assigned employees for this file -->
                        <div v-if="row.assignments.length" class="flex flex-col gap-1.5">
                            <div
                                v-for="(a, i) in row.assignments"
                                :key="`${a.item.participant_id}-${a.item.requirement_id}`"
                                class="flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs"
                                :class="
                                    a.status === 'error'
                                        ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/20'
                                        : isDuplicate(a.item)
                                          ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/20'
                                          : 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900 dark:bg-emerald-950/10'
                                "
                            >
                                <span class="min-w-0 flex-1 truncate">
                                    <span class="font-semibold">{{ a.item.employee_name }}</span> — {{ a.item.requirement_title }} —
                                    {{ a.item.program_title }} ({{ a.item.batch_label }})
                                </span>
                                <LoaderCircle v-if="a.status === 'uploading'" class="h-3.5 w-3.5 shrink-0 animate-spin text-blue-600" />
                                <CheckCircle2 v-else-if="a.status === 'done'" class="h-3.5 w-3.5 shrink-0 text-emerald-600" />
                                <XCircle v-else-if="a.status === 'error'" class="h-3.5 w-3.5 shrink-0 text-red-600" />
                                <AlertTriangle
                                    v-else-if="isDuplicate(a.item)"
                                    class="h-3.5 w-3.5 shrink-0 text-amber-500"
                                    title="Another file is also assigned to this same employee + requirement — the later upload will overwrite the earlier one."
                                />
                                <button
                                    v-if="a.status !== 'uploading' && a.status !== 'done'"
                                    type="button"
                                    class="shrink-0 text-muted-foreground hover:text-red-600"
                                    title="Remove this assignment"
                                    @click="removeAssignment(row, i)"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <p v-for="(a, i) in row.assignments.filter((x) => x.error)" :key="`err-${i}`" class="text-[11px] text-red-600">
                                {{ a.item.employee_name }}: {{ a.error }}
                            </p>
                        </div>

                        <!-- Search to add (another) employee -->
                        <div class="relative">
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    v-model="row.query"
                                    type="text"
                                    :placeholder="
                                        row.assignments.length
                                            ? 'Add another employee for this same file…'
                                            : 'Search employee name or empcode to assign this file…'
                                    "
                                    class="h-8 w-full rounded-lg border bg-background pl-8 pr-8 text-xs outline-none focus:ring-2 focus:ring-blue-500/30"
                                    @input="onQueryInput(row)"
                                    @focus="row.showResults = true"
                                />
                                <button
                                    v-if="row.query"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="
                                        row.query = '';
                                        row.results = [];
                                    "
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <!-- Results dropdown -->
                            <div
                                v-if="row.showResults && row.query.trim()"
                                class="absolute left-0 right-0 top-full z-10 mt-1 max-h-56 overflow-y-auto rounded-lg border bg-background shadow-lg"
                            >
                                <div v-if="row.searching" class="flex items-center justify-center py-3">
                                    <LoaderCircle class="h-4 w-4 animate-spin text-muted-foreground" />
                                </div>
                                <button
                                    v-for="item in row.results"
                                    :key="`${item.participant_id}-${item.requirement_id}`"
                                    type="button"
                                    class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-xs transition-colors hover:bg-muted/50"
                                    @click="pickResult(row, item)"
                                >
                                    <span class="font-semibold">{{ item.employee_name }} <span class="font-normal text-muted-foreground">({{ item.empcode }})</span></span>
                                    <span class="text-[11px] text-muted-foreground">
                                        {{ item.requirement_title }} — {{ item.program_title }} ({{ item.batch_label }})
                                        <span v-if="item.is_overdue" class="ml-1 font-semibold text-red-500">overdue</span>
                                    </span>
                                </button>
                                <p v-if="!row.searching && row.results.length === 0" class="px-3 py-3 text-center text-xs text-muted-foreground">
                                    No matching missing requirements found.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex shrink-0 flex-col gap-2 border-t px-5 py-3">
                <p v-if="finished && (doneCount || errorCount)" class="text-xs font-semibold">
                    <span class="text-emerald-600">{{ doneCount }} uploaded</span>
                    <span v-if="errorCount"> · <span class="text-red-600">{{ errorCount }} failed</span></span>
                </p>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" size="sm" @click="close">Close</Button>
                    <Button size="sm" class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="!canUpload" @click="uploadAll">
                        <LoaderCircle v-if="uploading" class="mr-1 h-3.5 w-3.5 animate-spin" />
                        <Upload v-else class="mr-1 h-3.5 w-3.5" />
                        Upload {{ pendingCount || '' }} Submission{{ pendingCount === 1 ? '' : 's' }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <FilePreviewModal
        :open="previewOpen"
        :file-url="previewRow?.previewUrl ?? null"
        :title="previewRow?.file.name"
        content-class="z-[80]"
        @update:open="previewOpen = $event"
    />
</template>
