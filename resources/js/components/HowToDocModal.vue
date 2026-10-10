<template>
    <Teleport to="body">
        <div v-if="video" class="docm" role="dialog" aria-modal="true" aria-labelledby="docm-title" @click.self="requestClose">
            <div class="docm__box">
                <!-- Header -->
                <div class="docm__head">
                    <span class="docm__badge" aria-hidden="true"><BookOpenText :size="18" /></span>
                    <div class="docm__heading">
                        <h2 id="docm-title">{{ video.title }}</h2>
                        <p v-if="editing">Editing documentation · {{ draft.length }} step{{ draft.length === 1 ? '' : 's' }}</p>
                        <p v-else>Documentation · {{ steps.length }} step{{ steps.length === 1 ? '' : 's' }}</p>
                    </div>
                    <button type="button" class="docm__close" aria-label="Close" :disabled="form.processing" @click="requestClose">
                        <X :size="18" />
                    </button>
                </div>

                <!-- Read mode -->
                <template v-if="!editing">
                    <div class="docm__toolbar">
                        <button type="button" class="tool" @click="copyLink">
                            <Check v-if="copied" :size="15" /><Link2 v-else :size="15" /> {{ copied ? 'Link copied' : 'Copy link' }}
                        </button>
                        <Link :href="video.documentation_url" class="tool"><ExternalLink :size="15" /> Open as page</Link>
                        <button v-if="canManage" type="button" class="tool tool--accent" @click="startEditing">
                            <Pencil :size="15" /> Edit documentation
                        </button>
                        <input
                            v-if="copyFallback"
                            ref="copyInput"
                            class="copy-fallback"
                            :value="video.documentation_url"
                            readonly
                            aria-label="Documentation link"
                            @focus="($event.target as HTMLInputElement).select()"
                        />
                    </div>
                    <div class="docm__body">
                        <p v-if="video.description" class="docm__intro">{{ video.description }}</p>
                        <HowToDocSteps v-if="steps.length" :steps="steps" />
                        <div v-else class="docm__empty">
                            <FileText :size="26" />
                            <p>No written steps yet for this video.</p>
                        </div>
                    </div>
                </template>

                <!-- Edit mode (superadmin) -->
                <form v-else class="docm__form" @submit.prevent="save">
                    <div class="docm__body docm__body--edit">
                        <p class="docm__hint">
                            Write one step per action. Tip: take a screenshot (Windows + Shift + S), click inside a step, and press Ctrl + V to paste
                            it.
                        </p>
                        <p v-if="hasErrors" class="docm__error" role="alert">Some steps need fixing. Check the messages below.</p>

                        <div v-for="(step, index) in draft" :key="step.key" class="ed-step" tabindex="-1" @paste="pasteImage(step, $event)">
                            <div class="ed-step__head">
                                <span class="ed-step__num">{{ index + 1 }}</span>
                                <span class="ed-step__label">Step {{ index + 1 }}</span>
                                <div class="ed-step__tools">
                                    <button type="button" class="icon-btn" :disabled="index === 0" aria-label="Move step up" @click="move(index, -1)">
                                        <ArrowUp :size="15" />
                                    </button>
                                    <button
                                        type="button"
                                        class="icon-btn"
                                        :disabled="index === draft.length - 1"
                                        aria-label="Move step down"
                                        @click="move(index, 1)"
                                    >
                                        <ArrowDown :size="15" />
                                    </button>
                                    <button type="button" class="icon-btn icon-btn--danger" aria-label="Delete step" @click="removeStep(index)">
                                        <Trash2 :size="15" />
                                    </button>
                                </div>
                            </div>

                            <label class="ed-field">
                                <span>Title <b>*</b></span>
                                <input
                                    :id="`step-title-${step.key}`"
                                    v-model="step.title"
                                    type="text"
                                    maxlength="150"
                                    placeholder="e.g. Click Create Program"
                                />
                                <small v-if="errorFor(`steps.${index}.title`)" class="err">{{ errorFor(`steps.${index}.title`) }}</small>
                            </label>
                            <label class="ed-field">
                                <span>Instructions</span>
                                <textarea
                                    :id="`step-body-${step.key}`"
                                    v-model="step.body"
                                    rows="3"
                                    maxlength="3000"
                                    placeholder="What should the user do in this step?"
                                ></textarea>
                            </label>

                            <div class="ed-shot">
                                <img v-if="previewOf(step)" :src="previewOf(step) ?? undefined" alt="Screenshot preview" />
                                <div v-else class="ed-shot__empty">
                                    <ImagePlus :size="20" /> No screenshot yet. Click in this step and press <kbd>Ctrl</kbd> + <kbd>V</kbd> to paste
                                    one.
                                </div>
                                <div class="ed-shot__acts">
                                    <label class="tool">
                                        <Upload :size="14" /> {{ previewOf(step) ? 'Replace' : 'Upload' }} screenshot
                                        <input type="file" accept="image/*" class="sr-only" @change="pickImage(step, $event)" />
                                    </label>
                                    <button v-if="previewOf(step)" type="button" class="tool tool--danger" @click="clearImage(step)">
                                        <X :size="14" /> Remove
                                    </button>
                                </div>
                                <small v-if="errorFor(`steps.${index}.image`)" class="err">{{ errorFor(`steps.${index}.image`) }}</small>
                            </div>
                        </div>

                        <button type="button" class="add-step" @click="addStep"><Plus :size="16" /> Add step</button>
                    </div>

                    <div class="docm__foot">
                        <button type="button" class="btn btn--ghost" :disabled="form.processing" @click="cancelEditing">Cancel</button>
                        <button type="submit" class="btn btn--primary" :disabled="form.processing">
                            <Loader2 v-if="form.processing" :size="16" class="spin" /> Save documentation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import { Link, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    BookOpenText,
    Check,
    ExternalLink,
    FileText,
    ImagePlus,
    Link2,
    Loader2,
    Pencil,
    Plus,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import HowToDocSteps from './HowToDocSteps.vue';
import type { HowToVideo } from './HowToVideoPlayer.vue';

const props = defineProps<{ video: HowToVideo | null; canManage: boolean; editOnOpen?: boolean }>();
const emit = defineEmits<{ close: []; saved: [] }>();

const { confirmDialog } = useConfirm();
const steps = computed(() => props.video?.steps ?? []);

/* ---- Copy link ---- */
const copied = ref(false);
const copyFallback = ref(false);
const copyInput = ref<HTMLInputElement | null>(null);
const copyLink = async () => {
    if (!props.video) return;
    try {
        await navigator.clipboard.writeText(props.video.documentation_url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard blocked (e.g. plain http): show the link selected so it can be copied by hand.
        copyFallback.value = true;
        await nextTick();
        copyInput.value?.focus();
    }
};

/* ---- Editing ---- */
interface DraftStep {
    key: number;
    id: number | null;
    title: string;
    body: string;
    imageUrl: string | null;
    file: File | null;
    filePreview: string | null;
    removeImage: boolean;
}

let nextKey = 1;
const editing = ref(false);
const draft = ref<DraftStep[]>([]);
const form = useForm<{ steps: unknown[] }>({ steps: [] });
const hasErrors = computed(() => Object.keys(form.errors).length > 0);
const errorFor = (key: string): string | undefined => (form.errors as Record<string, string>)[key];

const blankStep = (): DraftStep => ({
    key: nextKey++,
    id: null,
    title: '',
    body: '',
    imageUrl: null,
    file: null,
    filePreview: null,
    removeImage: false,
});

const startEditing = () => {
    form.clearErrors();
    draft.value = steps.value.map((s) => ({ ...blankStep(), id: s.id, title: s.title, body: s.body ?? '', imageUrl: s.image_url }));
    if (!draft.value.length) draft.value.push(blankStep());
    editing.value = true;
};

const isDirty = () =>
    draft.value.length !== steps.value.length ||
    draft.value.some((d, i) => {
        const s = steps.value[i];
        return !s || d.id !== s.id || d.title !== s.title || d.body !== (s.body ?? '') || d.file || d.removeImage;
    });

const cancelEditing = async () => {
    if (isDirty() && !(await confirmDialog('Discard your changes to this documentation?', { title: 'Discard changes?', confirmText: 'Discard' })))
        return;
    releasePreviews();
    editing.value = false;
    if (!steps.value.length) emit('close');
};

const requestClose = async () => {
    if (form.processing) return;
    if (
        editing.value &&
        isDirty() &&
        !(await confirmDialog('Discard your changes to this documentation?', { title: 'Discard changes?', confirmText: 'Discard' }))
    )
        return;
    releasePreviews();
    editing.value = false;
    emit('close');
};

const addStep = async () => {
    const step = blankStep();
    draft.value.push(step);
    await nextTick();
    document.getElementById(`step-title-${step.key}`)?.focus();
};

const move = (index: number, delta: number) => {
    const list = draft.value;
    [list[index], list[index + delta]] = [list[index + delta], list[index]];
};

const removeStep = async (index: number) => {
    const step = draft.value[index];
    if ((step.title || step.body || previewOf(step)) && !(await confirmDialog(`Delete step ${index + 1}?`, { title: 'Delete step?' }))) return;
    if (step.filePreview) URL.revokeObjectURL(step.filePreview);
    draft.value.splice(index, 1);
};

const setFile = (step: DraftStep, file: File) => {
    if (step.filePreview) URL.revokeObjectURL(step.filePreview);
    step.file = file;
    step.filePreview = URL.createObjectURL(file);
    step.removeImage = false;
};

const pickImage = (step: DraftStep, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) setFile(step, file);
    input.value = '';
};

const pasteImage = (step: DraftStep, event: ClipboardEvent) => {
    const item = [...(event.clipboardData?.items ?? [])].find((i) => i.type.startsWith('image/'));
    const file = item?.getAsFile();
    if (!file) return;
    event.preventDefault();
    setFile(step, new File([file], `step-screenshot.${file.type.split('/')[1] || 'png'}`, { type: file.type }));
};

const clearImage = (step: DraftStep) => {
    if (step.filePreview) URL.revokeObjectURL(step.filePreview);
    step.file = null;
    step.filePreview = null;
    if (step.imageUrl) step.removeImage = true;
};

const previewOf = (step: DraftStep) => step.filePreview ?? (step.removeImage ? null : step.imageUrl);

const releasePreviews = () => draft.value.forEach((s) => s.filePreview && URL.revokeObjectURL(s.filePreview));

const save = () => {
    if (!props.video) return;
    form.transform(() => ({
        steps: draft.value.map((s) => ({ id: s.id, title: s.title, body: s.body, image: s.file, remove_image: s.removeImage })),
    })).post(route('how-to-videos.documentation.update', props.video.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            releasePreviews();
            editing.value = false;
            emit('saved');
        },
    });
};

