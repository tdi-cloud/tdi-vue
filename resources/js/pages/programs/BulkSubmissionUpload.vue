<script setup lang="ts">
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, CheckCircle2, ChevronDown, CloudUpload, Eye, FileText, LoaderCircle, Trash2, Upload, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Requirement {
    id: number;
    title: string;
    name: string;
}

interface Participant {
    id: number;
    empcode: string;
    employee?: { name: string } | null;
}

interface Batch {
    id: number;
    program_code: string;
    participants: Participant[];
    requirements: Requirement[];
}

const props = defineProps<{
    open: boolean;
    batch: Batch | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

interface Row {
    key: string;
    file: File;
    previewUrl: string;
    participantId: number | null;
    status: 'idle' | 'uploading' | 'done' | 'error';
    error: string | null;
}

const requirementId = ref('');
const rows = ref<Row[]>([]);
const uploading = ref(false);
const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const sortedParticipants = computed(() =>
    [...(props.batch?.participants ?? [])].sort((a, b) => (a.employee?.name ?? a.empcode).localeCompare(b.employee?.name ?? b.empcode)),
);

const participantLabel = (p: Participant) => `${p.employee?.name ?? '(no name)'} — ${p.empcode}`;

// Ilang beses pang napili ang parehong empleyado sa ibang row — para
// mabantayan kung sino ang aksidenteng na-duplicate ang assignment (ang
// susunod na submit ay papalitan lang ang una, hindi dalawang hiwalay na file).
const duplicateParticipantIds = computed(() => {
    const seen = new Map<number, number>();
    for (const row of rows.value) {
        if (!row.participantId) continue;
        seen.set(row.participantId, (seen.get(row.participantId) ?? 0) + 1);
    }
    return new Set([...seen.entries()].filter(([, count]) => count > 1).map(([id]) => id));
});

function addFiles(fileList: FileList | File[]) {
    for (const file of Array.from(fileList)) {
        if (file.type !== 'application/pdf') continue;
        rows.value.push({
            key: `${file.name}-${file.size}-${file.lastModified}-${rows.value.length}`,
            file,
            previewUrl: URL.createObjectURL(file),
            participantId: null,
            status: 'idle',
            error: null,
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

/* ---------- File preview (local, not-yet-uploaded PDF) ---------- */
const previewOpen = ref(false);
const previewRow = ref<Row | null>(null);

function openPreview(row: Row) {
    previewRow.value = row;
    previewOpen.value = true;
}

/* ---------- Upload ---------- */
const assignedCount = computed(() => rows.value.filter((r) => r.participantId).length);
const doneCount = computed(() => rows.value.filter((r) => r.status === 'done').length);
const errorCount = computed(() => rows.value.filter((r) => r.status === 'error').length);
const finished = computed(() => rows.value.length > 0 && rows.value.every((r) => r.status === 'done' || r.status === 'error' || !r.participantId));

const canUpload = computed(() => !!requirementId.value && assignedCount.value > 0 && !uploading.value);

async function uploadAll() {
    if (!props.batch || !requirementId.value) return;
    uploading.value = true;

    for (const row of rows.value) {
        if (!row.participantId || row.status === 'done') continue;
        row.status = 'uploading';
        row.error = null;

        const formData = new FormData();
        formData.append('participant_id', String(row.participantId));
        formData.append('batch_id', String(props.batch.id));
        formData.append('requirement_id', requirementId.value);
        formData.append('program_code', props.batch.program_code);
        formData.append('status', 'Approved');
        formData.append('file', row.file);

        try {
            await axios.post(route('submissions.store'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            row.status = 'done';
        } catch (err: any) {
            row.status = 'error';
            row.error = err.response?.data?.errors?.file?.[0] ?? err.response?.data?.message ?? 'Upload failed.';
        }
    }

    uploading.value = false;
}

function reset() {
    rows.value.forEach((r) => URL.revokeObjectURL(r.previewUrl));
    rows.value = [];
    requirementId.value = '';
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
        <DialogContent class="flex h-[85vh] w-full max-w-3xl flex-col gap-0 overflow-hidden !rounded-2xl p-0">
            <DialogHeader class="shrink-0 border-b px-5 py-4 text-left">
                <DialogTitle class="flex items-center gap-2"><CloudUpload class="h-4 w-4 text-blue-600" /> Bulk Upload Submissions</DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    Upload many PDF files at once, then assign each one to the employee it belongs to.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                <!-- Requirement picker — one requirement type per session -->
                <div class="grid gap-1.5">
                    <Label class="text-xs">Requirement <span class="text-red-500">*</span></Label>
                    <div class="relative w-full sm:w-64">
                        <select
                            v-model="requirementId"
                            class="h-9 w-full appearance-none rounded-lg border bg-background px-3 pr-8 text-xs outline-none focus:ring-2 focus:ring-blue-500/30"
                        >
                            <option value="" disabled>— Select requirement —</option>
                            <option v-for="req in batch?.requirements ?? []" :key="req.id" :value="String(req.id)">
                                {{ req.title }} — {{ req.name }}
                            </option>
                        </select>
                        <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    </div>
                    <p class="text-[11px] text-muted-foreground">All files below will be filed under this one requirement.</p>
                </div>

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
                        {{ rows.length }} file(s) — {{ assignedCount }} assigned
                    </p>

                    <div
                        v-for="row in rows"
                        :key="row.key"
                        class="flex flex-col gap-2 rounded-xl border p-3 sm:flex-row sm:items-center"
                        :class="row.status === 'error' ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/20' : ''"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-2">
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
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <div class="relative w-56">
                                <select
                                    v-model="row.participantId"
                                    :disabled="row.status === 'uploading' || row.status === 'done'"
                                    class="h-8 w-full appearance-none rounded-lg border bg-background px-2.5 pr-7 text-xs outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-60"
                                >
                                    <option :value="null">— Select employee —</option>
                                    <option v-for="p in sortedParticipants" :key="p.id" :value="p.id">{{ participantLabel(p) }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-2 top-1/2 h-3 w-3 -translate-y-1/2 text-muted-foreground" />
                            </div>

                            <!-- Status -->
                            <LoaderCircle v-if="row.status === 'uploading'" class="h-4 w-4 shrink-0 animate-spin text-blue-600" />
                            <CheckCircle2 v-else-if="row.status === 'done'" class="h-4 w-4 shrink-0 text-emerald-600" />
                            <XCircle v-else-if="row.status === 'error'" class="h-4 w-4 shrink-0 text-red-600" />
                            <AlertTriangle
                                v-else-if="row.participantId && duplicateParticipantIds.has(row.participantId)"
                                class="h-4 w-4 shrink-0 text-amber-500"
                                title="This employee is already assigned to another file above — the later upload will overwrite the earlier one."
                            />

                            <button
                                v-if="row.status !== 'uploading'"
                                type="button"
                                class="shrink-0 text-muted-foreground transition-colors hover:text-red-600"
                                title="Remove"
                                @click="removeRow(row.key)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <p v-if="row.error" class="w-full text-[11px] text-red-600 sm:basis-full">{{ row.error }}</p>
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
                        Upload {{ assignedCount || '' }} File{{ assignedCount === 1 ? '' : 's' }}
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
