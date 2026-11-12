<script setup lang="ts">
import { toast } from '@/components/ui/toast/use-toast';
import UploadProgress from '@/components/UploadProgress.vue';
import { useConfirm } from '@/composables/useConfirm';
import { httpErrorMessage } from '@/lib/http';
import type { PhotoRules } from '@/lib/positionDirectory';
import axios, { type AxiosError } from 'axios';
import { Check, ImageUp, Loader2, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps<{
    /** Pictures are linked by EMPCODE (it survives HR's monthly re-import; the database id doesn't). */
    employee: { empcode: string; name: string };
    currentUrl: string | null;
    rules: PhotoRules;
}>();

const emit = defineEmits<{
    /** Local preview of the chosen file (null when cleared), shown in the large portrait. */
    preview: [url: string | null];
    /** The stored picture changed: new public URL, or null when removed. */
    saved: [url: string | null];
}>();

const { confirmDialog } = useConfirm();

const ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const inputRef = ref<HTMLInputElement | null>(null);
const chooseRef = ref<HTMLButtonElement | null>(null);
const selectedFile = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const progress = ref<number | null>(null);
const busy = ref<'upload' | 'remove' | null>(null);
const isDragging = ref(false);

const maxMegabytes = computed(() => Math.round((props.rules.maxKilobytes / 1024) * 10) / 10);
const typesLabel = computed(() => [...new Set(props.rules.types.map((type) => (type === 'jpeg' ? 'jpg' : type).toUpperCase()))].join(', '));
const acceptAttribute = computed(() => [...props.rules.types.map((type) => `.${type}`), ...ACCEPTED_MIME_TYPES].join(','));

function clearSelection(): void {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = null;
    selectedFile.value = null;
    progress.value = null;
    if (inputRef.value) {
        inputRef.value.value = '';
    }
    emit('preview', null);
}

function choose(file: File | undefined): void {
    errorMessage.value = null;
    if (!file) {
        return;
    }

    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
    if (!ACCEPTED_MIME_TYPES.includes(file.type) && !props.rules.types.includes(extension)) {
        errorMessage.value = `Use a ${typesLabel.value} picture.`;
        return;
    }
    if (file.size > props.rules.maxKilobytes * 1024) {
        errorMessage.value = `The picture must be ${maxMegabytes.value} MB or smaller.`;
        return;
    }

    clearSelection();
    selectedFile.value = file;
    previewUrl.value = URL.createObjectURL(file);
    emit('preview', previewUrl.value);
}

function onFileInput(event: Event): void {
    choose((event.target as HTMLInputElement).files?.[0]);
}

function onDrop(event: DragEvent): void {
    isDragging.value = false;
    choose(event.dataTransfer?.files?.[0]);
}

function errorFrom(error: unknown): string {
    const response = (error as AxiosError<{ errors?: Record<string, string[]>; message?: string }>).response;
    if (response?.status === 422) {
        return response.data?.errors?.photo?.[0] ?? response.data?.message ?? 'Please choose a different picture.';
    }
    return httpErrorMessage(error);
}

async function save(): Promise<void> {
    if (!selectedFile.value || busy.value) {
        return;
    }

    busy.value = 'upload';
    errorMessage.value = null;
    progress.value = 0;

    const formData = new FormData();
    formData.append('photo', selectedFile.value);

    try {
        const { data } = await axios.post<{ photo_url: string; title: string; message: string }>(
            route('employees.photo.update', props.employee.empcode),
            formData,
            {
                silentErrors: true,
                onUploadProgress: (event) => {
                    progress.value = event.total ? Math.round((event.loaded / event.total) * 100) : null;
                },
            },
        );
        clearSelection();
        emit('saved', data.photo_url);
        toast({ title: data.title, description: data.message });
    } catch (error) {
        errorMessage.value = errorFrom(error);
        progress.value = null;
    } finally {
        busy.value = null;
    }
}

async function remove(): Promise<void> {
    if (busy.value) {
        return;
    }

    const confirmed = await confirmDialog(`${props.employee.name} will show the default placeholder instead.`, {
        title: 'Remove this profile picture?',
        confirmText: 'Remove picture',
    });
    if (!confirmed) {
        return;
    }

    busy.value = 'remove';
    errorMessage.value = null;

    try {
        const { data } = await axios.delete<{ photo_url: null; title: string; message: string }>(
            route('employees.photo.destroy', props.employee.empcode),
            { silentErrors: true },
        );
        emit('saved', null);
        toast({ title: data.title, description: data.message });
    } catch (error) {
        errorMessage.value = errorFrom(error);
    } finally {
        busy.value = null;
    }
}

onBeforeUnmount(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

defineExpose({ focus: () => chooseRef.value?.focus() });
</script>

<template>
    <section class="pd-photo" aria-labelledby="pd-photo-title">
        <div class="pd-photo__head">
            <h3 id="pd-photo-title" class="pd-photo__title">Profile picture</h3>
            <p class="pd-photo__hint">{{ typesLabel }} · up to {{ maxMegabytes }} MB · saved as an optimized WebP</p>
        </div>

        <input ref="inputRef" type="file" class="sr-only" tabindex="-1" :accept="acceptAttribute" aria-hidden="true" @change="onFileInput" />

        <template v-if="!selectedFile">
            <button
                ref="chooseRef"
                type="button"
                class="pd-photo__drop"
                :class="{ 'is-dragging': isDragging }"
                :disabled="busy !== null"
                @click="inputRef?.click()"
                @dragenter.prevent="isDragging = true"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onDrop"
            >
                <ImageUp class="h-5 w-5 shrink-0" aria-hidden="true" />
                <span>
                    <strong>{{ currentUrl ? 'Change picture' : 'Upload picture' }}</strong>
                    <span class="pd-photo__drop-sub">Click to browse, or drop an image here</span>
                </span>
            </button>

            <button v-if="currentUrl" type="button" class="pd-photo__remove" :disabled="busy !== null" @click="remove">
                <Loader2 v-if="busy === 'remove'" class="h-4 w-4 animate-spin" aria-hidden="true" />
                <Trash2 v-else class="h-4 w-4" aria-hidden="true" />
                {{ busy === 'remove' ? 'Removing…' : 'Remove picture' }}
            </button>
        </template>

        <div v-else class="pd-photo__pending">
            <p class="pd-photo__file">
                <span class="truncate font-semibold">{{ selectedFile.name }}</span>
                <span class="shrink-0">{{ (selectedFile.size / 1024 / 1024).toFixed(2) }} MB</span>
            </p>
            <p class="pd-photo__preview-note">The large portrait shows a preview. Save to use it in the directory.</p>

            <UploadProgress v-if="busy === 'upload'" :percentage="progress" label="Uploading profile picture" />

            <div class="pd-photo__actions">
                <button type="button" class="pd-photo__save" :disabled="busy !== null" @click="save">
                    <Loader2 v-if="busy === 'upload'" class="h-4 w-4 animate-spin" aria-hidden="true" />
                    <Check v-else class="h-4 w-4" aria-hidden="true" />
                    {{ busy === 'upload' ? 'Saving…' : 'Save picture' }}
                </button>
                <button type="button" class="pd-photo__cancel" :disabled="busy !== null" @click="clearSelection">
                    <X class="h-4 w-4" aria-hidden="true" />
                    Cancel
                </button>
            </div>
        </div>

        <p v-if="errorMessage" class="pd-photo__error" role="alert">{{ errorMessage }}</p>
    </section>
</template>

<style scoped>
.pd-photo {
    display: grid;
    gap: 0.75rem;
    border-radius: 1rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface);
    padding: 1rem;
}

.pd-photo__title {
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--pd-gold-ink);
}

