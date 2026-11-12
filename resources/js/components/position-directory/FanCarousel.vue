<script setup lang="ts" generic="T">
import type { FanGroup, MotionLevel } from '@/lib/positionDirectory';
import { useResizeObserver } from '@vueuse/core';
import { ChevronLeft, ChevronRight, Hand } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

/**
 * 3D fan carousel: the centre card faces forward, the neighbours fan out to
 * both sides. Drag, swipe, trackpad, arrow keys, arrow buttons and a scrubber
 * all move it. Cards come from the #card slot; an optional #header slot sits
 * above the stage.
 *
 * - `groups` splits the items into consecutive runs (e.g. offices/divisions):
 *   they become scrubber segments and Page Up / Page Down jumps.
 * - `hasPrevious` / `hasNext` say more items exist past either end (another
 *   page): stepping past an end then emits `edge` instead of stopping.
 */
const props = withDefaults(
    defineProps<{
        items: T[];
        itemKey: (item: T) => string | number;
        /** Name read out for the centre card and on the side cards' buttons. */
        itemName: (item: T) => string;
        /** Accessible name of the carousel. */
        label: string;
        motionLevel: MotionLevel;
        /** Stretch to the parent's height (the one-screen layout); the cards size to fit. */
        fill?: boolean;
        groups?: FanGroup[];
        hasPrevious?: boolean;
        hasNext?: boolean;
        /** Added to the counter, e.g. the number of items on earlier pages. */
        counterOffset?: number;
        /** Counter total when it differs from `items.length` (e.g. all pages). */
        counterTotal?: number | null;
        previousLabel?: string;
        nextLabel?: string;
        previousEdgeLabel?: string;
        nextEdgeLabel?: string;
        /** Accessible name of the scrubber. */
        scrubLabel?: string;
    }>(),
    {
        fill: false,
        groups: () => [],
        hasPrevious: false,
        hasNext: false,
        counterOffset: 0,
        counterTotal: null,
        previousLabel: 'Previous employee',
        nextLabel: 'Next employee',
        previousEdgeLabel: 'Previous page',
        nextEdgeLabel: 'Next page',
        scrubLabel: 'Browse employees',
    },
);

/** Index of the item in the centre. */
const activeIndex = defineModel<number>('activeIndex', { required: true });

const emit = defineEmits<{
    open: [item: T];
    /** Stepped past the first (-1) or last (1) item while more exist that way. */
    edge: [direction: -1 | 1];
}>();

defineSlots<{
    header?(props: {
        current: T | null;
        activeIndex: number;
        groupIndex: number;
        indexInGroup: number;
        goToGroup: (groupIndex: number) => void;
        previousGroup: () => void;
    }): unknown;
    card(props: { item: T; index: number; featured: boolean }): unknown;
}>();

const current = computed(() => props.items[activeIndex.value] ?? null);

const groupStarts = computed(() => {
    let start = 0;
    return props.groups.map((group) => {
        const first = start;
        start += group.size;
        return first;
    });
});

/** Which group the centre card belongs to, and its place inside that group. */
const groupPosition = computed(() => {
    let remaining = activeIndex.value;
    for (let index = 0; index < props.groups.length; index++) {
        if (remaining < props.groups[index].size) {
            return { groupIndex: index, indexInGroup: remaining };
        }
        remaining -= props.groups[index].size;
    }
    return { groupIndex: 0, indexInGroup: activeIndex.value };
});

const currentGroup = computed(() => props.groups[groupPosition.value.groupIndex] ?? null);

watch(
    () => props.items,
    (list) => {
        if (activeIndex.value >= list.length) {
            activeIndex.value = 0;
        }
    },
);

// ─── Geometry ──────────────────────────────────────────────────────────────
// The card width (--fan-w) is pure CSS (container query units on the stage's
// viewport), and the horizontal spread is written in multiples of it. Only the
// number of side cards is decided here, from the carousel's own width — which
// never changes its height, so this can't feed back into a resize loop.
const regionRef = ref<HTMLElement | null>(null);
const stageRef = ref<HTMLElement | null>(null);
const carouselWidth = ref(1000);

