<script lang="ts">
export interface RankedRegion {
    region: string;
    rate: number;
    submitted: number;
    total: number;
}
</script>

<script setup lang="ts">
import TrophyArenaCanvas from '@/components/TrophyArenaCanvas.vue';
import { CalendarDays, ChevronRight, Crown, ListOrdered, Trophy, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    regions: RankedRegion[];
    year: string;
    /** True while another dialog sits on top — pauses rendering, Escape, and the focus trap. */
    paused?: boolean;
}>();

const emit = defineEmits<{
    close: [];
    selectRegion: [region: string];
}>();

const dialogRef = ref<HTMLElement | null>(null);
const closeButtonRef = ref<HTMLButtonElement | null>(null);
const prefersReducedMotion = ref(false);

// ─── Rankings ────────────────────────────────────────────────────────────────

type StatusTone = 'excellent' | 'good' | 'moderate' | 'low';

function statusFor(rate: number): { label: string; tone: StatusTone } {
    if (rate >= 90) {
        return { label: 'Excellent', tone: 'excellent' };
    }
    if (rate >= 80) {
        return { label: 'Good', tone: 'good' };
    }
    if (rate >= 60) {
        return { label: 'Moderate', tone: 'moderate' };
    }
    return { label: 'Needs Attention', tone: 'low' };
}

const STATUS_TEXT: Record<StatusTone, string> = {
    excellent: 'text-emerald-700 dark:text-emerald-300',
    good: 'text-cyan-700 dark:text-cyan-300',
    moderate: 'text-amber-700 dark:text-amber-300',
    low: 'text-rose-700 dark:text-rose-300',
};

// Podium order is #1, #2, #3 in the DOM (for screen readers); CSS grid places
// #2 left, #1 center, #3 right.
const podium = computed(() =>
    [1, 2, 3].map((rank) => ({
        rank,
        entry: props.regions[rank - 1] ?? null,
    })),
);

const remainingRegions = computed(() =>
    props.regions.slice(3).map((entry, index) => ({
        ...entry,
        rank: index + 4,
        status: statusFor(entry.rate),
    })),
);

const yearLabel = computed(() => (!props.year || props.year === 'ALL' ? 'All years' : props.year));

// ─── Counter animation ───────────────────────────────────────────────────────

const PODIUM_COUNTER_DELAYS: Record<number, number> = { 1: 1.0, 2: 1.2, 3: 1.35 };
const ROW_BASE_DELAY = 1.45;
const ROW_STAGGER = 0.07;
const COUNTER_DURATION = 1.1;

const elapsedSeconds = ref(0);
let counterFrame = 0;

function rowDelay(index: number): number {
    return ROW_BASE_DELAY + Math.min(index, 12) * ROW_STAGGER;
}

function progressAt(delay: number): number {
    const linear = Math.min(1, Math.max(0, (elapsedSeconds.value - delay) / COUNTER_DURATION));
    return 1 - Math.pow(1 - linear, 3);
}

function displayRate(rate: number, delay: number): string {
    return (rate * progressAt(delay)).toFixed(1);
}

function startCounters(): void {
    stopCounters();
    if (prefersReducedMotion.value) {
        elapsedSeconds.value = Number.POSITIVE_INFINITY;
        return;
    }
    elapsedSeconds.value = 0;
    const startedAt = performance.now();
    const finishAt = rowDelay(12) + COUNTER_DURATION + 0.1;
    const step = (now: number) => {
        elapsedSeconds.value = (now - startedAt) / 1000;
        counterFrame = elapsedSeconds.value < finishAt ? requestAnimationFrame(step) : 0;
    };
    counterFrame = requestAnimationFrame(step);
}

function stopCounters(): void {
    if (counterFrame) {
        cancelAnimationFrame(counterFrame);
        counterFrame = 0;
    }
}

// ─── Open / close lifecycle ──────────────────────────────────────────────────

let previouslyFocused: HTMLElement | null = null;
let previousBodyOverflow = '';

const FOCUSABLE = 'button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

function onKeydown(event: KeyboardEvent): void {
    if (props.paused) {
        return;
    }
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
        return;
    }
    if (event.key !== 'Tab' || !dialogRef.value) {
        return;
    }
    const focusable = Array.from(dialogRef.value.querySelectorAll<HTMLElement>(FOCUSABLE));
    if (!focusable.length) {
        return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;
    if (event.shiftKey && (active === first || !dialogRef.value.contains(active))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
}

function activate(): void {
    prefersReducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKeydown);
    startCounters();
    nextTick(() => closeButtonRef.value?.focus({ preventScroll: true }));
}

