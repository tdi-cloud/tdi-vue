<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import SignaturePadLib from 'signature_pad';

const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

defineProps<{
    modelValue?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', payload: string | null): void;
}>();

const canvasEl = ref<HTMLCanvasElement | null>(null);
const fileInputEl = ref<HTMLInputElement | null>(null);
const mode = ref<'draw' | 'upload'>('draw');
const uploadError = ref('');
let pad: SignaturePadLib | null = null;

function teardownPad() {
    pad?.off();
    pad = null;
}

function resizeCanvas() {
    const canvas = canvasEl.value;
    if (!canvas || !pad) return;

    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const { width, height } = canvas.getBoundingClientRect();
    canvas.width = width * ratio;
    canvas.height = height * ratio;
    canvas.getContext('2d')?.scale(ratio, ratio);
    pad.clear(); // resizing wipes the canvas bitmap, so the pad's stroke data must be cleared too
}

function handleEnd() {
    emit('update:modelValue', pad && !pad.isEmpty() ? pad.toDataURL('image/png') : null);
}

function clear() {
    pad?.clear();
    emit('update:modelValue', null);
}

async function initPad() {
    await nextTick();
    if (!canvasEl.value) return;
    teardownPad();
    pad = new SignaturePadLib(canvasEl.value, { backgroundColor: 'rgb(255, 255, 255)' });
    pad.addEventListener('endStroke', handleEnd);
    resizeCanvas();
}

function triggerUpload() {
    uploadError.value = '';
    fileInputEl.value?.click();
}

function handleFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = ''; // allow re-selecting the same file afterwards
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        uploadError.value = 'Please choose an image file.';
        return;
    }
    if (file.size > MAX_UPLOAD_BYTES) {
        uploadError.value = 'Image is too large — please choose one under 2MB.';
        return;
    }

    uploadError.value = '';
    const reader = new FileReader();
    reader.onload = () => {
        teardownPad();
        mode.value = 'upload';
        emit('update:modelValue', reader.result as string);
    };
    reader.readAsDataURL(file);
}

function switchToDraw() {
    mode.value = 'draw';
    uploadError.value = '';
    emit('update:modelValue', null);
    initPad();
}

function removeUpload() {
    uploadError.value = '';
    emit('update:modelValue', null);
}

onMounted(() => {
    initPad();
    window.addEventListener('resize', resizeCanvas);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', resizeCanvas);
    teardownPad();
});
</script>

<template>
    <div>
        <template v-if="mode === 'upload' && modelValue">
            <img
                :src="modelValue"
                alt="Uploaded signature"
                class="h-40 w-full max-w-md rounded-lg border border-gray-300 bg-white object-contain p-2"
            />
            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium">
                <button type="button" class="text-blue-600 hover:text-blue-700" @click="switchToDraw">
                    Draw signature instead
                </button>
                <button type="button" class="text-red-600 hover:text-red-700" @click="removeUpload">
                    Remove
                </button>
            </div>
        </template>
        <template v-else>
            <canvas
                ref="canvasEl"
                class="h-40 w-full max-w-md touch-none rounded-lg border border-gray-300 bg-white"
                aria-label="Signature pad — sign with your mouse or finger"
            ></canvas>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium">
                <button type="button" class="text-blue-600 hover:text-blue-700" @click="clear">
                    Clear signature
                </button>
                <button type="button" class="text-blue-600 hover:text-blue-700" @click="triggerUpload">
                    Or upload a photo of your signature
                </button>
            </div>
        </template>
        <input
            ref="fileInputEl"
            type="file"
            accept="image/png,image/jpeg"
            class="hidden"
            @change="handleFileChange"
        />
        <p v-if="uploadError" class="mt-1 text-xs text-red-600">{{ uploadError }}</p>
    </div>
</template>
