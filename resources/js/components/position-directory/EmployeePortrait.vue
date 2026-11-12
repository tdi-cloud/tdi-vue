<script setup lang="ts">
import { ref, useId, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        src: string | null;
        name: string;
        /** Load right away (the modal) instead of when scrolled near (cards). */
        eager?: boolean;
    }>(),
    { eager: false },
);

const hasFailed = ref(false);
const hasLoaded = ref(false);

// A new picture (upload, preview, another employee) gets a fresh chance to load.
watch(
    () => props.src,
    () => {
        hasFailed.value = false;
        hasLoaded.value = false;
    },
);

const gradientId = `pd-portrait-${useId()}`;
</script>

<template>
    <div class="pd-portrait">
        <!-- Placeholder: always rendered underneath, so a slow or broken picture never shows an empty box -->
        <svg
            class="pd-portrait__placeholder"
            :class="{ 'is-hidden': src && hasLoaded && !hasFailed }"
            viewBox="0 0 200 250"
            preserveAspectRatio="xMidYMid slice"
            role="img"
            :aria-label="src && !hasFailed ? undefined : `No picture yet for ${name}`"
            :aria-hidden="src && !hasFailed ? 'true' : undefined"
        >
            <defs>
                <linearGradient :id="`${gradientId}-bg`" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" style="stop-color: var(--pd-placeholder-top)" />
                    <stop offset="1" style="stop-color: var(--pd-placeholder-bottom)" />
                </linearGradient>
                <linearGradient :id="`${gradientId}-figure`" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" style="stop-color: var(--pd-placeholder-figure-top)" />
                    <stop offset="1" style="stop-color: var(--pd-placeholder-figure-bottom)" />
                </linearGradient>
                <radialGradient :id="`${gradientId}-light`" cx="0.5" cy="0.32" r="0.6">
                    <stop offset="0" stop-color="#ffffff" stop-opacity="0.45" />
                    <stop offset="1" stop-color="#ffffff" stop-opacity="0" />
                </radialGradient>
            </defs>

            <rect width="200" height="250" :fill="`url(#${gradientId}-bg)`" />
            <rect width="200" height="250" :fill="`url(#${gradientId}-light)`" />

            <!-- Concentric guide rings and a champagne-gold arc -->
            <g fill="none" style="stroke: var(--pd-placeholder-line)" stroke-width="1">
                <circle cx="100" cy="98" r="62" />
                <circle cx="100" cy="98" r="82" opacity="0.6" />
            </g>
            <path
                d="M 38 98 A 62 62 0 0 1 100 36"
                fill="none"
                style="stroke: var(--pd-gold)"
                stroke-width="2"
                stroke-linecap="round"
                opacity="0.85"
            />
            <circle cx="100" cy="36" r="3" style="fill: var(--pd-gold)" />

            <!-- Generic silhouette -->
            <g :fill="`url(#${gradientId}-figure)`">
                <circle cx="100" cy="94" r="34" />
                <path d="M 30 250 C 30 186 62 154 100 154 C 138 154 170 186 170 250 Z" />
            </g>
            <path d="M 88 156 L 100 178 L 112 156" fill="none" style="stroke: var(--pd-gold)" stroke-width="2" opacity="0.7" />
        </svg>

        <img
            v-if="src && !hasFailed"
            :src="src"
            :alt="`Portrait of ${name}`"
            class="pd-portrait__image"
            :class="{ 'is-loaded': hasLoaded }"
            :loading="eager ? 'eager' : 'lazy'"
            decoding="async"
            draggable="false"
            @load="hasLoaded = true"
            @error="hasFailed = true"
        />
    </div>
</template>

<style scoped>
.pd-portrait {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: var(--pd-placeholder-bottom);
}

.pd-portrait__placeholder,
.pd-portrait__image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

.pd-portrait__placeholder {
    transition: opacity 0.4s ease;
}

.pd-portrait__placeholder.is-hidden {
    opacity: 0;
}

.pd-portrait__image {
    object-fit: cover;
    object-position: 50% 22%;
    opacity: 0;
    transition: opacity 0.5s ease;
}

.pd-portrait__image.is-loaded {
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .pd-portrait__placeholder,
    .pd-portrait__image {
        transition: none;
    }
}
</style>