function deactivate(): void {
    stopCounters();
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = previousBodyOverflow;
    previouslyFocused?.focus({ preventScroll: true });
    previouslyFocused = null;
}

watch(
    () => props.open,
    (isOpen, wasOpen) => {
        if (isOpen) {
            activate();
        } else if (wasOpen) {
            deactivate();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (props.open) {
        deactivate();
    }
});

const transitionDuration = computed(() => (prefersReducedMotion.value ? 0 : { enter: 700, leave: 280 }));
</script>

<template>
    <Teleport to="body">
        <Transition name="rr-modal" :duration="transitionDuration">
            <div v-if="open" class="rr-root fixed inset-0 z-[45] flex items-center justify-center p-2 sm:p-4">
                <div class="rr-backdrop absolute inset-0" aria-hidden="true" @click="emit('close')" />

                <div
                    ref="dialogRef"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="rr-title"
                    aria-describedby="rr-description"
                    class="rr-panel relative flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl"
                >
                    <!-- Decorative layers -->
                    <div class="rr-panel-grid pointer-events-none absolute inset-0" aria-hidden="true" />
                    <div class="rr-sweep pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true" />

                    <!-- Header -->
                    <header class="relative flex shrink-0 items-start gap-3 px-4 pb-2 pt-4 sm:items-center sm:gap-4 sm:px-6 sm:pt-5">
                        <div
                            class="rr-header-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-full sm:h-12 sm:w-12"
                            aria-hidden="true"
                        >
                            <Trophy class="h-5 w-5 sm:h-6 sm:w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="rr-kicker text-[10px] font-bold uppercase tracking-[0.18em]">TDI Monitoring System</p>
                            <h2 id="rr-title" class="rr-title text-lg font-extrabold uppercase leading-tight tracking-wide sm:text-2xl">
                                Regional Compliance Ranking
                            </h2>
                            <p id="rr-description" class="rr-kicker mt-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] sm:text-[11px]">
                                TREAP compliance <span aria-hidden="true">•</span> Live filtered data
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="rr-chip hidden items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold sm:inline-flex">
                                <CalendarDays class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ yearLabel }}
                            </span>
                            <button
                                ref="closeButtonRef"
                                type="button"
                                class="rr-close flex h-9 w-9 items-center justify-center rounded-full"
                                aria-label="Close regional compliance ranking"
                                @click="emit('close')"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </header>

                    <div class="relative flex min-h-0 flex-1 flex-col overflow-y-auto">
                        <p v-if="!regions.length" class="rr-muted px-6 py-16 text-center text-sm">No regional data for the current filters.</p>

                        <template v-else>
                            <!-- Podium arena -->
                            <section class="rr-arena relative shrink-0" aria-label="Top three regions">
                                <div class="rr-floor-grid pointer-events-none absolute inset-x-0 bottom-0" aria-hidden="true" />
                                <div class="rr-spotlight pointer-events-none absolute inset-0" aria-hidden="true" />

                                <ol class="relative grid grid-cols-[1fr_1.35fr_1fr] items-end gap-2 px-3 pt-2 sm:gap-6 sm:px-10">
                                    <li
                                        v-for="slot in podium"
                                        :key="slot.rank"
                                        class="rr-slot row-start-1 flex min-w-0 flex-col items-center"
                                        :class="[`rr-slot-${slot.rank}`, { 'rr-slot-empty': !slot.entry }]"
                                    >
                                        <!-- Award -->
                                        <div v-if="slot.rank === 1" class="rr-trophy-stage relative w-[150%] max-w-none">
                                            <TrophyArenaCanvas :reduced-motion="prefersReducedMotion" :paused="paused" />
                                        </div>
                                        <div v-else class="rr-medal-stage flex items-end justify-center" aria-hidden="true">
                                            <div class="rr-medal" :class="slot.rank === 2 ? 'rr-medal-silver' : 'rr-medal-bronze'">
                                                <div class="rr-ribbon" />
                                                <div class="rr-medal-enter">
                                                    <div class="rr-coin">
                                                        <span
                                                            v-for="layer in 8"
                                                            :key="layer"
                                                            class="rr-coin-edge"
                                                            :style="{ transform: `translateZ(${(layer - 4.5) * 1.15}px)` }"
                                                        />
                                                        <div class="rr-coin-face rr-coin-front">
                                                            <span class="rr-coin-number">{{ slot.rank }}</span>
                                                        </div>
                                                        <div class="rr-coin-face rr-coin-back">
                                                            <span class="rr-coin-number">{{ slot.rank }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Podium block -->
                                        <button
                                            type="button"
                                            class="rr-podium relative w-full"
                                            :disabled="!slot.entry"
                                            :aria-label="
                                                slot.entry
                                                    ? `Rank ${slot.rank}: ${slot.entry.region}, ${slot.entry.rate}% compliance. View submitted employees.`
                                                    : `Rank ${slot.rank}: no region`
                                            "
                                            @click="slot.entry && emit('selectRegion', slot.entry.region)"
                                        >
                                            <span class="rr-podium-top" aria-hidden="true" />
                                            <span class="rr-podium-front flex flex-col items-center justify-center gap-0.5 px-1">
                                                <span class="rr-plate flex flex-col items-center rounded-xl px-2 py-1.5 sm:px-4 sm:py-2">
                                                    <Crown v-if="slot.rank === 1" class="rr-crown h-4 w-4 sm:h-5 sm:w-5" aria-hidden="true" />
                                                    <span class="rr-region max-w-full truncate text-xs font-bold uppercase sm:text-base">
                                                        {{ slot.entry?.region ?? '—' }}
                                                    </span>
                                                    <span
                                                        class="rr-rate font-extrabold tabular-nums leading-none"
                                                        :class="slot.rank === 1 ? 'text-xl sm:text-4xl' : 'text-lg sm:text-3xl'"
                                                    >
                                                        <template v-if="slot.entry">
                                                            {{ displayRate(slot.entry.rate, PODIUM_COUNTER_DELAYS[slot.rank]) }}%
                                                        </template>
                                                        <template v-else>—</template>
                                                    </span>
                                                    <span class="rr-muted mt-0.5 text-[10px] font-semibold sm:text-xs">Rank #{{ slot.rank }}</span>
                                                </span>
                                                <span v-if="slot.entry" class="rr-muted hidden text-[10px] tabular-nums sm:block">
                                                    {{ slot.entry.submitted.toLocaleString() }} / {{ slot.entry.total.toLocaleString() }} submitted
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                </ol>
                            </section>

                            <!-- Remaining regions -->
                            <section
                                class="rr-board relative mx-3 mb-3 mt-4 flex flex-col rounded-2xl sm:mx-6 sm:mb-6"
                                aria-labelledby="rr-board-title"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2 px-4 pb-2 pt-3">
                                    <h3 id="rr-board-title" class="rr-title text-sm font-bold uppercase tracking-wider">Regional Status</h3>
                                    <p class="rr-kicker flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide">
                                        <ListOrdered class="h-3.5 w-3.5" aria-hidden="true" />
                                        All regions · sorted by compliance rate
                                    </p>
                                </div>

                                <div
                                    class="rr-board-head rr-muted hidden grid-cols-[3rem_minmax(6rem,1fr)_minmax(0,2.2fr)_9rem_1rem] gap-x-3 px-4 py-2 text-[10px] font-bold uppercase tracking-wider sm:grid"
                                    aria-hidden="true"
                                >
                                    <span>Rank</span>
                                    <span>Region</span>
                                    <span>Compliance Rate</span>
                                    <span>Status</span>
                                    <span />
                                </div>

                                <ol
                                    v-if="remainingRegions.length"
                                    class="rr-board-list flex flex-col gap-1 px-2 pb-2 sm:max-h-[min(40vh,420px)] sm:overflow-y-auto"
                                >
                                    <li
                                        v-for="(row, index) in remainingRegions"
                                        :key="row.region"
                                        class="rr-row-enter"
                                        :style="{ animationDelay: `${rowDelay(index) - 0.25}s` }"
                                    >
                                        <button
                                            type="button"
                                            class="rr-row grid w-full grid-cols-[2rem_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 rounded-xl px-2 py-2.5 text-left sm:grid-cols-[3rem_minmax(6rem,1fr)_minmax(0,2.2fr)_9rem_1rem] sm:px-2"
                                            :aria-label="`Rank ${row.rank}: ${row.region}, ${row.rate}% compliance, ${row.status.label}. View submitted employees.`"
                                            @click="emit('selectRegion', row.region)"
                                        >
                                            <span class="rr-rank col-start-1 row-start-1 text-center text-sm font-extrabold tabular-nums">{{
                                                row.rank
                                            }}</span>
                                            <span class="col-start-2 row-start-1 min-w-0">
                                                <span class="rr-region block truncate text-sm font-bold">{{ row.region }}</span>
                                                <span class="rr-muted block text-[10px] tabular-nums">
                                                    {{ row.submitted.toLocaleString() }} / {{ row.total.toLocaleString() }} submitted
                                                </span>
                                            </span>
                                            <span
                                                class="col-span-2 col-start-2 row-start-2 flex items-center gap-3 sm:col-span-1 sm:col-start-3 sm:row-start-1"
                                            >
                                                <span class="rr-rate w-12 shrink-0 text-sm font-bold tabular-nums">
                                                    {{ displayRate(row.rate, rowDelay(index)) }}%
                                                </span>
                                                <span class="rr-track relative h-2 flex-1 overflow-hidden rounded-full">
                                                    <span
                                                        class="rr-fill absolute inset-y-0 left-0 rounded-full"
                                                        :class="`rr-tone-${row.status.tone}`"
                                                        :style="{ width: `${row.rate * progressAt(rowDelay(index))}%` }"
                                                    />
                                                </span>
                                            </span>
                                            <span
                                                class="col-start-3 row-start-1 flex items-center gap-1.5 justify-self-end text-xs font-semibold sm:col-start-4 sm:justify-self-start"
                                                :class="STATUS_TEXT[row.status.tone]"
                                            >
                                                <span
                                                    class="rr-dot h-2 w-2 shrink-0 rounded-full"
                                                    :class="`rr-tone-${row.status.tone}`"
                                                    aria-hidden="true"
                                                />
                                                <span class="whitespace-nowrap">{{ row.status.label }}</span>
                                            </span>
                                            <ChevronRight
                                                class="rr-chevron hidden h-4 w-4 sm:col-start-5 sm:row-start-1 sm:block"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </li>
                                </ol>
                                <p v-else class="rr-muted px-4 pb-5 pt-2 text-center text-xs">No other regions to rank for the current filters.</p>
                            </section>
                        </template>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style>
/* Regional Compliance Ranking — unscoped because the modal is teleported to
   <body> and themed from the <html class="dark"> ancestor. Every selector is
   prefixed with .rr- to stay isolated. */

.rr-root {
    --rr-bg-from: #f7fbff;
    --rr-bg-to: #e3edf8;
    --rr-panel: rgba(255, 255, 255, 0.72);
    --rr-panel-strong: rgba(255, 255, 255, 0.9);
    --rr-border: rgba(14, 116, 144, 0.22);
    --rr-border-strong: rgba(8, 145, 178, 0.45);
    --rr-glow: rgba(6, 182, 212, 0.28);
    --rr-text: #0b1b33;
    --rr-muted: #475569;
    --rr-accent: #0e7490;
    --rr-accent-2: #047857;
    --rr-grid: rgba(8, 145, 178, 0.16);
    --rr-gold: #a16207;
    --rr-arena-from: rgba(14, 116, 144, 0.1);
    --rr-arena-to: rgba(255, 255, 255, 0);
    --rr-podium-from: #1b3358;
    --rr-podium-to: #0d1c36;
    --rr-podium-text: #eef6ff;
    --rr-podium-muted: #a9bcd6;
    color: var(--rr-text);
}

.dark .rr-root {
    --rr-bg-from: #071229;
    --rr-bg-to: #040a18;
    --rr-panel: rgba(10, 25, 50, 0.6);
    --rr-panel-strong: rgba(12, 30, 60, 0.85);
    --rr-border: rgba(34, 211, 238, 0.22);
    --rr-border-strong: rgba(34, 211, 238, 0.55);
    --rr-glow: rgba(34, 211, 238, 0.35);
    --rr-text: #e6f1ff;
    --rr-muted: #93a7c4;
    --rr-accent: #22d3ee;
    --rr-accent-2: #34d399;
    --rr-grid: rgba(34, 211, 238, 0.2);
    --rr-gold: #fcd34d;
    --rr-arena-from: rgba(34, 211, 238, 0.14);
    --rr-arena-to: rgba(4, 10, 24, 0);
}

.rr-muted {
    color: var(--rr-muted);
}

.rr-kicker {
    color: var(--rr-accent);
}

.rr-title {
    color: var(--rr-text);
}

/* ─── Shell ─────────────────────────────────────────────────────────────── */

.rr-backdrop {
    background: radial-gradient(ellipse at center, rgba(8, 47, 73, 0.45), rgba(2, 6, 23, 0.78));
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.rr-panel {
    background: linear-gradient(180deg, var(--rr-bg-from), var(--rr-bg-to));
    border: 1px solid var(--rr-border-strong);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.04) inset,
        0 0 40px -6px var(--rr-glow),
        0 30px 80px -20px rgba(2, 6, 23, 0.6);
}

.rr-panel-grid {
    background-image: linear-gradient(var(--rr-grid) 1px, transparent 1px), linear-gradient(90deg, var(--rr-grid) 1px, transparent 1px);
    background-size: 36px 36px;
    opacity: 0.35;
    -webkit-mask-image: radial-gradient(ellipse 70% 55% at 50% 0%, black 0%, transparent 75%);
    mask-image: radial-gradient(ellipse 70% 55% at 50% 0%, black 0%, transparent 75%);
}

.rr-sweep::before {
    content: '';
    position: absolute;
    top: -20%;
    bottom: -20%;
    left: 0;
    width: 30%;
    background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.09) 45%, rgba(165, 243, 252, 0.14) 50%, transparent);
    transform: translateX(-150%) skewX(-18deg);
    animation: rr-sweep 8s 0.7s ease-in-out infinite;
}