/* Open straight into edit mode when asked (e.g. a superadmin opening an empty guide). */
watch(
    () => props.video?.id,
    (id) => {
        copyFallback.value = false;
        editing.value = false;
        if (id && props.canManage && props.editOnOpen) startEditing();
    },
    { immediate: true },
);

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && props.video) requestClose();
};
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    releasePreviews();
});
</script>

<style scoped>
.docm {
    --hd-bg: #f4f6fb;
    --hd-surface: #ffffff;
    --hd-surface-2: #f8f9fc;
    --hd-border: #e3e8f3;
    --hd-text: #1a2744;
    --hd-text-2: #4b5671;
    --hd-muted: #6b7280;
    --hd-accent: #1d3fc4;
    --hd-accent-hover: #1834a3;
    --hd-accent-soft: #eef2ff;
    --hd-input: #ffffff;
    --hd-danger: #c92a2a;
    --hd-danger-soft: #fff5f5;
    --hd-success: #2b8a3e;

    position: fixed;
    inset: 0;
    z-index: 95;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(8, 15, 35, 0.6);
    backdrop-filter: blur(3px);
    font-family:
        'Inter',
        system-ui,
        -apple-system,
        sans-serif;
    color: var(--hd-text);
}
.dark .docm {
    --hd-bg: #0b1222;
    --hd-surface: #111a2e;
    --hd-surface-2: #0e1628;
    --hd-border: #1f2a44;
    --hd-text: #e6ebf5;
    --hd-text-2: #b6c0d6;
    --hd-muted: #8d99b3;
    --hd-accent: #4c6ef5;
    --hd-accent-hover: #5c7cfa;
    --hd-accent-soft: rgba(76, 110, 245, 0.14);
    --hd-input: #0b1222;
    --hd-danger: #ff8787;
    --hd-danger-soft: rgba(250, 82, 82, 0.1);
    --hd-success: #8ce99a;
}
.docm :where(*, *::before, *::after) {
    box-sizing: border-box;
}
.docm__box {
    width: min(820px, 100%);
    max-height: 100%;
    display: flex;
    flex-direction: column;
    background: var(--hd-surface);
    border: 1px solid var(--hd-border);
    border-radius: 18px;
    box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.5);
    overflow: hidden;
}
.docm__head {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--hd-border);
}
.docm__badge {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    background: var(--hd-accent-soft);
    color: var(--hd-accent);
}
.docm__heading {
    flex: 1;
    min-width: 0;
}
.docm__heading h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1.3;
}
.docm__heading p {
    margin: 0.1rem 0 0;
    font-size: 0.8rem;
    color: var(--hd-muted);
}
.docm__close {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    border: 0;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    background: var(--hd-surface-2);
    color: var(--hd-text);
    cursor: pointer;
}
.docm__toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--hd-border);
    background: var(--hd-surface-2);
}
.tool {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    height: 34px;
    padding: 0 0.8rem;
    border-radius: 9px;
    border: 1px solid var(--hd-border);
    background: var(--hd-surface);
    color: var(--hd-text-2);
    font: inherit;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}