// Applied on the next frame, so re-rendering the cards never happens inside the
// observer callback (which the browser reports as a "ResizeObserver loop" error).
let widthFrame = 0;
useResizeObserver(regionRef, ([entry]) => {
    const width = Math.round(entry.contentRect.width);
    cancelAnimationFrame(widthFrame);
    widthFrame = requestAnimationFrame(() => {
        carouselWidth.value = width;
    });
});

/** Cards shown on each side of the centre. */
const sideCount = computed(() => (carouselWidth.value < 560 ? 2 : carouselWidth.value < 900 ? 3 : 4));

/** Spread of the first neighbour, as a fraction of the card width (also one drag step). */
const STEP_RATIO = 0.565;

/**
 * Where a card sits in the fan for a given (possibly fractional) offset from
 * the centre: farther cards slide out along a gentle curve, turn to face the
 * centre more steeply, shrink and sink deeper.
 */
function placement(offset: number) {
    const distance = Math.min(Math.abs(offset), sideCount.value + 1);
    const side = Math.sign(offset);

    const spread = side * (0.6 * distance - 0.035 * distance * distance);
    const y = distance * distance * 4;
    const z = -(distance * 120 + distance * distance * 8);
    const rotateY = -side * 75 * (1 - Math.exp(-0.6 * distance));
    const rotateZ = side * distance * 1.2;
    const scale = 1 - distance * 0.06;

    return {
        transform: `translate3d(calc(var(--fan-w) * ${spread.toFixed(4)}), ${y.toFixed(1)}px, ${z.toFixed(1)}px) rotateY(${rotateY.toFixed(2)}deg) rotateZ(${rotateZ.toFixed(2)}deg) scale(${scale.toFixed(3)})`,
        opacity: distance <= sideCount.value ? 1 : Math.max(0, sideCount.value + 1 - distance),
        zIndex: 1000 - Math.round(distance * 100),
        dimming: Math.min(0.42, distance * 0.11),
    };
}

// ─── Dragging / swiping ────────────────────────────────────────────────────
const dragOffset = ref(0);
const isDragging = ref(false);

const slots = computed(() => {
    const center = activeIndex.value + dragOffset.value;
    const reach = sideCount.value + 1;
    const first = Math.max(0, Math.floor(center - reach));
    const last = Math.min(props.items.length - 1, Math.ceil(center + reach));
    const result = [];

    for (let index = first; index <= last; index++) {
        const place = placement(index - center);
        result.push({
            index,
            item: props.items[index],
            isCenter: index === Math.round(center),
            ...place,
            filter: place.dimming > 0 ? `brightness(${(1 - place.dimming).toFixed(2)}) saturate(${(1 - place.dimming * 0.6).toFixed(2)})` : undefined,
        });
    }

    return result;
});

const isAtStart = computed(() => activeIndex.value === 0);
const isAtEnd = computed(() => activeIndex.value >= props.items.length - 1);

const DRAG_THRESHOLD = 6;
let pointerId: number | null = null;
let startX = 0;
let lastX = 0;
let lastTime = 0;
let velocity = 0;
let suppressClick = false;
/** Pixels of drag per card, measured once when a drag starts. */
let stepPx = 150;

function goTo(index: number): void {
    const target = Math.max(0, Math.min(props.items.length - 1, Math.round(index)));
    const hadFocus = regionRef.value?.contains(document.activeElement) ?? false;
    activeIndex.value = target;

    // The old centre card becomes inert; keep keyboard focus on the carousel.
    if (hadFocus) {
        nextTick(() => {
            if (!regionRef.value?.contains(document.activeElement)) {
                regionRef.value?.focus({ preventScroll: true });
            }
        });
    }
}

/** One card left or right; past either end it asks for the previous/next page when there is one. */
function step(direction: -1 | 1): void {
    const target = activeIndex.value + direction;
    if (target < 0 && props.hasPrevious) {
        emit('edge', -1);
    } else if (target > props.items.length - 1 && props.hasNext) {
        emit('edge', 1);
    } else {
        goTo(target);
    }
}

