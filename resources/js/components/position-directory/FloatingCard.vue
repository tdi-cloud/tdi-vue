<script setup lang="ts">
import { observeVisibility, seededUnit, type MotionLevel } from '@/lib/positionDirectory';
import { Rotate3d, Undo2 } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * The 3D card shell shared by the Position Directory and the Employees page:
 * a seeded idle float, mouse tilt with a glare that follows the cursor, an
 * animated gold edge light, a featured state for the centre of the fan, and a
 * "Details" flip to a back face. Off-screen cards pause through the shared
 * visibility observer.
 *
 * Slots: #front (picture and text), #back (the details area between the back
 * header and the actions) and #actions (buttons on the back face). The card
 * anatomy classes (pd-card__media, __body, __name, __details, __action, …) are
 * styled here for slot content too.
 */
const props = defineProps<{
    /** Stable number per card (a database id or a hashed EMPCODE) for its own float timing. */
    seed: number;
    /** Position in the rendered list; only used to stagger the entrance. */
    index: number;
    motionLevel: MotionLevel;
    /** The card in the centre of the fan carousel: gets a soft glowing edge. */
    featured?: boolean;
    /** Accessible name of the button covering the front face. */
    openLabel: string;
    /** Accessible name of the "Details" (flip) button. */
    flipLabel: string;
    backEyebrow: string;
    backTitle: string;
}>();

const emit = defineEmits<{ open: [] }>();

const slotRef = ref<HTMLElement | null>(null);
const tiltRef = ref<HTMLElement | null>(null);
const flipButtonRef = ref<HTMLButtonElement | null>(null);
const backRef = ref<HTMLElement | null>(null);
const isVisible = ref(true);
const isHovered = ref(false);
const isFlipped = ref(false);

/**
 * Each card gets its own (stable) timing, angle and depth so the gallery
 * drifts naturally instead of moving in lockstep.
 */
const cardStyle = computed(() => {
    const seed = props.seed;
    const duration = 8 + seededUnit(seed, 1) * 5;
    const direction = seededUnit(seed, 4) > 0.5 ? 1 : -1;

    return {
        '--float-duration': `${duration.toFixed(2)}s`,
        '--float-delay': `-${(seededUnit(seed, 2) * duration).toFixed(2)}s`,
        '--rot-y': `${((3 + seededUnit(seed, 3) * 4) * direction).toFixed(2)}deg`,
        '--rot-x': `${(1.5 + seededUnit(seed, 5) * 2).toFixed(2)}deg`,
        '--lift': `${(6 + seededUnit(seed, 6) * 6).toFixed(1)}px`,
        '--enter-delay': `${Math.min(props.index, 11) * 55}ms`,
    };
});

// ─── Mouse tilt (CSS variables written directly, no re-render per move) ─────
let tiltFrame = 0;
let pointer: { x: number; y: number } | null = null;

function applyTilt(): void {
    tiltFrame = 0;
    const element = tiltRef.value;
    if (!element || !pointer) {
        return;
    }
    element.style.setProperty('--tilt-x', `${((0.5 - pointer.y) * 14).toFixed(2)}deg`);
    element.style.setProperty('--tilt-y', `${((pointer.x - 0.5) * 16).toFixed(2)}deg`);
    element.style.setProperty('--glare-x', `${(pointer.x * 100).toFixed(1)}%`);
    element.style.setProperty('--glare-y', `${(pointer.y * 100).toFixed(1)}%`);
}

function onPointerMove(event: PointerEvent): void {
    if (event.pointerType !== 'mouse' || props.motionLevel === 'still' || !tiltRef.value) {
        return;
    }
    const rect = tiltRef.value.getBoundingClientRect();
    pointer = { x: (event.clientX - rect.left) / rect.width, y: (event.clientY - rect.top) / rect.height };
    isHovered.value = true;
    if (!tiltFrame) {
        tiltFrame = requestAnimationFrame(applyTilt);
    }
}

function onPointerLeave(): void {
    if (tiltFrame) {
        cancelAnimationFrame(tiltFrame);
        tiltFrame = 0;
    }
    pointer = null;
    isHovered.value = false;
    tiltRef.value?.style.removeProperty('--tilt-x');
    tiltRef.value?.style.removeProperty('--tilt-y');
}