.tool:hover {
    color: var(--hd-text);
    border-color: var(--hd-accent);
}
.tool--accent {
    background: var(--hd-accent);
    border-color: var(--hd-accent);
    color: #fff;
}
.tool--accent:hover {
    background: var(--hd-accent-hover);
    color: #fff;
}
.tool--danger {
    color: var(--hd-danger);
}
.tool:focus-visible,
.icon-btn:focus-visible,
.btn:focus-visible,
.add-step:focus-visible,
.docm__close:focus-visible {
    outline: 2px solid var(--hd-accent);
    outline-offset: 2px;
}
.copy-fallback {
    flex: 1 1 260px;
    min-width: 0;
    height: 34px;
    padding: 0 0.6rem;
    border-radius: 9px;
    border: 1px solid var(--hd-accent);
    background: var(--hd-input);
    color: var(--hd-text);
    font: inherit;
    font-size: 0.8rem;
}
.docm__body {
    padding: 1.25rem 1.5rem 1.75rem;
    overflow-y: auto;
}
.docm__intro {
    margin: 0 0 1.5rem;
    font-size: 0.95rem;
    line-height: 1.6;
    color: var(--hd-text-2);
}
.docm__empty {
    display: grid;
    justify-items: center;
    gap: 0.5rem;
    padding: 2.5rem 1rem;
    color: var(--hd-muted);
    text-align: center;
}
.docm__empty p {
    margin: 0;
}

