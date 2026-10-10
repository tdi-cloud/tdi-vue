<template>
    <div>
        <ol class="doc-steps">
            <li v-for="(step, index) in steps" :key="step.id" class="doc-step">
                <span class="doc-step__num" aria-hidden="true">{{ index + 1 }}</span>
                <div class="doc-step__content">
                    <h3 class="doc-step__title">
                        <span class="sr-only">Step {{ index + 1 }}: </span>{{ step.title }}
                    </h3>
                    <p v-if="step.body" class="doc-step__body">{{ step.body }}</p>
                    <button
                        v-if="step.image_url"
                        type="button"
                        class="doc-step__shot"
                        :aria-label="`Enlarge screenshot for step ${index + 1}`"
                        @click="zoomed = step"
                    >
                        <img :src="step.image_url" :alt="`Screenshot: ${step.title}`" loading="lazy" />
                        <span class="doc-step__zoom" aria-hidden="true"><ZoomIn :size="15" /> Click to enlarge</span>
                    </button>
                </div>
            </li>
        </ol>

        <Teleport to="body">
            <div v-if="zoomed" class="doc-lightbox" role="dialog" aria-modal="true" :aria-label="zoomed.title" @click="zoomed = null">
                <img :src="zoomed.image_url ?? undefined" :alt="`Screenshot: ${zoomed.title}`" />
                <button type="button" class="doc-lightbox__close" aria-label="Close screenshot" @click.stop="zoomed = null"><X :size="20" /></button>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { X, ZoomIn } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { HowToDocStep } from './HowToVideoPlayer.vue';

defineProps<{ steps: HowToDocStep[] }>();

const zoomed = ref<HowToDocStep | null>(null);

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && zoomed.value) {
        event.stopPropagation();
        zoomed.value = null;
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown, true));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown, true));
</script>

<style scoped>
/* Colors come from the parent's --hd-* tokens, with light fallbacks. */
.doc-steps {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 1.75rem;
}
.doc-step {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr);
    gap: 1rem;
}
.doc-step__num {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: var(--hd-accent, #1d3fc4);
    color: #fff;
    font-weight: 800;
    font-size: 0.95rem;
    font-variant-numeric: tabular-nums;
}
.doc-step__content {
    display: grid;
    gap: 0.5rem;
    min-width: 0;
    padding-top: 0.35rem;
}
.doc-step__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.35;
    color: var(--hd-text, #1a2744);
}
.doc-step__body {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.65;
    white-space: pre-line;
    color: var(--hd-text-2, #4b5671);
    max-width: 70ch;
}
.doc-step__shot {
    position: relative;
    display: block;
    margin-top: 0.35rem;
    padding: 0;
    border: 1px solid var(--hd-border, #e3e8f3);
    border-radius: 12px;
    overflow: hidden;
    background: var(--hd-surface-2, #f8f9fc);
    cursor: zoom-in;
    max-width: 100%;
}
.doc-step__shot img {
    display: block;
    width: 100%;
    height: auto;
}
.doc-step__shot:focus-visible {
    outline: 2px solid var(--hd-accent, #1d3fc4);
    outline-offset: 2px;
}
.doc-step__zoom {
    position: absolute;
    right: 0.6rem;
    bottom: 0.6rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.6rem;
    border-radius: 999px;
    background: rgba(8, 15, 35, 0.7);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 600;
    opacity: 0;
    transition: opacity 0.2s;
}
.doc-step__shot:hover .doc-step__zoom,
.doc-step__shot:focus-visible .doc-step__zoom {
    opacity: 1;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
}
.doc-lightbox {
    position: fixed;
    inset: 0;
    z-index: 200;
    display: grid;
    place-items: center;
    padding: 1.5rem;
    background: rgba(8, 15, 35, 0.88);
    cursor: zoom-out;
}
.doc-lightbox img {
    max-width: 100%;
    max-height: 100%;
    border-radius: 10px;
    box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.7);
}
.doc-lightbox__close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 0;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    cursor: pointer;
}
@media print {
    .doc-step {
        break-inside: avoid;
    }
    .doc-step__zoom {
        display: none;
    }
}
</style>
