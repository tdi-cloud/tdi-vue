<script setup lang="ts">
import HeroCenterpiece from '@/components/position-directory/HeroCenterpiece.vue';
import { seededUnit, type EmblemShape, type HeroStat, type MotionLevel } from '@/lib/positionDirectory';
import { TransitionPresets, useTransition } from '@vueuse/core';
import { computed } from 'vue';

/**
 * Navy hero shared by the Position Directory and the Employees page: a
 * full-height left panel on wide pages, a slim banner on narrow ones. Title,
 * counts and the 3D emblem's nodes come from props; the controls go in the
 * #selector slot and an optional note in #footer.
 */
const props = withDefaults(
    defineProps<{
        /** White part of the title. */
        title?: string;
        /** Gold part of the title (its own line on the panel). */
        titleAccent?: string;
        subtitle: string;
        stats: HeroStat[];
        /** Lights orbiting the 3D emblem. */
        nodeCount: number;
        /** Changes whenever the results change, to play a light sweep and burst. */
        pulseKey: string | null;
        /** Tooltip explaining the 3D emblem. */
        visualLabel?: string;
        /** Centre of the 3D emblem: the faceted crystal or a spoked gear. */
        emblem?: EmblemShape;
        motionLevel: MotionLevel;
        loading: boolean;
        /** Full-height left panel (two-column page) instead of the slim banner. */
        panel?: boolean;
    }>(),
    { title: '', titleAccent: '', visualLabel: undefined, emblem: 'crystal', panel: false },
);

/** The white part plus a space before the gold part, so the banner reads as two words. */
const titleLead = computed(() => (props.title && props.titleAccent ? `${props.title} ` : props.title));

const animatedValues = useTransition(
    computed(() => props.stats.map((stat) => stat.value)),
    {
        duration: 800,
        transition: TransitionPresets.easeOutCubic,
        disabled: computed(() => props.motionLevel === 'still'),
    },
);

const shownStats = computed(() => props.stats.map((stat, index) => ({ ...stat, value: Math.round(animatedValues.value[index] ?? stat.value) })));

const particles = Array.from({ length: 10 }, (_, index) => ({
    left: `${(8 + seededUnit(index, 11) * 84).toFixed(1)}%`,
    bottom: `${(seededUnit(index, 12) * 30).toFixed(1)}%`,
    duration: `${(9 + seededUnit(index, 13) * 9).toFixed(1)}s`,
    delay: `-${(seededUnit(index, 14) * 12).toFixed(1)}s`,
    isGold: index % 3 === 0,
}));
</script>