.rr-header-icon {
    color: #f59e0b;
    background: radial-gradient(circle at 35% 30%, rgba(253, 230, 138, 0.35), rgba(15, 23, 42, 0.05));
    border: 1px solid rgba(245, 158, 11, 0.5);
    box-shadow: 0 0 18px -2px rgba(245, 158, 11, 0.45);
}

.dark .rr-header-icon {
    color: #fcd34d;
    background: radial-gradient(circle at 35% 30%, rgba(253, 230, 138, 0.25), rgba(15, 23, 42, 0.6));
}

.rr-chip,
.rr-close {
    color: var(--rr-text);
    background: var(--rr-panel);
    border: 1px solid var(--rr-border);
}

.rr-close {
    transition:
        border-color 0.2s,
        box-shadow 0.2s,
        transform 0.2s;
}

.rr-close:hover {
    border-color: var(--rr-border-strong);
    box-shadow: 0 0 14px -2px var(--rr-glow);
}

.rr-close:focus-visible,
.rr-podium:focus-visible,
.rr-row:focus-visible {
    outline: 2px solid var(--rr-accent);
    outline-offset: 2px;
}

/* ─── Arena ─────────────────────────────────────────────────────────────── */

.rr-arena {
    background: radial-gradient(ellipse 60% 70% at 50% 35%, var(--rr-arena-from), var(--rr-arena-to));
    perspective: 900px;
}

