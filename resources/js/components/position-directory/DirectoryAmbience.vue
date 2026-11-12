<script setup lang="ts">
import { seededUnit, type MotionLevel } from '@/lib/positionDirectory';
import { useDocumentVisibility } from '@vueuse/core';
import { computed } from 'vue';
// Its colours come from the .pd-theme tokens; load them wherever the background is used.
import '../../../css/position-directory.css';

const props = defineProps<{
    motionLevel: MotionLevel;
    /** Lighting tone of the office/division currently in view (0–3). */
    tone: number;
}>();

const visibility = useDocumentVisibility();

const PARTICLE_COUNT = 16;

const particles = Array.from({ length: PARTICLE_COUNT }, (_, index) => ({
    left: `${(seededUnit(index, 1) * 100).toFixed(2)}%`,
    top: `${(30 + seededUnit(index, 2) * 70).toFixed(2)}%`,
    size: `${(2 + seededUnit(index, 3) * 3).toFixed(1)}px`,
    duration: `${(14 + seededUnit(index, 4) * 16).toFixed(1)}s`,
    delay: `-${(seededUnit(index, 5) * 20).toFixed(1)}s`,
    isGold: index % 4 === 0,
}));

/** Circuit-like traces; the same paths carry the travelling light pulses. */
const traces = [
    'M-20 140 H240 L300 200 H560 L620 140 H900',
    'M1220 300 H980 L920 360 H700 L660 320 H420',
    'M-20 520 H180 L240 460 H480 L540 520 H820 L880 580 H1220',
    'M300 820 V680 L360 620 H620',
    'M900 -20 V120 L960 180 H1220',
];

const showsParticles = computed(() => props.motionLevel === 'full');
</script>

<template>
    <div class="pd-ambience" :data-motion="motionLevel" :data-tone="tone % 4" :class="{ 'is-paused': visibility === 'hidden' }" aria-hidden="true">
        <div class="pd-ambience__viewport">
            <div class="pd-ambience__grid" />

            <div class="pd-ambience__orb pd-ambience__orb--a" />
            <div class="pd-ambience__orb pd-ambience__orb--b" />
            <div class="pd-ambience__orb pd-ambience__orb--c" />

            <template v-if="motionLevel !== 'still'">
                <div class="pd-ambience__beam pd-ambience__beam--one" />
                <div class="pd-ambience__beam pd-ambience__beam--two" />
                <div class="pd-ambience__beam pd-ambience__beam--gold" />
            </template>

            <svg class="pd-ambience__traces" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice">
                <g class="pd-ambience__trace-base">
                    <path v-for="(trace, index) in traces" :key="`base-${index}`" :d="trace" />
                    <circle cx="300" cy="200" r="3" />
                    <circle cx="620" cy="140" r="3" />
                    <circle cx="660" cy="320" r="3" />
                    <circle cx="540" cy="520" r="3" />
                    <circle cx="960" cy="180" r="3" />
                </g>
                <g v-if="motionLevel === 'full'" class="pd-ambience__trace-pulses">
                    <path
                        v-for="(trace, index) in traces"
                        :key="`pulse-${index}`"
                        :d="trace"
                        :style="{ animationDelay: `-${index * 2.7}s`, animationDuration: `${11 + index * 2}s` }"
                    />
                </g>
            </svg>

            <template v-if="showsParticles">
                <span
                    v-for="(particle, index) in particles"
                    :key="index"
                    class="pd-ambience__particle"
                    :class="{ 'is-gold': particle.isGold }"
                    :style="{
                        left: particle.left,
                        top: particle.top,
                        width: particle.size,
                        height: particle.size,
                        animationDuration: particle.duration,
                        animationDelay: particle.delay,
                    }"
                />
            </template>
        </div>
    </div>
</template>

<style scoped>
@property --pd-orb-tone {
    syntax: '<color>';
    inherits: true;
    initial-value: rgba(47, 107, 255, 0.22);
}

.pd-ambience {
    position: absolute;
    inset: 0;
    z-index: 0;
    overflow: clip;
    pointer-events: none;
    --pd-orb-tone: var(--pd-tone-0);
    transition: --pd-orb-tone 1.6s ease;
}

.pd-ambience[data-tone='1'] {
    --pd-orb-tone: var(--pd-tone-1);
}