<template>
    <section class="pd-hero" :class="{ 'is-panel': panel }" :data-motion="motionLevel" aria-labelledby="pd-hero-title">
        <!-- Decorative layers (clipped here so the selector's dropdown can overflow the hero) -->
        <div class="pd-hero__backdrop" aria-hidden="true">
            <div class="pd-hero__glow pd-hero__glow--royal" />
            <div class="pd-hero__glow pd-hero__glow--gold" />
            <div class="pd-hero__grid" />

            <template v-if="motionLevel !== 'still'">
                <div class="pd-hero__beam pd-hero__beam--one" />
                <div class="pd-hero__beam pd-hero__beam--two" />
            </template>

            <svg class="pd-hero__geometry" viewBox="0 0 1200 640" preserveAspectRatio="xMidYMid slice">
                <g class="pd-hero__hex">
                    <polygon points="930,90 1090,182 1090,366 930,458 770,366 770,182" />
                    <polygon points="930,40 1133,157 1133,391 930,508 727,391 727,157" opacity="0.5" />
                </g>
                <g class="pd-hero__diagonals">
                    <line x1="-40" y1="620" x2="520" y2="-20" />
                    <line x1="140" y1="660" x2="700" y2="20" />
                    <line x1="520" y1="680" x2="1240" y2="80" />
                </g>
                <g class="pd-hero__arcs">
                    <path d="M 0 520 Q 300 380 640 470 T 1200 420" />
                    <path d="M 0 560 Q 320 440 660 520 T 1200 470" />
                </g>
            </svg>

            <template v-if="motionLevel === 'full'">
                <span class="pd-hero__trail pd-hero__trail--one" />
                <span class="pd-hero__trail pd-hero__trail--two" />
                <span
                    v-for="(particle, index) in particles"
                    :key="index"
                    class="pd-hero__particle"
                    :class="{ 'is-gold': particle.isGold }"
                    :style="{ left: particle.left, bottom: particle.bottom, animationDuration: particle.duration, animationDelay: particle.delay }"
                />
            </template>

            <!-- One light sweep each time the results change -->
            <span v-if="motionLevel !== 'still'" :key="pulseKey ?? 'none'" class="pd-hero__sweep" />
        </div>

        <!--
            Wide screens: a full-height left panel (title, 3D emblem, selector, counts, note).
            Narrow screens: a slim banner above the cards (the emblem is hidden there).
        -->
        <div class="pd-hero__content">
            <header class="pd-hero__heading">
                <h1 id="pd-hero-title" class="pd-hero__title">
                    {{ titleLead }}<span v-if="titleAccent" class="pd-hero__title-accent">{{ titleAccent }}</span>
                </h1>
                <p class="pd-hero__subtitle">{{ subtitle }}</p>
            </header>

            <div class="pd-hero__visual" :title="visualLabel">
                <div class="pd-hero__visual-frame">
                    <HeroCenterpiece :node-count="nodeCount" :pulse-key="pulseKey" :motion-level="motionLevel" :shape="emblem" fill />
                </div>
            </div>

            <div class="pd-hero__controls">
                <div class="pd-hero__selector">
                    <slot name="selector" />
                </div>

                <dl class="pd-hero__stats" :class="{ 'is-loading': loading }" :style="{ '--pd-hero-stat-count': stats.length }" :aria-busy="loading">
                    <div v-for="stat in shownStats" :key="stat.label" class="pd-hero__stat">
                        <dt>
                            <component :is="stat.icon" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                            {{ stat.label }}
                        </dt>
                        <dd>
                            {{ stat.value.toLocaleString() }}<span v-if="stat.suffix" class="pd-hero__stat-suffix">{{ stat.suffix }}</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div v-if="$slots.footer" class="pd-hero__footer">
                <slot name="footer" />
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Above the sticky toolbar (z-20) so the position dropdown can overlap it. */
.pd-hero {
    position: relative;
    z-index: 30;
    border-radius: 2rem;
    color: #f4f7ff;
    isolation: isolate;
}

.pd-hero__backdrop {
    position: absolute;
    inset: 0;
    z-index: -1;
    overflow: hidden;
    border-radius: inherit;
    border: 1px solid rgba(226, 194, 122, 0.22);
    background:
        radial-gradient(120% 90% at 85% 10%, var(--pd-hero-glow), transparent 60%),
        linear-gradient(135deg, var(--pd-hero-from) 0%, var(--pd-hero-via) 55%, var(--pd-hero-to) 100%);
    box-shadow: 0 40px 90px -50px rgba(6, 21, 54, 0.9);
}

.pd-hero__glow {
    position: absolute;
    border-radius: 9999px;
    animation: pd-hero-glow 22s ease-in-out infinite alternate;
}

.pd-hero__glow--royal {
    top: -30%;
    left: -10%;
    width: 60%;
    aspect-ratio: 1;
    background: radial-gradient(circle, rgba(47, 107, 255, 0.45), transparent 65%);
}

.pd-hero__glow--gold {
    right: 5%;
    bottom: -45%;
    width: 45%;
    aspect-ratio: 1;
    background: radial-gradient(circle, rgba(226, 194, 122, 0.22), transparent 65%);
    animation-duration: 28s;
    animation-direction: alternate-reverse;
}

.pd-hero__grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
    background-size: 44px 44px;
    mask-image: linear-gradient(to bottom, #000, transparent 85%);
}

.pd-hero__beam {
    position: absolute;
    top: -50%;
    width: 9rem;
    height: 200%;
    background: linear-gradient(90deg, transparent, rgba(160, 196, 255, 0.16), transparent);
    transform: rotate(28deg);
    animation: pd-hero-beam 16s ease-in-out infinite;
}

.pd-hero__beam--one {
    left: 15%;
}

.pd-hero__beam--two {
    left: 55%;
    width: 4rem;
    background: linear-gradient(90deg, transparent, rgba(230, 195, 114, 0.14), transparent);
    animation-duration: 21s;
    animation-delay: -8s;
}

.pd-hero__geometry {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

.pd-hero__hex {
    fill: none;
    stroke: rgba(230, 195, 114, 0.35);
    stroke-width: 1;
    transform-box: fill-box;
    transform-origin: center;
    animation: pd-hero-hex 60s linear infinite;
}

.pd-hero__diagonals {
    stroke: rgba(160, 196, 255, 0.18);
    stroke-width: 1;
    stroke-dasharray: 2 10;
    animation: pd-hero-dash 18s linear infinite;
}

.pd-hero__arcs {
    fill: none;
    stroke: rgba(160, 196, 255, 0.16);
    stroke-width: 1;
}

.pd-hero__trail {
    position: absolute;
    left: 0;
    height: 1px;
    width: 35%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.85), rgba(230, 195, 114, 0.8), transparent);
    opacity: 0;
    animation: pd-hero-trail 9s ease-in-out infinite;
}