.rr-floor-grid {
    height: 55%;
    background-image: linear-gradient(var(--rr-grid) 1px, transparent 1px), linear-gradient(90deg, var(--rr-grid) 1px, transparent 1px);
    background-size: 44px 44px;
    transform: rotateX(68deg);
    transform-origin: bottom center;
    -webkit-mask-image: radial-gradient(ellipse 60% 90% at 50% 100%, black 10%, transparent 75%);
    mask-image: radial-gradient(ellipse 60% 90% at 50% 100%, black 10%, transparent 75%);
    animation: rr-grid-in 1.1s 0.15s ease-out both;
}

.rr-spotlight {
    background:
        radial-gradient(ellipse 22% 55% at 50% 0%, rgba(253, 224, 71, 0.18), transparent 70%),
        radial-gradient(ellipse 14% 40% at 18% 10%, rgba(203, 213, 225, 0.12), transparent 70%),
        radial-gradient(ellipse 14% 40% at 82% 10%, rgba(251, 146, 60, 0.12), transparent 70%);
}

.rr-slot-1 {
    grid-column-start: 2;
    z-index: 2;
}

.rr-slot-2 {
    grid-column-start: 1;
    z-index: 3;
}

.rr-slot-3 {
    grid-column-start: 3;
    z-index: 3;
}