/* ---- Editor ---- */
.docm__form {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
}
.docm__body--edit {
    display: grid;
    gap: 1rem;
    background: var(--hd-bg);
}
.docm__hint {
    margin: 0;
    font-size: 0.82rem;
    color: var(--hd-muted);
}
.docm__error {
    margin: 0;
    padding: 0.6rem 0.8rem;
    border-radius: 9px;
    background: var(--hd-danger-soft);
    color: var(--hd-danger);
    font-size: 0.85rem;
    font-weight: 600;
}
.ed-step {
    display: grid;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 14px;
    border: 1px solid var(--hd-border);
    background: var(--hd-surface);
}
.ed-step:focus-within {
    border-color: var(--hd-accent);
}
.ed-step__head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.ed-step__num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: var(--hd-accent);
    color: #fff;
    font-weight: 800;
    font-size: 0.82rem;
}
.ed-step__label {
    font-weight: 700;
    font-size: 0.9rem;
}
.ed-step__tools {
    margin-left: auto;
    display: flex;
    gap: 0.3rem;
}
.icon-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px solid var(--hd-border);
    background: var(--hd-surface);
    color: var(--hd-text-2);
    display: grid;
    place-items: center;
    cursor: pointer;
}
.icon-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
.icon-btn--danger {
    color: var(--hd-danger);
}
.ed-field {
    display: grid;
    gap: 0.3rem;
}
.ed-field > span {
    font-size: 0.82rem;
    font-weight: 700;
}
.ed-field b {
    color: #e03131;
}
/* Extra selector weight so the app's global dark-mode input style doesn't override these. */
.docm .ed-step .ed-field input[type='text'],
.docm .ed-step .ed-field textarea {
    font: inherit;
    font-size: 0.9rem;
    padding: 0.55rem 0.7rem;
    border-radius: 9px;
    border: 1px solid var(--hd-border);
    background: var(--hd-input);
    color: var(--hd-text);
    resize: vertical;
}
.docm .ed-step .ed-field input[type='text']:focus,
.docm .ed-step .ed-field textarea:focus {
    outline: none;
    border-color: var(--hd-accent);
    box-shadow: 0 0 0 3px rgba(76, 110, 245, 0.2);
}
.err {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hd-danger);
}
.ed-shot {
    display: grid;
    gap: 0.5rem;
}
.ed-shot img {
    max-width: 100%;
    max-height: 240px;
    justify-self: start;
    border-radius: 10px;
    border: 1px solid var(--hd-border);
}
.ed-shot__empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    min-height: 72px;
    padding: 0.75rem;
    text-align: center;
    flex-wrap: wrap;
    border-radius: 10px;
    border: 1px dashed var(--hd-border);
    color: var(--hd-muted);
    font-size: 0.82rem;
}
.ed-shot__empty kbd {
    padding: 0.05rem 0.35rem;
    border-radius: 5px;
    border: 1px solid var(--hd-border);
    background: var(--hd-surface-2);
    font: inherit;
    font-size: 0.75rem;
    font-weight: 700;
}
.ed-shot__acts {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
}
.add-step {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    height: 44px;
    border-radius: 12px;
    border: 1px dashed var(--hd-accent);
    background: transparent;
    color: var(--hd-accent);
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}
.add-step:hover {
    background: var(--hd-accent-soft);
}
.docm__foot {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    padding: 0.85rem 1.25rem;
    border-top: 1px solid var(--hd-border);
    background: var(--hd-surface-2);
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    height: 40px;
    padding: 0 1rem;
    border-radius: 10px;
    border: 1px solid transparent;
    font: inherit;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
}
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn--primary {
    background: var(--hd-accent);
    color: #fff;
}
.btn--primary:hover:not(:disabled) {
    background: var(--hd-accent-hover);
}
.btn--ghost {
    background: var(--hd-surface);
    border-color: var(--hd-border);
    color: var(--hd-text);
}
.spin {
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
@media (max-width: 560px) {
    .docm {
        padding: 0;
    }
    .docm__box {
        border-radius: 0;
        height: 100%;
    }
    .docm__body {
        padding: 1rem;
    }
}
</style>
