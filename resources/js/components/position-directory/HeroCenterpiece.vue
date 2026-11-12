<script setup lang="ts">
import { EMBLEM_GEAR, gearProfile, type EmblemShape, type MotionLevel } from '@/lib/positionDirectory';
import type { DirectorySceneHandle } from '@/lib/positionDirectoryScene';
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Offices/divisions for the selected position, drawn as orbiting nodes. */
        nodeCount: number;
        /** Changes whenever the selected position changes, to play a light burst. */
        pulseKey: string | null;
        motionLevel: MotionLevel;
        /** Fill the parent's box instead of being a square sized by its width. */
        fill?: boolean;
        /** Centre object: the faceted crystal or a spoked gear. */
        shape?: EmblemShape;
    }>(),
    { fill: false, shape: 'crystal' },
);

// ─── CSS emblem gear (same outline as the 3D one), as one SVG path ─────────
const SVG_SCALE = 38;
const gearPath = computed(() => {
    const { outline, windows } = gearProfile(EMBLEM_GEAR);
    const toPath = (points: [number, number][]) =>
        `M${points.map(([x, y]) => `${(x * SVG_SCALE).toFixed(2)} ${(y * SVG_SCALE).toFixed(2)}`).join('L')}Z`;
    return [outline, ...windows].map(toPath).join(' ');
});
const gearHubRadius = (EMBLEM_GEAR.hubRadius - 0.04) * SVG_SCALE;
const gradientId = `pd-gear-${useId()}`;

const containerRef = ref<HTMLDivElement | null>(null);
const isReady = ref(false);
const hasFailed = ref(false);

// WebGL only for the full experience; lite mode, phones, low-end devices and
// reduced motion get the lightweight CSS emblem instead (no three.js download).
const usesWebGl = computed(() => props.motionLevel === 'full' && !hasFailed.value);

const emblemNodes = computed(() => Math.max(1, Math.min(24, props.nodeCount)));

let scene: DirectorySceneHandle | null = null;
let visibilityObserver: IntersectionObserver | null = null;
let isUnmounted = false;

async function startScene(): Promise<void> {
    if (!usesWebGl.value || scene || !containerRef.value) {
        return;
    }

    try {
        const { createDirectoryScene } = await import('@/lib/positionDirectoryScene');
        if (isUnmounted || !containerRef.value || !usesWebGl.value) {
            return;
        }
        scene = createDirectoryScene(containerRef.value, { reducedMotion: false, nodeCount: props.nodeCount, shape: props.shape });
        isReady.value = true;

        // Stop rendering while the hero is scrolled out of view.
        visibilityObserver = new IntersectionObserver(([entry]) => scene?.setPaused(!entry.isIntersecting));
        visibilityObserver.observe(containerRef.value);
    } catch {
        // No WebGL (old GPU, disabled acceleration): show the CSS emblem.
        hasFailed.value = true;
    }
}

function stopScene(): void {
    visibilityObserver?.disconnect();
    visibilityObserver = null;
    scene?.dispose();
    scene = null;
    isReady.value = false;
}

onMounted(startScene);

// After render, so the canvas container exists again when WebGL is re-enabled.
watch(usesWebGl, (enabled) => (enabled ? startScene() : stopScene()), { flush: 'post' });

watch(
    () => props.nodeCount,
    (count) => scene?.setNodeCount(count),
);

watch(
    () => props.pulseKey,
    () => scene?.pulse(),
);

// The shape is built into the scene, so a different one needs a fresh scene.
watch(
    () => props.shape,
    () => {
        if (scene) {
            stopScene();
            startScene();
        }
    },
    { flush: 'post' },
);

onBeforeUnmount(() => {
    isUnmounted = true;
    stopScene();
});
</script>

<template>
    <div class="pd-centerpiece" :class="{ 'is-fill': fill }" :data-motion="motionLevel" aria-hidden="true">
        <div v-if="usesWebGl" ref="containerRef" class="pd-centerpiece__canvas" :class="{ 'is-ready': isReady }" />

        <!-- CSS emblem: fallback and calm/still modes -->
        <div v-if="!usesWebGl || !isReady" class="pd-emblem" :class="{ 'is-behind': usesWebGl }">
            <div class="pd-emblem__halo" />
            <div class="pd-emblem__ring pd-emblem__ring--outer" />
            <div class="pd-emblem__ring pd-emblem__ring--middle">
                <span
                    v-for="index in emblemNodes"
                    :key="index"
                    class="pd-emblem__node"
                    :class="{ 'is-gold': index % 5 === 1 }"
                    :style="{ '--node-angle': `${(index / emblemNodes) * 360}deg` }"
                />
            </div>
            <div class="pd-emblem__ring pd-emblem__ring--inner" />
            <div v-if="shape === 'gear'" :key="pulseKey ?? 'none'" class="pd-emblem__gear">
                <svg class="pd-emblem__gear-svg" viewBox="-50 -50 100 100">
                    <defs>
                        <linearGradient :id="`${gradientId}-body`" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#7aa5ff" />
                            <stop offset="0.45" stop-color="#13306f" />
                            <stop offset="1" stop-color="#081740" />
                        </linearGradient>
                        <radialGradient :id="`${gradientId}-hub`" cx="0.35" cy="0.3" r="0.8">
                            <stop offset="0" stop-color="#f6e7bd" />
                            <stop offset="0.55" stop-color="#e6c372" />
                            <stop offset="1" stop-color="#9c7425" />
                        </radialGradient>
                    </defs>
                    <path
                        :d="gearPath"
                        :fill="`url(#${gradientId}-body)`"
                        fill-rule="evenodd"
                        stroke="#e6c372"
                        stroke-width="1"
                        stroke-linejoin="round"
                    />
                    <circle :r="gearHubRadius" :fill="`url(#${gradientId}-hub)`" stroke="#f6e7bd" stroke-width="0.6" />
                    <circle r="7.6" fill="#0a1d52" stroke="#e6c372" stroke-width="0.8" />
                </svg>
            </div>
            <div v-else :key="pulseKey ?? 'none'" class="pd-emblem__gem">
                <div class="pd-emblem__facet" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.pd-centerpiece {
    position: relative;
    width: 100%;
    aspect-ratio: 1;
}