.rr-slot-empty {
    opacity: 0.45;
}

.rr-trophy-stage {
    height: 200px;
    margin-bottom: -26px;
    animation: rr-fade-in 0.6s 0.25s ease-out both;
}

@media (min-width: 640px) {
    .rr-trophy-stage {
        height: 290px;
        margin-bottom: -36px;
    }
}

.rr-fallback-trophy {
    color: #f5b301;
    filter: drop-shadow(0 0 18px rgba(245, 179, 1, 0.55));
}

/* ─── Podium blocks ─────────────────────────────────────────────────────── */

.rr-podium {
    --rr-podium-height: 96px;
    --rr-podium-accent: rgba(148, 163, 184, 0.9);
    display: block;
    color: var(--rr-podium-text);
    transform-origin: bottom center;
    transition: transform 0.25s ease;
    animation: rr-rise 0.75s cubic-bezier(0.2, 0.9, 0.25, 1.05) backwards;
}

.rr-slot-1 .rr-podium {
    --rr-podium-height: 128px;
    --rr-podium-accent: rgba(251, 191, 36, 0.95);
    animation-delay: 0.45s;
}

.rr-slot-2 .rr-podium {
    --rr-podium-height: 108px;
    --rr-podium-accent: rgba(203, 213, 225, 0.95);
    animation-delay: 0.75s;
}

