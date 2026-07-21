<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps<{
    programId: number;
    hasFile: boolean;
}>();

const emit = defineEmits<{
    (e: 'uploaded'): void;
    (e: 'deleted'): void;
}>();

const MAX_BYTES = 10 * 1024 * 1024;

const fileInput = ref<HTMLInputElement | null>(null);
const processing = ref(false);
const error = ref('');

function triggerPick() {
    error.value = '';
    fileInput.value?.click();
}

async function handleFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    (e.target as HTMLInputElement).value = '';
    if (!file) return;

    if (file.type !== 'application/pdf') {
        error.value = 'Please choose a PDF file.';
        return;
    }
    if (file.size > MAX_BYTES) {
        error.value = 'File is too large — please choose one under 10MB.';
        return;
    }

    error.value = '';
    processing.value = true;
    const data = new FormData();
    data.append('file', file);
    try {
        await axios.post(route('nhrdc.signed-copy.upload', props.programId), data);
        emit('uploaded');
    } catch (err: any) {
        error.value = err.response?.data?.errors?.file?.[0] ?? 'Failed to upload signed copy.';
    } finally {
        processing.value = false;
    }
}

async function destroy() {
    if (!confirm('Delete this signed copy?')) return;
    error.value = '';
    processing.value = true;
    try {
        await axios.delete(route('nhrdc.signed-copy.destroy', props.programId));
        emit('deleted');
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <a
            v-if="hasFile"
            :href="route('nhrdc.signed-copy.download', programId)"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-blue-600 shadow-sm transition hover:bg-blue-50"
        >
            View Signed Copy
        </a>
        <button
            type="button"
            :disabled="processing"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
            @click="triggerPick"
        >
            {{ processing ? 'Uploading…' : hasFile ? 'Replace Signed Copy' : 'Upload Signed Copy' }}
        </button>
        <button
            v-if="hasFile"
            type="button"
            :disabled="processing"
            class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-red-600 shadow-sm transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
            @click="destroy"
        >
            Delete
        </button>
        <span v-if="!hasFile && !processing" class="text-[11px] text-muted-foreground">No signed copy uploaded yet.</span>
        <input ref="fileInput" type="file" accept="application/pdf" class="hidden" @change="handleFileChange" />
        <p v-if="error" class="w-full text-[11px] text-red-600">{{ error }}</p>
    </div>
</template>