function goToGroup(groupIndex: number): void {
    const start = groupStarts.value[Math.max(0, Math.min(props.groups.length - 1, groupIndex))];
    if (start !== undefined) {
        goTo(start);
    }
}

function previousGroup(): void {
    // First press returns to the start of this group, the next one goes back a group.
    if (groupPosition.value.indexInGroup > 0) {
        goToGroup(groupPosition.value.groupIndex);
    } else {
        goToGroup(groupPosition.value.groupIndex - 1);
    }
}

function onPointerDown(event: PointerEvent): void {
    if ((event.pointerType === 'mouse' && event.button !== 0) || (props.items.length < 2 && !props.hasPrevious && !props.hasNext)) {
        return;
    }
    pointerId = event.pointerId;
    startX = lastX = event.clientX;
    lastTime = event.timeStamp;
    velocity = 0;
    // Layout width of the centre card (transforms don't affect offsetWidth).
    const cardWidth = stageRef.value?.querySelector<HTMLElement>('.pd-fan__slot.is-center')?.offsetWidth;
    stepPx = (cardWidth || 260) * STEP_RATIO;
}

function onPointerMove(event: PointerEvent): void {
    if (event.pointerId !== pointerId) {
        return;
    }

    const dx = event.clientX - startX;
    if (!isDragging.value) {
        if (Math.abs(dx) < DRAG_THRESHOLD) {
            return;
        }
        isDragging.value = true;
        stageRef.value?.setPointerCapture(event.pointerId);
    }

    velocity = (event.clientX - lastX) / Math.max(1, event.timeStamp - lastTime);
    lastX = event.clientX;
    lastTime = event.timeStamp;

    // Follow the pointer, with a little resistance past either end.
    const raw = -dx / stepPx;
    const min = -activeIndex.value;
    const max = props.items.length - 1 - activeIndex.value;
    dragOffset.value = raw < min ? min + (raw - min) * 0.25 : raw > max ? max + (raw - max) * 0.25 : raw;
}

function endDrag(event: PointerEvent, cancelled: boolean): void {
    if (event.pointerId !== pointerId) {
        return;
    }
    pointerId = null;
    if (!isDragging.value) {
        return;
    }

    const fling = !cancelled && Math.abs(velocity) > 0.45 ? -Math.sign(velocity) * 0.6 : 0;
    const target = activeIndex.value + dragOffset.value + fling;

    isDragging.value = false;
    dragOffset.value = 0;

    // Pulled clearly past an end: continue on the previous/next page.
    if (!cancelled && target < -0.5 && props.hasPrevious) {
        emit('edge', -1);
    } else if (!cancelled && target > props.items.length - 0.5 && props.hasNext) {
        emit('edge', 1);
    } else {
        goTo(target);
    }

    // A drag that ends on a card must not also open it.
    suppressClick = true;
    setTimeout(() => (suppressClick = false), 0);
}

function onClickCapture(event: MouseEvent): void {
    if (suppressClick) {
        event.preventDefault();
        event.stopPropagation();
    }
}

// Horizontal trackpad swipes; vertical scrolling is left to the page.
let wheelDistance = 0;
let wheelTimer: ReturnType<typeof setTimeout> | undefined;

function onWheel(event: WheelEvent): void {
    if (Math.abs(event.deltaX) <= Math.abs(event.deltaY)) {
        return;
    }
    event.preventDefault();
    wheelDistance += event.deltaX;
    clearTimeout(wheelTimer);
    wheelTimer = setTimeout(() => (wheelDistance = 0), 220);
    if (Math.abs(wheelDistance) >= 80) {
        step(wheelDistance > 0 ? 1 : -1);
        wheelDistance = 0;
    }
}