.pd-hero__trail--one {
    top: 28%;
}

.pd-hero__trail--two {
    top: 41%;
    animation-delay: -4.5s;
    animation-duration: 11s;
}

.pd-hero__particle {
    position: absolute;
    width: 3px;
    height: 3px;
    border-radius: 9999px;
    background: #cfe0ff;
    box-shadow: 0 0 6px rgba(207, 224, 255, 0.9);
    opacity: 0;
    animation: pd-hero-particle 12s linear infinite;
}

.pd-hero__particle.is-gold {
    background: #e6c372;
    box-shadow: 0 0 6px rgba(230, 195, 114, 0.9);
}

.pd-hero__sweep {
    position: absolute;
    inset: 0;
    background: linear-gradient(100deg, transparent 35%, rgba(255, 255, 255, 0.16) 48%, rgba(230, 195, 114, 0.14) 52%, transparent 65%);
    background-size: 250% 100%;
    background-position: 150% 0;
    animation: pd-hero-sweep 1.5s cubic-bezier(0.3, 0.7, 0.2, 1) both;
}

/* ─── Content ───────────────────────────────────────────────────────────── */
/* Narrow screens: slim banner (heading, then selector + counts) */
.pd-hero__content {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1rem;
    padding: 1.25rem 1.25rem 1.4rem;
}

@media (min-width: 640px) {
    .pd-hero__content {
        padding: 1.5rem 2rem 1.6rem;
    }
}

.pd-hero__title {
    font-size: clamp(1.5rem, 1rem + 1.7vw, 2.4rem);
    font-weight: 800;
    line-height: 1.08;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    text-wrap: balance;
    color: #ffffff;
    text-shadow: 0 10px 40px rgba(47, 107, 255, 0.35);
}

.pd-hero__title-accent {
    background: linear-gradient(100deg, #f6e7bd 0%, #e6c372 45%, #c99b3e 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.pd-hero__subtitle {
    margin-top: 0.3rem;
    font-size: clamp(0.85rem, 0.8rem + 0.25vw, 1rem);
    font-weight: 500;
    letter-spacing: 0.04em;
    color: rgba(214, 226, 255, 0.85);
}

.pd-hero__controls {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.75rem 1rem;
}

.pd-hero__selector {
    flex: 1 1 22rem;
    min-width: 0;
    max-width: 36rem;
}

.pd-hero__stats {
    display: flex;
    flex: 0 1 auto;
    gap: 0.5rem;
}

.pd-hero__stats.is-loading dd {
    animation: pd-hero-blink 0.8s ease-in-out infinite alternate;
}

.pd-hero__stat {
    min-width: 6.5rem;
    border-radius: 0.9rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: linear-gradient(160deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.03));
    padding: 0.45rem 0.8rem;
}

.pd-hero__stat dt {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    white-space: nowrap;
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(214, 226, 255, 0.72);
}

.pd-hero__stat dd {
    margin-top: 0.15rem;
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1.05;
    font-variant-numeric: tabular-nums;
    color: #ffffff;
}

.pd-hero__stat-suffix {
    margin-left: 0.2rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(214, 226, 255, 0.72);
}

@media (max-width: 639px) {
    .pd-hero__stats {
        width: 100%;
    }

    .pd-hero__stat {
        flex: 1;
        min-width: 0;
    }
}