.pd-photo__hint {
    margin-top: 0.15rem;
    font-size: 0.75rem;
    color: var(--pd-ink-soft);
}

.pd-photo__drop {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.85rem;
    border: 1.5px dashed var(--pd-drop-border);
    background: var(--pd-drop-bg);
    padding: 0.9rem 1rem;
    text-align: left;
    font-size: 0.85rem;
    color: var(--pd-ink);
    transition:
        border-color 0.2s ease,
        background-color 0.2s ease,
        transform 0.2s ease;
}

.pd-photo__drop:hover:not(:disabled),
.pd-photo__drop.is-dragging {
    border-color: var(--pd-royal);
    background: var(--pd-hover);
}

.pd-photo__drop.is-dragging {
    transform: scale(1.01);
}

.pd-photo__drop svg {
    color: var(--pd-royal);
}

.pd-photo__drop-sub {
    display: block;
    font-size: 0.75rem;
    color: var(--pd-ink-soft);
}

.pd-photo__drop:focus-visible,
.pd-photo__save:focus-visible,
.pd-photo__cancel:focus-visible,
.pd-photo__remove:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: 2px;
}

.pd-photo__remove {
    justify-self: start;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.6rem;
    padding: 0.35rem 0.6rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--pd-danger);
    transition: background-color 0.2s ease;
}

.pd-photo__remove:hover:not(:disabled) {
    background: var(--pd-danger-bg);
}

.pd-photo__pending {
    display: grid;
    gap: 0.6rem;
}

.pd-photo__file {
    display: flex;
    min-width: 0;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.8rem;
    color: var(--pd-ink);
}

.pd-photo__preview-note {
    font-size: 0.75rem;
    color: var(--pd-ink-soft);
}

.pd-photo__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.pd-photo__save,
.pd-photo__cancel {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.7rem;
    padding: 0.5rem 0.9rem;
    font-size: 0.82rem;
    font-weight: 700;
    transition: filter 0.2s ease;
}

.pd-photo__save {
    background: linear-gradient(120deg, var(--pd-navy), var(--pd-royal));
    color: #ffffff;
    box-shadow: 0 8px 20px -10px var(--pd-royal);
}

.pd-photo__save:hover:not(:disabled) {
    filter: brightness(1.1);
}

.pd-photo__cancel {
    border: 1px solid var(--pd-border);
    color: var(--pd-ink);
}

.pd-photo__cancel:hover:not(:disabled) {
    background: var(--pd-hover);
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.pd-photo__error {
    border-radius: 0.6rem;
    background: var(--pd-danger-bg);
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--pd-danger);
}
</style>