.rr-slot-3 .rr-podium {
    --rr-podium-height: 96px;
    --rr-podium-accent: rgba(251, 146, 60, 0.95);
    animation-delay: 0.9s;
}

@media (min-width: 640px) {
    .rr-slot-1 .rr-podium {
        --rr-podium-height: 150px;
    }

    .rr-slot-2 .rr-podium {
        --rr-podium-height: 126px;
    }

    .rr-slot-3 .rr-podium {
        --rr-podium-height: 112px;
    }
}

.rr-podium:not(:disabled) {
    cursor: pointer;
}

.rr-podium:not(:disabled):hover {
    transform: translateY(-3px);
}

.rr-podium-top {
    position: relative;
    display: block;
    height: 16px;
    margin: 0 4%;
    background: linear-gradient(180deg, #2a4a7a, #1a3156);
    clip-path: polygon(6% 0, 94% 0, 100% 100%, 0 100%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.rr-podium-top::after {
    content: '';
    position: absolute;
    inset: 0 6% auto;
    height: 2px;
    background: var(--rr-podium-accent);
    box-shadow: 0 0 10px var(--rr-podium-accent);
}

.rr-slot-1 .rr-podium-top {
    visibility: hidden;
}

.rr-podium-front {
    position: relative;
    display: flex;
    height: var(--rr-podium-height);
    background:
        linear-gradient(90deg, rgba(255, 255, 255, 0.07), transparent 22%, transparent 78%, rgba(0, 0, 0, 0.25)),
        linear-gradient(180deg, var(--rr-podium-from), var(--rr-podium-to));
    border-top: 2px solid var(--rr-podium-accent);
    border-radius: 0 0 14px 14px;
    box-shadow:
        0 0 22px -6px var(--rr-podium-accent),
        0 18px 30px -12px rgba(2, 6, 23, 0.65);
}

.rr-podium-front::after {
    content: '';
    position: absolute;
    left: 10%;
    right: 10%;
    bottom: -10px;
    height: 10px;
    border-radius: 50%;
    background: radial-gradient(ellipse at center, var(--rr-podium-accent), transparent 70%);
    opacity: 0.45;
    filter: blur(4px);
}

.rr-plate {
    max-width: 100%;
    background: rgba(4, 12, 28, 0.55);
    border: 1px solid color-mix(in srgb, var(--rr-podium-accent) 55%, transparent);
    box-shadow: inset 0 0 18px -6px var(--rr-podium-accent);
}

.rr-podium .rr-muted {
    color: var(--rr-podium-muted);
}

.rr-podium .rr-region {
    color: var(--rr-podium-text);
}

.rr-slot-1 .rr-podium .rr-rate,
.rr-crown {
    color: #fcd34d;
    text-shadow: 0 0 14px rgba(252, 211, 77, 0.55);
}

.rr-slot-2 .rr-podium .rr-rate {
    color: #67e8f9;
}

.rr-slot-3 .rr-podium .rr-rate {
    color: #fdba74;
}

/* ─── Medals (CSS 3D) ───────────────────────────────────────────────────── */

.rr-medal-stage {
    height: 120px;
    width: 100%;
    padding-bottom: 6px;
}

@media (min-width: 640px) {
    .rr-medal-stage {
        height: 168px;
    }
}

.rr-medal {
    --rr-medal-size: 74px;
    position: relative;
    width: var(--rr-medal-size);
    height: calc(var(--rr-medal-size) * 1.25);
    display: flex;
    align-items: flex-end;
    justify-content: center;
    perspective: 520px;
    animation: rr-float 5s ease-in-out infinite;
}

@media (min-width: 640px) {
    .rr-medal {
        --rr-medal-size: 112px;
    }
}

.rr-medal-silver {
    --rr-metal-hi: #ffffff;
    --rr-metal-mid: #cbd5e1;
    --rr-metal-lo: #64748b;
    --rr-metal-edge: #7b8aa1;
    --rr-metal-ink: #334155;
    --rr-ribbon-a: #0891b2;
    --rr-ribbon-b: #155e75;
}

.rr-medal-bronze {
    --rr-metal-hi: #ffe2c2;
    --rr-metal-mid: #e3955a;
    --rr-metal-lo: #8a4a1c;
    --rr-metal-edge: #8f4e22;
    --rr-metal-ink: #5b2a0c;
    --rr-ribbon-a: #059669;
    --rr-ribbon-b: #065f46;
    animation-delay: -2.5s;
}

.rr-ribbon {
    position: absolute;
    top: 0;
    left: 50%;
    width: 46%;
    height: 45%;
    transform: translateX(-50%);
}

.rr-ribbon::before,
.rr-ribbon::after {
    content: '';
    position: absolute;
    top: 0;
    width: 52%;
    height: 100%;
    background: linear-gradient(180deg, var(--rr-ribbon-a), var(--rr-ribbon-b));
    clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 82%, 0 100%);
}

.rr-ribbon::before {
    left: 0;
    transform: skewX(14deg);
}

.rr-ribbon::after {
    right: 0;
    transform: skewX(-14deg);
}

.rr-medal-enter {
    position: relative;
    transform-style: preserve-3d;
    animation: rr-medal-in 1.1s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.rr-medal-silver .rr-medal-enter {
    animation-delay: 1s;
}

.rr-medal-bronze .rr-medal-enter {
    animation-delay: 1.15s;
}

.rr-coin {
    position: relative;
    width: var(--rr-medal-size);
    height: var(--rr-medal-size);
    transform-style: preserve-3d;
    animation: rr-coin-sway 7s ease-in-out infinite;
}

.rr-medal-bronze .rr-coin {
    animation-delay: -3.5s;
}

.rr-coin-edge {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: var(--rr-metal-edge);
}

.rr-coin-face {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: 50%;
    backface-visibility: hidden;
    background:
        radial-gradient(circle at 50% 50%, transparent 55%, rgba(0, 0, 0, 0.18) 56%, transparent 60%),
        repeating-conic-gradient(from 0deg, rgba(255, 255, 255, 0.12) 0deg 6deg, transparent 6deg 12deg),
        radial-gradient(circle at 32% 26%, var(--rr-metal-hi) 0%, var(--rr-metal-mid) 42%, var(--rr-metal-lo) 100%);
    box-shadow:
        inset 0 0 0 4px rgba(255, 255, 255, 0.35),
        inset 0 0 0 7px var(--rr-metal-lo),
        inset 0 -6px 14px rgba(0, 0, 0, 0.35);
}

.rr-coin-front {
    transform: translateZ(5px);
}

.rr-coin-back {
    transform: rotateY(180deg) translateZ(5px);
}

.rr-coin-face::after {
    content: '';
    position: absolute;
    inset: -20%;
    background: linear-gradient(115deg, transparent 40%, rgba(255, 255, 255, 0.65) 50%, transparent 60%);
    transform: translateX(-120%);
    animation: rr-glint 5s 1.8s ease-in-out infinite;
}

.rr-coin-number {
    position: relative;
    font-size: calc(var(--rr-medal-size) * 0.46);
    font-weight: 900;
    line-height: 1;
    color: var(--rr-metal-ink);
    text-shadow:
        0 1px 0 rgba(255, 255, 255, 0.6),
        0 -1px 0 rgba(0, 0, 0, 0.25);
}

/* ─── Ranking board ─────────────────────────────────────────────────────── */

.rr-board {
    background: var(--rr-panel);
    border: 1px solid var(--rr-border);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.06),
        0 0 24px -12px var(--rr-glow);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    animation: rr-fade-up 0.6s 1.25s ease-out both;
}

.rr-board-head {
    border-top: 1px solid var(--rr-border);
    border-bottom: 1px solid var(--rr-border);
}

.rr-row-enter {
    perspective: 800px;
    animation: rr-row-in 0.5s ease-out both;
}

.rr-row {
    border: 1px solid transparent;
    transition:
        transform 0.2s ease,
        background-color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.rr-row:hover {
    background: var(--rr-panel-strong);
    border-color: var(--rr-border-strong);
    box-shadow: 0 8px 22px -14px var(--rr-glow);
    transform: translateZ(14px) translateY(-1px);
}

.rr-rank {
    color: var(--rr-gold);
}

.rr-region {
    color: var(--rr-text);
}

.rr-board .rr-rate {
    color: var(--rr-accent);
}

.rr-track {
    background: color-mix(in srgb, var(--rr-muted) 18%, transparent);
}

.rr-fill {
    box-shadow: 0 0 10px -1px currentColor;
}

.rr-tone-excellent {
    color: #10b981;
    background: linear-gradient(90deg, #059669, #34d399);
}

.rr-tone-good {
    color: #06b6d4;
    background: linear-gradient(90deg, #0891b2, #22d3ee);
}

.rr-tone-moderate {
    color: #f59e0b;
    background: linear-gradient(90deg, #d97706, #fbbf24);
}

.rr-tone-low {
    color: #f43f5e;
    background: linear-gradient(90deg, #e11d48, #fb7185);
}

.rr-dot {
    box-shadow: 0 0 8px currentColor;
}

.rr-chevron {
    color: var(--rr-muted);
    transition: transform 0.2s ease;
}

.rr-row:hover .rr-chevron {
    transform: translateX(2px);
    color: var(--rr-accent);
}

/* ─── Modal transition ──────────────────────────────────────────────────── */

.rr-modal-enter-active .rr-backdrop {
    animation: rr-fade-in 0.35s ease-out both;
}

.rr-modal-enter-active .rr-panel {
    animation: rr-panel-in 0.65s cubic-bezier(0.2, 0.85, 0.25, 1) both;
}

.rr-modal-leave-active .rr-backdrop {
    animation: rr-fade-in 0.28s ease-in reverse both;
}

.rr-modal-leave-active .rr-panel {
    animation: rr-panel-out 0.28s ease-in both;
}

/* ─── Keyframes ─────────────────────────────────────────────────────────── */

@keyframes rr-fade-in {
    from {
        opacity: 0;
    }
}

@keyframes rr-fade-up {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
}

@keyframes rr-panel-in {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(18px);
        clip-path: inset(38% 12% round 24px);
    }
    to {
        clip-path: inset(0 0 round 24px);
    }
}

@keyframes rr-panel-out {
    to {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
}

@keyframes rr-grid-in {
    from {
        opacity: 0;
        transform: rotateX(68deg) translateY(30px);
    }
}

@keyframes rr-sweep {
    0% {
        transform: translateX(-150%) skewX(-18deg);
    }
    22%,
    100% {
        transform: translateX(450%) skewX(-18deg);
    }
}

@keyframes rr-rise {
    from {
        opacity: 0;
        transform: translateY(40px) scaleY(0.6);
    }
}

@keyframes rr-medal-in {
    from {
        opacity: 0;
        transform: translateY(-30px) rotateY(-540deg) scale(0.4);
    }
}

@keyframes rr-coin-sway {
    0%,
    100% {
        transform: rotateY(-28deg) rotateX(6deg);
    }
    50% {
        transform: rotateY(28deg) rotateX(-4deg);
    }
}

@keyframes rr-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-6px);
    }
}

@keyframes rr-glint {
    0%,
    100% {
        transform: translateX(-120%);
    }
    30% {
        transform: translateX(120%);
    }
}

@keyframes rr-row-in {
    from {
        opacity: 0;
        transform: translateX(-14px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .rr-root *,
    .rr-root *::before,
    .rr-root *::after {
        animation: none !important;
        transition: none !important;
    }

    .rr-sweep {
        display: none;
    }

    .rr-coin {
        transform: rotateY(-18deg);
    }
}
</style>