.pd-hero__visual {
    display: none;
}

.pd-hero__footer {
    font-size: 0.7rem;
    line-height: 1.45;
    color: rgba(214, 226, 255, 0.7);
}

/*
 * Panel mode (two-column page): a full-height left panel. Title on two lines,
 * the 3D object fills all the height that's left, then selector, counts, note.
 */
.pd-hero.is-panel {
    height: 100%;
}

.pd-hero.is-panel .pd-hero__content {
    display: flex;
    flex-direction: column;
    height: 100%;
    gap: 0.9rem;
    padding: 1.6rem 1.5rem 1.4rem;
}

.pd-hero.is-panel .pd-hero__title {
    font-size: clamp(1.45rem, 0.95rem + 0.9vw, 1.9rem);
    text-wrap: initial;
}

.pd-hero.is-panel .pd-hero__title-accent {
    display: block;
}

.pd-hero.is-panel .pd-hero__subtitle {
    font-size: 0.86rem;
}

.pd-hero.is-panel .pd-hero__visual {
    position: relative;
    display: block;
    flex: 1 1 0;
    /* Pages with more controls in the panel can let the emblem shrink further */
    min-height: var(--pd-hero-visual-min, 9rem);
    /* Let the object spread to the panel edges */
    margin: -0.5rem -1.5rem;
}

.pd-hero.is-panel .pd-hero__visual-frame {
    position: absolute;
    inset: 0;
}

.pd-hero.is-panel .pd-hero__controls {
    display: grid;
    gap: 0.75rem;
}

.pd-hero.is-panel .pd-hero__selector {
    max-width: none;
}

.pd-hero.is-panel .pd-hero__stats {
    display: grid;
    grid-template-columns: repeat(var(--pd-hero-stat-count, 3), minmax(0, 1fr));
}

/* Numbers line up at the bottom even when a label wraps to two lines */
.pd-hero.is-panel .pd-hero__stat {
    display: flex;
    min-width: 0;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.2rem;
    padding: 0.5rem 0.65rem;
}

.pd-hero.is-panel .pd-hero__stat dt {
    align-items: flex-start;
    white-space: normal;
    line-height: 1.25;
}

.pd-hero.is-panel .pd-hero__stat dd {
    margin-top: 0;
}

.pd-hero.is-panel .pd-hero__footer {
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    padding-top: 0.75rem;
}

/* ─── Keyframes ─────────────────────────────────────────────────────────── */
@keyframes pd-hero-glow {
    to {
        transform: translate3d(8%, 6%, 0) scale(1.15);
    }
}

@keyframes pd-hero-beam {
    0%,
    100% {
        transform: translate3d(-6rem, 0, 0) rotate(28deg);
        opacity: 0.3;
    }
    50% {
        transform: translate3d(10rem, 0, 0) rotate(28deg);
        opacity: 1;
    }
}

@keyframes pd-hero-hex {
    to {
        transform: rotate(360deg);
    }
}

@keyframes pd-hero-dash {
    to {
        stroke-dashoffset: -240;
    }
}

@keyframes pd-hero-trail {
    0% {
        transform: translate3d(-100%, 0, 0);
        opacity: 0;
    }
    20% {
        opacity: 0.9;
    }
    70% {
        opacity: 0.6;
    }
    100% {
        transform: translate3d(320%, 0, 0);
        opacity: 0;
    }
}

@keyframes pd-hero-particle {
    0% {
        transform: translate3d(0, 0, 0);
        opacity: 0;
    }
    20% {
        opacity: 0.9;
    }
    100% {
        transform: translate3d(1.5rem, -18rem, 0);
        opacity: 0;
    }
}

@keyframes pd-hero-sweep {
    to {
        background-position: -50% 0;
    }
}

@keyframes pd-hero-blink {
    to {
        opacity: 0.4;
    }
}

.pd-hero[data-motion='calm'] .pd-hero__glow,
.pd-hero[data-motion='calm'] .pd-hero__hex,
.pd-hero[data-motion='calm'] .pd-hero__diagonals {
    animation: none;
}

.pd-hero[data-motion='still'] *,
.pd-hero[data-motion='still'] *::before,
.pd-hero[data-motion='still'] *::after {
    animation: none !important;
    transition-duration: 0s !important;
}
</style>