function onKeydown(event: KeyboardEvent): void {
    const hasGroups = props.groups.length > 0;
    const keys: Record<string, (() => void) | undefined> = {
        ArrowRight: () => step(1),
        ArrowLeft: () => step(-1),
        Home: () => goTo(0),
        End: () => goTo(props.items.length - 1),
        // Groups: jump between them. No groups: Page Up / Page Down move a whole page.
        PageDown: hasGroups ? () => goToGroup(groupPosition.value.groupIndex + 1) : props.hasNext ? () => emit('edge', 1) : undefined,
        PageUp: hasGroups ? previousGroup : props.hasPrevious ? () => emit('edge', -1) : undefined,
    };

    const action = keys[event.key];
    if (action) {
        event.preventDefault();
        action();
    } else if (event.key === 'Enter' && event.target === event.currentTarget && current.value) {
        event.preventDefault();
        emit('open', current.value);
    }
}

const counterTotal = computed(() => props.counterTotal ?? props.items.length);

const liveText = computed(() => {
    if (!current.value || isDragging.value) {
        return '';
    }
    const position = `${props.itemName(current.value)}, ${props.counterOffset + activeIndex.value + 1} of ${counterTotal.value}.`;
    return currentGroup.value ? `${position} ${currentGroup.value.name}` : position;
});

defineExpose({ goTo, goToGroup, element: regionRef });
</script>

<template>
    <section
        ref="regionRef"
        class="pd-fan"
        :class="{ 'is-fill': fill }"
        :data-motion="motionLevel"
        role="region"
        aria-roledescription="carousel"
        :aria-label="label"
        tabindex="0"
        @keydown="onKeydown"
    >
        <p class="sr-only" aria-live="polite">{{ liveText }}</p>

        <slot
            name="header"
            :current="current"
            :active-index="activeIndex"
            :group-index="groupPosition.groupIndex"
            :index-in-group="groupPosition.indexInGroup"
            :go-to-group="goToGroup"
            :previous-group="previousGroup"
        />

        <!-- Stage (its viewport is the size container the cards measure themselves against) -->
        <div class="pd-fan__viewport">
            <div
                ref="stageRef"
                class="pd-fan__stage"
                :class="{ 'is-dragging': isDragging }"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="endDrag($event, false)"
                @pointercancel="endDrag($event, true)"
                @click.capture="onClickCapture"
                @wheel="onWheel"
            >
                <div class="pd-fan__lights" aria-hidden="true">
                    <div class="pd-fan__spotlight" />
                    <div class="pd-fan__floor" />
                    <svg class="pd-fan__arcs" viewBox="0 0 1000 400" preserveAspectRatio="none">
                        <path d="M -20 250 Q 500 420 1020 250" />
                        <path d="M -20 290 Q 500 470 1020 290" class="is-gold" />
                        <path d="M 60 330 Q 500 480 940 330" />
                        <path v-if="motionLevel === 'full'" d="M -20 290 Q 500 470 1020 290" class="is-pulse" />
                    </svg>
                    <span v-if="motionLevel !== 'still'" class="pd-fan__streak" />
                </div>

                <div class="pd-fan__track">
                    <div
                        v-for="slot in slots"
                        :key="itemKey(slot.item)"
                        class="pd-fan__slot"
                        :class="{ 'is-center': slot.isCenter }"
                        :style="{ transform: slot.transform, opacity: slot.opacity, zIndex: slot.zIndex, filter: slot.filter }"
                    >
                        <span class="pd-fan__backlight" aria-hidden="true" />
                        <div class="pd-fan__card" :inert="!slot.isCenter || undefined">
                            <slot name="card" :item="slot.item" :index="slot.index" :featured="slot.isCenter && !isDragging" />
                        </div>
                        <button
                            v-if="!slot.isCenter"
                            type="button"
                            class="pd-fan__pick"
                            tabindex="-1"
                            :aria-label="`Bring ${itemName(slot.item)} to the centre`"
                            @click="goTo(slot.index)"
                        />
                    </div>
                </div>

                <template v-if="items.length > 1 || hasPrevious || hasNext">
                    <button
                        type="button"
                        class="pd-fan__arrow pd-fan__arrow--prev"
                        :disabled="isAtStart && !hasPrevious"
                        :aria-label="isAtStart && hasPrevious ? previousEdgeLabel : previousLabel"
                        @pointerdown.stop
                        @click="step(-1)"
                    >
                        <ChevronLeft class="h-5 w-5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="pd-fan__arrow pd-fan__arrow--next"
                        :disabled="isAtEnd && !hasNext"
                        :aria-label="isAtEnd && hasNext ? nextEdgeLabel : nextLabel"
                        @pointerdown.stop
                        @click="step(1)"
                    >
                        <ChevronRight class="h-5 w-5" aria-hidden="true" />
                    </button>
                </template>
            </div>
        </div>

        <!-- Position in the list: group segments + scrubber -->
        <footer class="pd-fan__controls">
            <p class="pd-fan__counter">
                <strong>{{ (counterOffset + activeIndex + 1).toLocaleString() }}</strong>
                <span>/ {{ counterTotal.toLocaleString() }}</span>
            </p>

            <div v-if="items.length > 1" class="pd-fan__scrub">
                <div class="pd-fan__segments" aria-hidden="true">
                    <template v-if="groups.length > 0">
                        <span
                            v-for="(group, index) in groups"
                            :key="group.name"
                            class="pd-fan__segment"
                            :class="{ 'is-active': index === groupPosition.groupIndex }"
                            :style="{ flexGrow: group.size }"
                        />
                    </template>
                    <span v-else class="pd-fan__segment" :style="{ flexGrow: 1 }" />
                </div>
                <input
                    type="range"
                    class="pd-fan__range"
                    min="0"
                    :max="items.length - 1"
                    :value="activeIndex"
                    :aria-label="scrubLabel"
                    :aria-valuetext="liveText || undefined"
                    @input="goTo(Number(($event.target as HTMLInputElement).value))"
                />
            </div>

            <p v-if="items.length > 1 || hasPrevious || hasNext" class="pd-fan__hint">
                <Hand class="h-3.5 w-3.5" aria-hidden="true" />
                Drag or swipe the cards, or use the arrow keys
            </p>
        </footer>
    </section>