// ─── Flip (keyboard focus follows the visible face) ────────────────────────
function setFlipped(flipped: boolean): void {
    const hadFocus = slotRef.value?.contains(document.activeElement) ?? false;
    isFlipped.value = flipped;

    if (hadFocus) {
        // The face that was just hidden becomes inert, so focus would be lost.
        nextTick(() => {
            const target = flipped ? backRef.value?.querySelector<HTMLElement>('button, [href]') : flipButtonRef.value;
            target?.focus({ preventScroll: true });
        });
    }
}

let stopObserving: (() => void) | null = null;

onMounted(() => {
    if (slotRef.value) {
        stopObserving = observeVisibility(slotRef.value, (visible) => (isVisible.value = visible));
    }
});

onBeforeUnmount(() => {
    stopObserving?.();
    if (tiltFrame) {
        cancelAnimationFrame(tiltFrame);
    }
});
</script>

<template>
    <div
        ref="slotRef"
        class="pd-card"
        :class="{ 'is-hovered': isHovered, 'is-flipped': isFlipped, 'is-offscreen': !isVisible, 'is-featured': featured }"
        :data-motion="motionLevel"
        :style="cardStyle"
    >
        <div class="pd-card__shadow" aria-hidden="true" />

        <div class="pd-card__float">
            <div ref="tiltRef" class="pd-card__tilt" @pointermove="onPointerMove" @pointerleave="onPointerLeave">
                <div class="pd-card__flipper">
                    <!-- Front -->
                    <article class="pd-face pd-face--front" :inert="isFlipped || undefined" :aria-hidden="isFlipped || undefined">
                        <slot name="front" />

                        <span class="pd-card__sheen" aria-hidden="true" />
                        <span class="pd-card__glare" aria-hidden="true" />

                        <!-- Whole card opens the profile; the details button sits above it -->
                        <button type="button" class="pd-card__open" :aria-label="openLabel" @click="emit('open')" />
                        <button
                            ref="flipButtonRef"
                            type="button"
                            class="pd-card__flip"
                            :aria-label="flipLabel"
                            :aria-expanded="isFlipped"
                            @click="setFlipped(true)"
                        >
                            <Rotate3d class="h-3.5 w-3.5" aria-hidden="true" />
                            <span class="pd-card__flip-text">Details</span>
                        </button>
                    </article>

                    <!-- Back -->
                    <article ref="backRef" class="pd-face pd-face--back" :inert="!isFlipped || undefined" :aria-hidden="!isFlipped || undefined">
                        <header class="pd-card__back-header">
                            <p class="pd-card__eyebrow">{{ backEyebrow }}</p>
                            <h3 class="pd-card__back-name">{{ backTitle }}</h3>
                        </header>

                        <slot name="back" />

                        <footer class="pd-card__back-actions">
                            <slot name="actions" />
                            <button
                                type="button"
                                class="pd-card__action pd-card__action--icon"
                                aria-label="Back to the front of the card"
                                @click="setFlipped(false)"
                            >
                                <Undo2 class="h-3.5 w-3.5" aria-hidden="true" />
                            </button>
                        </footer>
                    </article>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@property --pd-border-angle {
    syntax: '<angle>';
    inherits: false;
    initial-value: 0deg;
}

.pd-card {
    position: relative;
    perspective: 1100px;
    animation: pd-card-enter 0.75s cubic-bezier(0.2, 0.8, 0.2, 1) var(--enter-delay) both;
}

/* Soft floor shadow that breathes with the card's float */
.pd-card__shadow {
    position: absolute;
    left: 12%;
    right: 12%;
    bottom: -14px;
    height: 26px;
    border-radius: 50%;
    background: radial-gradient(ellipse at center, var(--pd-card-shadow), transparent 70%);
    animation: pd-card-shadow var(--float-duration) ease-in-out var(--float-delay) infinite;
}

.pd-card__float {
    transform-style: preserve-3d;
    animation: pd-card-float var(--float-duration) ease-in-out var(--float-delay) infinite;
}