.pd-ambience[data-tone='2'] {
    --pd-orb-tone: var(--pd-tone-2);
}

.pd-ambience[data-tone='3'] {
    --pd-orb-tone: var(--pd-tone-3);
}

/* Viewport-sized so the effects never span the whole (possibly very tall) page. */
.pd-ambience__viewport {
    position: sticky;
    top: 0;
    height: 100vh;
    height: 100svh;
    overflow: hidden;
}

.pd-ambience__grid {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(var(--pd-grid-line) 1px, transparent 1px), linear-gradient(90deg, var(--pd-grid-line) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 70% at 50% 40%, #000 20%, transparent 75%);
}

.pd-ambience__orb {
    position: absolute;
    border-radius: 9999px;
    animation: pd-orb-drift 46s ease-in-out infinite alternate;
}

.pd-ambience__orb--a {
    top: -12%;
    left: -10%;
    width: 55vw;
    height: 55vw;
    background: radial-gradient(circle, var(--pd-orb-tone), transparent 65%);
}

.pd-ambience__orb--b {
    right: -14%;
    bottom: -18%;
    width: 50vw;
    height: 50vw;
    background: radial-gradient(circle, var(--pd-orb-blue), transparent 65%);
    animation-duration: 58s;
    animation-direction: alternate-reverse;
}

.pd-ambience__orb--c {
    top: 30%;
    left: 45%;
    width: 30vw;
    height: 30vw;
    background: radial-gradient(circle, var(--pd-orb-gold), transparent 65%);
    animation-duration: 64s;
}

.pd-ambience__beam {
    position: absolute;
    top: -30%;
    width: 18vw;
    min-width: 160px;
    height: 160%;
    background: linear-gradient(90deg, transparent, var(--pd-beam), transparent);
    transform: rotate(24deg);
    animation: pd-beam-sweep 26s ease-in-out infinite;
}

.pd-ambience__beam--one {
    left: -10%;
}

.pd-ambience__beam--two {
    left: 30%;
    width: 9vw;
    animation-duration: 34s;
    animation-delay: -12s;
}

.pd-ambience__beam--gold {
    left: 60%;
    width: 6vw;
    background: linear-gradient(90deg, transparent, var(--pd-beam-gold), transparent);
    animation-duration: 40s;
    animation-delay: -22s;
}

.pd-ambience__traces {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

.pd-ambience__trace-base {
    fill: none;
    stroke: var(--pd-trace);
    stroke-width: 1;
}

.pd-ambience__trace-base circle {
    fill: var(--pd-trace-node);
    stroke: none;
}

.pd-ambience__trace-pulses path {
    fill: none;
    stroke: var(--pd-trace-pulse);
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-dasharray: 70 1600;
    stroke-dashoffset: 1670;
    opacity: 0.8;
    animation: pd-trace-travel 13s linear infinite;
}

.pd-ambience__particle {
    position: absolute;
    border-radius: 9999px;
    background: var(--pd-particle);
    box-shadow: 0 0 6px var(--pd-particle);
    opacity: 0;
    animation: pd-particle-rise 20s linear infinite;
}

.pd-ambience__particle.is-gold {
    background: var(--pd-particle-gold);
    box-shadow: 0 0 6px var(--pd-particle-gold);
}

@keyframes pd-orb-drift {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    100% {
        transform: translate3d(6vw, 4vh, 0) scale(1.12);
    }
}

@keyframes pd-beam-sweep {
    0%,
    100% {
        transform: translate3d(-8vw, 0, 0) rotate(24deg);
        opacity: 0.4;
    }
    50% {
        transform: translate3d(14vw, 0, 0) rotate(24deg);
        opacity: 1;
    }
}

@keyframes pd-trace-travel {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes pd-particle-rise {
    0% {
        transform: translate3d(0, 0, 0);
        opacity: 0;
    }
    15% {
        opacity: 0.75;
    }
    85% {
        opacity: 0.5;
    }
    100% {
        transform: translate3d(2vw, -40vh, 0);
        opacity: 0;
    }
}

.pd-ambience.is-paused *,
.pd-ambience[data-motion='calm'] .pd-ambience__orb {
    animation-play-state: paused;
}

.pd-ambience[data-motion='still'] * {
    animation: none !important;
}
</style>