</template>

<style scoped>
/*
 * Card proportions (overridable per use): the picture's height as a fraction
 * of the card width, and the height of the text below it.
 */
.pd-fan {
    --fan-card-ratio: 1.1;
    --fan-card-text: 110px;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
    border-radius: 1.75rem;
    border: 1px solid var(--pd-border);
    background: var(--pd-section-surface);
    padding: 0.9rem 0 1rem;
    outline: none;
    container-type: inline-size;
    /* Keeps the cards' z-index inside the carousel, below the floating toolbar */
    isolation: isolate;
}

.pd-fan:focus-visible {
    box-shadow: 0 0 0 3px var(--pd-focus-ring);
}

/* ─── Stage ─────────────────────────────────────────────────────────────── */
/*
 * The viewport is a size container: the card width (--fan-w) comes from both
 * its width and its height, so a whole card always fits. In the one-screen
 * layout (.is-fill) it takes all the height left in the column.
 */
.pd-fan__viewport {
    container: fan-viewport / size;
    position: relative;
    flex: none;
    height: clamp(400px, 46vw + 160px, 560px);
}

.pd-fan.is-fill {
    flex: 1 1 0;
    min-height: 0;
}

.pd-fan.is-fill .pd-fan__viewport {
    flex: 1 1 0;
    height: auto;
    min-height: 20rem;
}

/*
 * A card is about W × ratio (picture) + the text height tall. The width leaves
 * the text height + 68px of the height free: room above (the float animation
 * lifts the card ~12px) and below (its floor shadow), so the card is never
 * clipped at the top. --fan-top then centres it vertically.
 */
.pd-fan__stage {
    --fan-free-h: calc(var(--fan-card-text) + 68px);
    --fan-w: clamp(150px, min(28cqw, calc((100cqh - var(--fan-free-h)) / var(--fan-card-ratio))), 340px);
    --fan-top: max(1.75rem, calc((100cqh - var(--fan-w) * var(--fan-card-ratio) - var(--fan-card-text)) / 2));
    position: absolute;
    inset: 0;
    overflow: hidden;
    touch-action: pan-y;
    user-select: none;
    -webkit-user-select: none;
    cursor: grab;
}