/* Fill the parent's whole box (any shape); the CSS emblem stays a centred circle */
.pd-centerpiece.is-fill {
    height: 100%;
    aspect-ratio: auto;
}

.pd-centerpiece.is-fill .pd-emblem {
    inset: 0;
    width: auto;
    height: 94%;
    max-width: 100%;
    margin: auto;
    aspect-ratio: 1;
}

.pd-centerpiece__canvas {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.9s ease;
}

.pd-centerpiece__canvas.is-ready {
    opacity: 1;
}

.pd-emblem {
    position: absolute;
    inset: 8%;
    display: grid;
    place-items: center;
}

.pd-emblem.is-behind {
    opacity: 0.6;
}

.pd-emblem__halo {
    position: absolute;
    inset: 12%;
    border-radius: 9999px;
    background: radial-gradient(circle, rgba(170, 200, 255, 0.55), rgba(47, 107, 255, 0.18) 45%, transparent 70%);
}

.pd-emblem__ring {
    position: absolute;
    border-radius: 9999px;
    border: 1px solid rgba(230, 195, 114, 0.7);
}

.pd-emblem__ring--outer {
    --tilt: rotateX(62deg) rotateY(14deg);
    inset: 0;
    border-color: rgba(47, 107, 255, 0.55);
    transform: var(--tilt);
    animation: pd-emblem-orbit 38s linear infinite;
}

.pd-emblem__ring--middle {
    --tilt: rotateX(58deg) rotateY(-12deg);
    inset: 10%;
    border-color: rgba(234, 241, 255, 0.45);
    transform: var(--tilt);
    animation: pd-emblem-orbit 30s linear infinite reverse;
}

.pd-emblem__ring--inner {
    --tilt: rotateX(52deg) rotateY(28deg);
    inset: 22%;
    transform: var(--tilt);
    animation: pd-emblem-orbit 24s linear infinite;
}

.pd-emblem__node {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 7px;
    height: 7px;
    margin: -3.5px;
    border-radius: 9999px;
    background: #eaf1ff;
    box-shadow: 0 0 8px rgba(150, 190, 255, 0.9);
    transform: rotate(var(--node-angle)) translateX(calc(var(--emblem-radius, 120px)));
}

.pd-emblem__node.is-gold {
    background: #e6c372;
    box-shadow: 0 0 8px rgba(230, 195, 114, 0.9);
}

.pd-emblem__ring--middle {
    container-type: size;
}

.pd-emblem__ring--middle .pd-emblem__node {
    --emblem-radius: 50cqw;
}

.pd-emblem__gem {
    position: relative;
    width: 34%;
    aspect-ratio: 1;
    clip-path: polygon(50% 0, 93% 25%, 93% 75%, 50% 100%, 7% 75%, 7% 25%);
    background: linear-gradient(150deg, #e6c372 0%, #e6c372 3%, #2f6bff 4%, #13306f 55%, #0a1d52 100%);
    animation: pd-emblem-pulse 1.4s ease-out;
}

.pd-emblem__facet {
    position: absolute;
    inset: 3px;
    clip-path: polygon(50% 0, 93% 25%, 93% 75%, 50% 100%, 7% 75%, 7% 25%);
    background:
        linear-gradient(90deg, transparent 49.5%, rgba(230, 195, 114, 0.7) 50%, transparent 50.5%),
        linear-gradient(30deg, transparent 49.5%, rgba(230, 195, 114, 0.45) 50%, transparent 50.5%),
        linear-gradient(150deg, transparent 49.5%, rgba(230, 195, 114, 0.45) 50%, transparent 50.5%),
        linear-gradient(160deg, rgba(120, 165, 255, 0.85), #13306f 55%, #081740);
}

/* Gear emblem: larger than the gem, turning slowly on its axle */
.pd-emblem__gear {
    position: relative;
    width: 56%;
    aspect-ratio: 1;
    filter: drop-shadow(0 10px 18px rgba(6, 21, 54, 0.55));
    animation: pd-emblem-pulse 1.4s ease-out;
}

.pd-emblem__gear-svg {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
    animation: pd-emblem-turn 40s linear infinite;
}

@keyframes pd-emblem-turn {
    to {
        transform: rotate(-360deg);
    }
}

@keyframes pd-emblem-orbit {
    from {
        transform: var(--tilt) rotateZ(0deg);
    }
    to {
        transform: var(--tilt) rotateZ(360deg);
    }
}

@keyframes pd-emblem-pulse {
    0% {
        transform: scale(0.82);
        filter: brightness(1.6);
    }
    100% {
        transform: scale(1);
        filter: brightness(1);
    }
}

.pd-centerpiece[data-motion='still'] .pd-emblem__ring,
.pd-centerpiece[data-motion='still'] .pd-emblem__gem,
.pd-centerpiece[data-motion='still'] .pd-emblem__gear,
.pd-centerpiece[data-motion='still'] .pd-emblem__gear-svg {
    animation: none;
}
</style>