.pd-card__tilt {
    --tilt-x: 0deg;
    --tilt-y: 0deg;
    --glare-x: 50%;
    --glare-y: 30%;
    transform-style: preserve-3d;
    transform: rotateX(var(--tilt-x)) rotateY(var(--tilt-y)) translateZ(0);
    transition: transform 0.55s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.pd-card.is-hovered .pd-card__tilt {
    transform: rotateX(var(--tilt-x)) rotateY(var(--tilt-y)) translateZ(28px);
    transition: transform 0.12s ease-out;
}

.pd-card__flipper {
    position: relative;
    transform-style: preserve-3d;
    transition: transform 0.8s cubic-bezier(0.3, 0.7, 0.2, 1);
}

.pd-card.is-flipped .pd-card__flipper {
    transform: rotateY(180deg);
}

/* ─── Faces ─────────────────────────────────────────────────────────────── */
.pd-face {
    position: relative;
    overflow: hidden;
    border-radius: 1.25rem;
    border: 1px solid var(--pd-card-border);
    background: var(--pd-card-surface);
    color: var(--pd-ink);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.5) inset,
        0 18px 40px -22px var(--pd-card-shadow);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

/* Animated gold/blue edge light, only while hovered */
.pd-face::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 4;
    border-radius: inherit;
    padding: 1px;
    background: conic-gradient(from var(--pd-border-angle), transparent 0 55%, var(--pd-gold) 70%, var(--pd-azure) 82%, transparent 95%);
    -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.35s ease;
}

.pd-card.is-hovered .pd-face::after,
.pd-card:focus-within .pd-face::after {
    opacity: 1;
    animation: pd-border-spin 3.6s linear infinite;
}

/* Centre card of the fan: a slow, quieter edge light and a soft blue halo */
.pd-card.is-featured .pd-face {
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.5) inset,
        0 18px 40px -22px var(--pd-card-shadow),
        0 0 0 1px var(--pd-gold-halo),
        0 0 42px -10px var(--pd-azure);
}

.pd-card.is-featured:not(.is-hovered):not(:focus-within) .pd-face::after {
    opacity: 0.65;
    animation: pd-border-spin 7s linear infinite;
}

.pd-face--back {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    transform: rotateY(180deg);
    background: var(--pd-card-back);
}

/* ─── Front anatomy (slot content) ──────────────────────────────────────── */
:slotted(.pd-card__media) {
    position: relative;
    aspect-ratio: 10 / 11;
    overflow: hidden;
}

:slotted(.pd-card__media-shade) {
    position: absolute;
    inset: auto 0 0 0;
    height: 40%;
    background: linear-gradient(to top, var(--pd-card-surface), transparent);
    pointer-events: none;
}

:slotted(.pd-card__order) {
    position: absolute;
    top: 0.65rem;
    left: 0.65rem;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(10, 29, 82, 0.55);
    /* Never runs into the Details button on the right */
    max-width: calc(100% - 4.5rem);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 0.1rem 0.5rem;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    font-variant-numeric: tabular-nums;
    color: #f5e6bf;
}

:slotted(.pd-card__body) {
    position: relative;
    z-index: 1;
    margin-top: -1.75rem;
    padding: 0 1rem 1.1rem;
}

:slotted(.pd-card__name) {
    font-size: 0.98rem;
    font-weight: 700;
    line-height: 1.25;
    text-wrap: balance;
    color: var(--pd-ink);
}

:slotted(.pd-card__position) {
    margin-top: 0.3rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pd-gold-ink);
}

:slotted(.pd-card__office) {
    margin-top: 0.55rem;
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    font-size: 0.75rem;
    line-height: 1.3;
    color: var(--pd-ink-soft);
}

:slotted(.pd-card__office span) {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Light that slides across as the card turns */
.pd-card__sheen {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    background: linear-gradient(105deg, transparent 35%, var(--pd-sheen) 48%, transparent 60%);
    background-size: 250% 100%;
    background-position: 120% 0;
    animation: pd-card-sheen var(--float-duration) ease-in-out var(--float-delay) infinite;
}

/* Hover highlight that follows the cursor */
.pd-card__glare {
    position: absolute;
    inset: 0;
    z-index: 3;
    pointer-events: none;
    background: radial-gradient(circle at var(--glare-x) var(--glare-y), rgba(255, 255, 255, 0.35), transparent 45%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.pd-card.is-hovered .pd-card__glare {
    opacity: 1;
}

.pd-card__open {
    position: absolute;
    inset: 0;
    z-index: 5;
    border-radius: inherit;
    cursor: pointer;
}

.pd-card__open:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: -4px;
}

.pd-card__flip {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    z-index: 6;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.4);
    background: rgba(10, 29, 82, 0.55);
    padding: 0.3rem 0.45rem;
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 700;
    transition:
        background-color 0.2s ease,
        padding 0.2s ease;
}

.pd-card__flip-text {
    max-width: 0;
    overflow: hidden;
    white-space: nowrap;
    transition: max-width 0.25s ease;
}

.pd-card.is-hovered .pd-card__flip-text,
.pd-card__flip:focus-visible .pd-card__flip-text {
    max-width: 4rem;
}

.pd-card__flip:hover,
.pd-card__flip:focus-visible {
    background: rgba(29, 78, 216, 0.85);
    outline: none;
    box-shadow: 0 0 0 2px var(--pd-gold);
}

/* ─── Back face ─────────────────────────────────────────────────────────── */
.pd-card__back-header {
    border-bottom: 1px solid var(--pd-border);
    padding: 1rem 1rem 0.75rem;
}

.pd-card__eyebrow {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--pd-gold-ink);
}