@container fan-viewport (max-width: 899px) {
    .pd-fan__stage {
        --fan-w: clamp(150px, min(32cqw, calc((100cqh - var(--fan-free-h)) / var(--fan-card-ratio))), 300px);
    }
}

@container fan-viewport (max-width: 559px) {
    .pd-fan__stage {
        --fan-w: clamp(150px, min(62cqw, calc((100cqh - var(--fan-free-h)) / var(--fan-card-ratio))), 260px);
    }
}

@supports not (width: 1cqw) {
    .pd-fan__stage {
        --fan-w: 240px;
        --fan-top: 1rem;
    }
}

.pd-fan__stage.is-dragging {
    cursor: grabbing;
}

.pd-fan__lights {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.pd-fan__spotlight {
    position: absolute;
    left: 50%;
    top: -8%;
    width: calc(var(--fan-w) * 2.6);
    aspect-ratio: 1;
    transform: translateX(-50%);
    border-radius: 9999px;
    background: radial-gradient(closest-side, var(--pd-tone-0), transparent);
    animation: pd-fan-breathe 7s ease-in-out infinite alternate;
}

.pd-fan__floor {
    position: absolute;
    left: 50%;
    bottom: 4%;
    width: min(92%, calc(var(--fan-w) * 5));
    height: 26%;
    transform: translateX(-50%);
    border-radius: 50%;
    background: radial-gradient(ellipse at center, var(--pd-card-shadow), transparent 70%);
    opacity: 0.45;
}

.pd-fan__arcs {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    fill: none;
}

.pd-fan__arcs path {
    stroke: var(--pd-trace);
    stroke-width: 1;
    vector-effect: non-scaling-stroke;
}

.pd-fan__arcs path.is-gold {
    stroke: var(--pd-trace-node);
}

.pd-fan__arcs path.is-pulse {
    stroke: var(--pd-trace-pulse);
    stroke-width: 2;
    stroke-linecap: round;
    stroke-dasharray: 90 1400;
    stroke-dashoffset: 1490;
    animation: pd-fan-pulse 9s linear infinite;
}

/* Blue light reflection drifting across the stage */
.pd-fan__streak {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 30%;
    background: linear-gradient(100deg, transparent, var(--pd-trace), transparent);
    transform: translateX(-120%) skewX(-18deg);
    animation: pd-fan-streak 11s ease-in-out infinite;
}

.pd-fan__track {
    position: absolute;
    inset: 0;
    perspective: 1400px;
    perspective-origin: 50% 30%;
}

.pd-fan__slot {
    position: absolute;
    top: var(--fan-top);
    left: 50%;
    width: var(--fan-w);
    margin-left: calc(var(--fan-w) / -2);
    transform-origin: 50% 50%;
    /* z-index follows the same curve, so incoming and outgoing cards swap layers as they pass */
    transition:
        transform 0.75s cubic-bezier(0.22, 0.8, 0.24, 1),
        opacity 0.5s ease,
        filter 0.5s ease,
        z-index 0.75s cubic-bezier(0.22, 0.8, 0.24, 1);
}

.pd-fan__stage.is-dragging .pd-fan__slot {
    transition: none;
}

/* Soft light behind the centre card */
.pd-fan__backlight {
    position: absolute;
    inset: -12% -18%;
    z-index: -1;
    border-radius: 50%;
    background: radial-gradient(closest-side, var(--pd-tone-0), transparent);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.6s ease;
}

.pd-fan__slot.is-center .pd-fan__backlight {
    opacity: 1;
}

/* Side cards: one click brings them to the centre */
.pd-fan__pick {
    position: absolute;
    inset: 0;
    z-index: 10;
    border-radius: 1.25rem;
    cursor: pointer;
}

.pd-fan__arrow {
    position: absolute;
    top: calc(var(--fan-top) + var(--fan-w) * var(--fan-card-ratio) / 2);
    z-index: 1100;
    display: inline-flex;
    width: 2.75rem;
    height: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid var(--pd-border);
    background: color-mix(in srgb, var(--pd-surface-strong) 88%, transparent);
    color: var(--pd-ink);
    box-shadow: 0 10px 25px -12px var(--pd-card-shadow);
    transform: translateY(-50%);
    transition:
        border-color 0.2s ease,
        opacity 0.2s ease,
        background-color 0.2s ease;
}

.pd-fan__arrow:hover:not(:disabled) {
    border-color: var(--pd-gold);
}

.pd-fan__arrow:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: 2px;
}

.pd-fan__arrow:disabled {
    cursor: not-allowed;
    opacity: 0.35;
}

.pd-fan__arrow--prev {
    left: 0.75rem;
}

.pd-fan__arrow--next {
    right: 0.75rem;
}

@media (min-width: 640px) {
    .pd-fan__arrow--prev {
        left: 1.5rem;
    }

    .pd-fan__arrow--next {
        right: 1.5rem;
    }
}

/* ─── Controls ──────────────────────────────────────────────────────────── */
.pd-fan__controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.5rem 1rem;
    padding: 0 1.25rem;
}

.pd-fan__counter {
    font-variant-numeric: tabular-nums;
    color: var(--pd-ink-soft);
}

.pd-fan__counter strong {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--pd-ink);
}

.pd-fan__scrub {
    position: relative;
    flex: 1 1 14rem;
    max-width: 34rem;
    height: 1.5rem;
}

.pd-fan__segments {
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    display: flex;
    gap: 1px;
    height: 6px;
    transform: translateY(-50%);
    overflow: hidden;
    border-radius: 9999px;
}

.pd-fan__segment {
    min-width: 1px;
    flex-basis: 0;
    background: var(--pd-chip);
    transition: background-color 0.3s ease;
}

.pd-fan__segment.is-active {
    background: linear-gradient(90deg, var(--pd-gold), var(--pd-royal));
}

.pd-fan__range {
    position: absolute;
    inset: 0;
    width: 100%;
    margin: 0;
    background: transparent;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
}

.pd-fan__range::-webkit-slider-runnable-track {
    height: 100%;
    background: transparent;
}

.pd-fan__range::-moz-range-track {
    background: transparent;
}

.pd-fan__range::-webkit-slider-thumb {
    -webkit-appearance: none;
    margin-top: calc((1.5rem - 1rem) / 2);
    width: 1rem;
    height: 1rem;
    border-radius: 9999px;
    border: 2px solid var(--pd-gold);
    background: var(--pd-surface-strong);
    box-shadow: 0 2px 8px var(--pd-card-shadow);
}

.pd-fan__range::-moz-range-thumb {
    width: 0.8rem;
    height: 0.8rem;
    border-radius: 9999px;
    border: 2px solid var(--pd-gold);
    background: var(--pd-surface-strong);
    box-shadow: 0 2px 8px var(--pd-card-shadow);
}

.pd-fan__range:focus-visible {
    outline: 2px solid var(--pd-gold);
    outline-offset: 4px;
    border-radius: 9999px;
}

.pd-fan__hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: var(--pd-ink-soft);
}

/* ─── Keyframes & motion levels ─────────────────────────────────────────── */
@keyframes pd-fan-breathe {
    to {
        opacity: 0.6;
        transform: translateX(-50%) scale(1.06);
    }
}

@keyframes pd-fan-pulse {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes pd-fan-streak {
    0%,
    15% {
        transform: translateX(-120%) skewX(-18deg);
    }
    85%,
    100% {
        transform: translateX(420%) skewX(-18deg);
    }
}

.pd-fan[data-motion='calm'] .pd-fan__slot {
    transition-duration: 0.45s, 0.3s, 0.3s, 0.45s;
}

.pd-fan[data-motion='calm'] .pd-fan__spotlight {
    animation: none;
}

.pd-fan[data-motion='still'] .pd-fan__slot,
.pd-fan[data-motion='still'] .pd-fan__backlight {
    transition: none;
}

.pd-fan[data-motion='still'] .pd-fan__spotlight {
    animation: none;
}
</style>