.pd-card__back-name {
    margin-top: 0.2rem;
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.25;
}

:slotted(.pd-card__details) {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 1rem;
}

:slotted(.pd-card__detail) {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    border-bottom: 1px dashed var(--pd-border);
    padding: 0.4rem 0;
    font-size: 0.75rem;
}

:slotted(.pd-card__detail dt) {
    color: var(--pd-ink-soft);
}

:slotted(.pd-card__detail dd) {
    text-align: right;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.pd-card__back-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.75rem 1rem 1rem;
}

.pd-card__action,
:slotted(.pd-card__action) {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 0.6rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-surface-strong);
    padding: 0.4rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--pd-ink);
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease;
}

.pd-card__action:hover,
:slotted(.pd-card__action:hover) {
    border-color: var(--pd-royal);
}

.pd-card__action:focus-visible,
:slotted(.pd-card__action:focus-visible) {
    outline: 2px solid var(--pd-gold);
    outline-offset: 2px;
}

:slotted(.pd-card__action--primary) {
    border-color: transparent;
    background: linear-gradient(120deg, var(--pd-navy), var(--pd-royal));
    color: #ffffff;
}

.pd-card__action--icon {
    margin-left: auto;
    padding: 0.4rem;
}

/* ─── Keyframes ─────────────────────────────────────────────────────────── */
@keyframes pd-card-enter {
    from {
        opacity: 0;
        transform: translate3d(0, 28px, 0) rotateX(16deg) scale(0.96);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes pd-card-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0) rotateX(var(--rot-x)) rotateY(calc(var(--rot-y) * -1));
    }
    50% {
        transform: translate3d(0, calc(var(--lift) * -1), 14px) rotateX(calc(var(--rot-x) * -0.4)) rotateY(var(--rot-y));
    }
}

@keyframes pd-card-shadow {
    0%,
    100% {
        transform: scale(1);
        opacity: 0.85;
    }
    50% {
        transform: scale(0.86);
        opacity: 0.5;
    }
}

@keyframes pd-card-sheen {
    0%,
    100% {
        background-position: 130% 0;
    }
    50% {
        background-position: -30% 0;
    }
}

@keyframes pd-border-spin {
    to {
        --pd-border-angle: 360deg;
    }
}

/* ─── Motion levels and off-screen pausing ──────────────────────────────── */
.pd-card.is-offscreen .pd-card__float,
.pd-card.is-offscreen .pd-card__shadow,
.pd-card.is-offscreen .pd-card__sheen,
.pd-card.is-flipped .pd-card__float,
.pd-card.is-flipped .pd-card__shadow,
.pd-card.is-hovered .pd-card__float,
.pd-card.is-hovered .pd-card__shadow {
    animation-play-state: paused;
}

.pd-card[data-motion='calm'] .pd-card__float,
.pd-card[data-motion='calm'] .pd-card__shadow,
.pd-card[data-motion='calm'] .pd-card__sheen {
    animation: none;
}

.pd-card[data-motion='still'],
.pd-card[data-motion='still'] * {
    animation: none !important;
}

.pd-card[data-motion='still'] .pd-card__tilt,
.pd-card[data-motion='still'] .pd-card__flipper {
    transform: none !important;
    transition: none;
}

.pd-card[data-motion='still'] .pd-face--back {
    transform: none;
    opacity: 0;
}

.pd-card[data-motion='still'].is-flipped .pd-face--back {
    opacity: 1;
}

.pd-card[data-motion='still'].is-flipped .pd-face--front {
    visibility: hidden;
}
</style>
