<script setup lang="ts">
/**
 * The animated technology layer of the public forms, drawn over the background
 * picture: a navy atmosphere, flowing light lines, network clusters, floating
 * particles, light beams and holographic rings.
 *
 * CSS and SVG only: no JavaScript animation loop, no listeners, and nothing
 * reactive, so it renders once. The motion stays around the edges and a veil
 * keeps the form column calm. It respects prefers-reduced-motion and thins
 * out on small screens.
 */
withDefaults(
    defineProps<{
        /** The width of the form column, so the calm zone in the middle matches it. */
        focusWidth?: 'md' | 'form';
    }>(),
    { focusWidth: 'form' },
);

/** A light streak travelling along a line, in seconds. */
interface Streak {
    duration: number;
    delay: number;
    gradient: string;
}

/** One flowing line across the 1440×900 scene. */
interface FlowLine {
    path: string;
    gradient: string;
    strokeWidth: number;
    /** Draws a wider, faint copy underneath that reads as glow. */
    glows?: boolean;
    streak?: Streak;
}

/** One point of a network cluster in its 360×520 box. */
interface NetworkNode {
    x: number;
    y: number;
    pulses?: boolean;
    gold?: boolean;
}

/** The two flow layers drift in opposite directions for parallax depth. */
const flowLayers: Array<{ key: string; lines: FlowLine[] }> = [
    {
        key: 'front',
        lines: [
            {
                path: 'M-80 646C220 540 430 770 724 662S1180 470 1520 566',
                gradient: 'pf-gradient-cyan',
                strokeWidth: 1.4,
                glows: true,
                streak: { duration: 11, delay: -2, gradient: 'pf-gradient-streak' },
            },
            {
                path: 'M-80 708C250 610 470 830 760 726S1200 566 1520 644',
                gradient: 'pf-gradient-cyan',
                strokeWidth: 0.9,
                streak: { duration: 15, delay: -9, gradient: 'pf-gradient-streak' },
            },
            {
                path: 'M-80 182C230 266 466 94 760 172S1220 304 1520 194',
                gradient: 'pf-gradient-cyan',
                strokeWidth: 1.2,
                glows: true,
                streak: { duration: 13, delay: -6, gradient: 'pf-gradient-streak' },
            },
            { path: 'M-80 238C260 316 500 150 790 226S1230 350 1520 252', gradient: 'pf-gradient-blue', strokeWidth: 0.7 },
        ],
    },
    {
        key: 'back',
        lines: [
            {
                path: 'M-80 786C300 694 566 884 866 792S1268 662 1520 744',
                gradient: 'pf-gradient-gold',
                strokeWidth: 1,
                streak: { duration: 17, delay: -3, gradient: 'pf-gradient-streak-gold' },
            },
            { path: 'M-80 318C286 396 526 232 826 310S1246 426 1520 336', gradient: 'pf-gradient-blue', strokeWidth: 0.8 },
            {
                path: 'M-80 104C340 40 626 200 926 112S1306 24 1520 84',
                gradient: 'pf-gradient-blue',
                strokeWidth: 0.8,
                streak: { duration: 19, delay: -12, gradient: 'pf-gradient-streak' },
            },
            { path: 'M-80 852C320 790 620 900 920 846S1300 780 1520 820', gradient: 'pf-gradient-gold', strokeWidth: 0.6 },
        ],
    },
];

const networkNodes: NetworkNode[] = [
    { x: 36, y: 48, pulses: true },
    { x: 142, y: 22 },
    { x: 250, y: 78, pulses: true, gold: true },
    { x: 92, y: 150 },
    { x: 204, y: 186, pulses: true },
    { x: 318, y: 150 },
    { x: 52, y: 288 },
    { x: 168, y: 318, pulses: true },
    { x: 286, y: 276 },
    { x: 120, y: 446 },
    { x: 244, y: 420, pulses: true, gold: true },
];

/** Pairs of node indexes; the comments mark the links that fade in and out or carry a signal. */
const networkLinkPairs: Array<[number, number]> = [
    [0, 1],
    [1, 2],
    [0, 3],
    [1, 3],
    [3, 4],
    [2, 4],
    [2, 5],
    [4, 5],
    [3, 6],
    [4, 7],
    [6, 7],
    [7, 8],
    [5, 8],
    [7, 9],
    [8, 10],
    [9, 10],
    [4, 8],
];

const BLINKING_LINKS = new Set([1, 6, 11, 15]);
const SIGNAL_LINKS: Array<{ link: number; duration: number; delay: number }> = [
    { link: 3, duration: 6, delay: -1 },
    { link: 9, duration: 7.5, delay: -4 },
    { link: 14, duration: 6.5, delay: -2.5 },
    { link: 6, duration: 8, delay: -6 },
];

function linkPath([from, to]: [number, number]): string {
    const start = networkNodes[from];
    const end = networkNodes[to];

    return `M${start.x} ${start.y}L${end.x} ${end.y}`;
}

const networkLinks = networkLinkPairs.map((pair, index) => ({
    key: `link-${index}`,
    path: linkPath(pair),
    blinks: BLINKING_LINKS.has(index),
    style: { '--pf-duration': `${7 + (index % 4) * 1.5}s`, '--pf-delay': `-${(index * 1.7) % 9}s` },
}));

const networkSignals = SIGNAL_LINKS.map((signal) => ({
    key: `signal-${signal.link}`,
    path: linkPath(networkLinkPairs[signal.link]),
    style: { '--pf-duration': `${signal.duration}s`, '--pf-delay': `${signal.delay}s` },
}));

const networkPulses = networkNodes
    .filter((node) => node.pulses)
    .map((node, index) => ({ ...node, key: `pulse-${node.x}-${node.y}`, style: { '--pf-delay': `-${index * 1.1}s` } }));

/** The same cluster sits top left and, mirrored and out of step, bottom right. */
const networkClusters = [
    { key: 'left', mirrored: false },
    { key: 'right', mirrored: true },
];

function hexagonPoints(centerX: number, centerY: number, radius: number): string {
    return Array.from({ length: 6 }, (_, corner) => {
        const angle = (Math.PI / 3) * corner - Math.PI / 2;

        return `${(centerX + radius * Math.cos(angle)).toFixed(1)},${(centerY + radius * Math.sin(angle)).toFixed(1)}`;
    }).join(' ');
}

const hexagons = [
    { centerX: 50, centerY: 50, gold: false, filled: true },
    { centerX: 112.4, centerY: 50, gold: false, filled: false },
    { centerX: 81.2, centerY: 104, gold: true, filled: false },
    { centerX: 143.5, centerY: 104, gold: false, filled: true },
    { centerX: 112.4, centerY: 158, gold: false, filled: false },
].map((hexagon) => ({ ...hexagon, key: `hex-${hexagon.centerX}-${hexagon.centerY}`, points: hexagonPoints(hexagon.centerX, hexagon.centerY, 34) }));

/** A small seeded random generator, so the particles land in the same places on every visit. */
function seededRandom(seed: number): () => number {
    let state = seed;

    return () => {
        state = (state + 0x6d2b79f5) | 0;
        let mixed = Math.imul(state ^ (state >>> 15), 1 | state);
        mixed = (mixed + Math.imul(mixed ^ (mixed >>> 7), 61 | mixed)) ^ mixed;

        return ((mixed ^ (mixed >>> 14)) >>> 0) / 4294967296;
    };
}

const PARTICLE_COUNT = 20;
const random = seededRandom(1994);
const between = (min: number, max: number): number => min + random() * (max - min);

/** Most particles float along the left and right edges, a few anywhere; mostly upward. */
const particles = Array.from({ length: PARTICLE_COUNT }, (_, index) => {
    const onLeft = index % 2 === 0;
    const left = random() < 0.85 ? (onLeft ? between(2, 26) : between(74, 98)) : between(30, 70);
    const tone = index % 7 === 3 ? 'gold' : index % 3 === 0 ? 'blue' : 'cyan';

    return {
        key: `particle-${index}`,
        tone,
        style: {
            left: `${left.toFixed(2)}%`,
            top: `${between(4, 96).toFixed(2)}%`,
            '--pf-size': `${between(2, 5).toFixed(1)}px`,
            '--pf-dx': `${between(-60, 60).toFixed(0)}px`,
            '--pf-dy': `${between(-170, 50).toFixed(0)}px`,
            '--pf-duration': `${between(16, 30).toFixed(1)}s`,
            '--pf-delay': `-${between(0, 30).toFixed(1)}s`,
        },
    };
});
</script>

<template>
    <div class="pf-backdrop" :class="`pf-backdrop--${focusWidth}`" aria-hidden="true">
        <!-- Shared gradients (a zero-size SVG; display: none would break the references) -->
        <svg class="pf-defs" width="0" height="0" focusable="false">
            <defs>
                <linearGradient id="pf-gradient-cyan" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="1440" y2="0">
                    <stop offset="0" stop-color="#38bdf8" stop-opacity="0" />
                    <stop offset="0.14" stop-color="#38bdf8" stop-opacity="0.75" />
                    <stop offset="0.4" stop-color="#60a5fa" stop-opacity="0.28" />
                    <stop offset="0.6" stop-color="#60a5fa" stop-opacity="0.28" />
                    <stop offset="0.86" stop-color="#38bdf8" stop-opacity="0.75" />
                    <stop offset="1" stop-color="#38bdf8" stop-opacity="0" />
                </linearGradient>
                <linearGradient id="pf-gradient-blue" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="1440" y2="0">
                    <stop offset="0" stop-color="#3b82f6" stop-opacity="0" />
                    <stop offset="0.16" stop-color="#3b82f6" stop-opacity="0.6" />
                    <stop offset="0.5" stop-color="#3b82f6" stop-opacity="0.2" />
                    <stop offset="0.84" stop-color="#3b82f6" stop-opacity="0.6" />
                    <stop offset="1" stop-color="#3b82f6" stop-opacity="0" />
                </linearGradient>
                <linearGradient id="pf-gradient-gold" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="1440" y2="0">
                    <stop offset="0" stop-color="#f2c94c" stop-opacity="0" />
                    <stop offset="0.15" stop-color="#f2c94c" stop-opacity="0.6" />
                    <stop offset="0.5" stop-color="#f2c94c" stop-opacity="0.14" />
                    <stop offset="0.85" stop-color="#f2c94c" stop-opacity="0.6" />
                    <stop offset="1" stop-color="#f2c94c" stop-opacity="0" />
                </linearGradient>
                <linearGradient id="pf-gradient-streak" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="1440" y2="0">
                    <stop offset="0" stop-color="#e0f7ff" stop-opacity="0.2" />
                    <stop offset="0.18" stop-color="#e0f7ff" stop-opacity="1" />
                    <stop offset="0.5" stop-color="#e0f7ff" stop-opacity="0.35" />
                    <stop offset="0.82" stop-color="#e0f7ff" stop-opacity="1" />
                    <stop offset="1" stop-color="#e0f7ff" stop-opacity="0.2" />
                </linearGradient>
                <linearGradient id="pf-gradient-streak-gold" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="1440" y2="0">
                    <stop offset="0" stop-color="#fde68a" stop-opacity="0.2" />
                    <stop offset="0.18" stop-color="#fde68a" stop-opacity="1" />
                    <stop offset="0.5" stop-color="#fde68a" stop-opacity="0.3" />
                    <stop offset="0.82" stop-color="#fde68a" stop-opacity="1" />
                    <stop offset="1" stop-color="#fde68a" stop-opacity="0.2" />
                </linearGradient>
            </defs>
        </svg>

        <!-- Atmosphere: navy wash and slow-moving radial light -->
        <div class="pf-atmosphere"></div>
        <div class="pf-glow pf-glow--cyan"></div>
        <div class="pf-glow pf-glow--royal"></div>
        <div class="pf-glow pf-glow--gold"></div>

        <!-- Geometry: technical grid, perspective floor, orbital rings, hexagons -->
        <div class="pf-grid"></div>
        <div class="pf-floor"><div class="pf-floor__plane"></div></div>

        <div class="pf-orbit pf-orbit--top">
            <span class="pf-orbit__ring pf-orbit__ring--outer"></span>
            <span class="pf-orbit__ring pf-orbit__ring--dashed"></span>
            <span class="pf-orbit__ring pf-orbit__ring--inner"></span>
        </div>
        <div class="pf-orbit pf-orbit--bottom">
            <span class="pf-orbit__ring pf-orbit__ring--outer"></span>
            <span class="pf-orbit__ring pf-orbit__ring--dashed"></span>
        </div>

        <svg v-for="side in ['left', 'right']" :key="side" class="pf-hex" :class="`pf-hex--${side}`" viewBox="0 0 200 210" fill="none">
            <polygon
                v-for="hexagon in hexagons"
                :key="hexagon.key"
                :points="hexagon.points"
                class="pf-hex__cell"
                :class="{ 'pf-hex__cell--gold': hexagon.gold, 'pf-hex__cell--filled': hexagon.filled }"
            />
        </svg>

        <!-- Flowing digital lines with light streaks -->
        <svg
            v-for="layer in flowLayers"
            :key="layer.key"
            class="pf-flow"
            :class="`pf-flow--${layer.key}`"
            viewBox="0 0 1440 900"
            preserveAspectRatio="xMidYMid slice"
            fill="none"
        >
            <template v-for="(line, index) in layer.lines" :key="`${layer.key}-${index}`">
                <path v-if="line.glows" :d="line.path" :stroke="`url(#${line.gradient})`" stroke-width="7" class="pf-flow__glow" />
                <path :d="line.path" :stroke="`url(#${line.gradient})`" :stroke-width="line.strokeWidth" />
                <template v-if="line.streak">
                    <path
                        :d="line.path"
                        :stroke="`url(#${line.streak.gradient})`"
                        stroke-width="6"
                        pathLength="100"
                        class="pf-streak pf-streak--halo"
                        :style="{ '--pf-duration': `${line.streak.duration}s`, '--pf-delay': `${line.streak.delay}s` }"
                    />
                    <path
                        :d="line.path"
                        :stroke="`url(#${line.streak.gradient})`"
                        stroke-width="1.8"
                        pathLength="100"
                        class="pf-streak"
                        :style="{ '--pf-duration': `${line.streak.duration}s`, '--pf-delay': `${line.streak.delay}s` }"
                    />
                </template>
            </template>
        </svg>

        <!-- Network clusters: connected people and knowledge -->
        <svg v-for="cluster in networkClusters" :key="cluster.key" class="pf-net" :class="`pf-net--${cluster.key}`" viewBox="0 0 360 520" fill="none">
            <g :transform="cluster.mirrored ? 'matrix(-1 0 0 1 360 0)' : undefined">
                <path
                    v-for="link in networkLinks"
                    :key="link.key"
                    :d="link.path"
                    class="pf-net__link"
                    :class="{ 'pf-net__link--blink': link.blinks }"
                    :style="link.style"
                />
                <path
                    v-for="signal in networkSignals"
                    :key="signal.key"
                    :d="signal.path"
                    pathLength="100"
                    class="pf-streak pf-net__signal"
                    :style="signal.style"
                />
                <circle
                    v-for="pulse in networkPulses"
                    :key="pulse.key"
                    :cx="pulse.x"
                    :cy="pulse.y"
                    r="9"
                    class="pf-net__halo"
                    :class="{ 'pf-net__halo--gold': pulse.gold }"
                    :style="pulse.style"
                />
                <circle
                    v-for="node in networkNodes"
                    :key="`node-${node.x}-${node.y}`"
                    :cx="node.x"
                    :cy="node.y"
                    :r="node.gold ? 3.2 : 2.6"
                    class="pf-net__node"
                    :class="{ 'pf-net__node--gold': node.gold }"
                />
            </g>
        </svg>

        <!-- Light: beams, edge sweeps, a holographic scan line -->
        <div class="pf-beams">
            <span class="pf-beam-track pf-beam-track--cyan"><span class="pf-beam"></span></span>
            <span class="pf-beam-track pf-beam-track--gold"><span class="pf-beam pf-beam--gold"></span></span>
            <span class="pf-beam-track pf-beam-track--low"><span class="pf-beam"></span></span>
            <span class="pf-beam-track pf-beam-track--sheen"><span class="pf-beam pf-beam--sheen"></span></span>
        </div>
        <span class="pf-rail pf-rail--left"><span class="pf-sweep"></span></span>
        <span class="pf-rail pf-rail--right"><span class="pf-sweep pf-sweep--gold"></span></span>
        <span class="pf-scan"></span>

        <!-- Floating particles -->
        <div class="pf-particles">
            <span
                v-for="particle in particles"
                :key="particle.key"
                class="pf-particle"
                :class="`pf-particle--${particle.tone}`"
                :style="particle.style"
            ></span>
        </div>

        <!-- Calm zone behind the form column, then a soft vignette -->
        <div class="pf-veil"></div>
        <div class="pf-vignette"></div>
    </div>
</template>

<style scoped>
/* ── Frame ───────────────────────────────────────────────────────────────
   Fixed behind the page, never takes a click or scroll, never adds a scrollbar.
   --pf-focus is half the form column's width (max-w-2xl / lg:3xl / xl:4xl). */
.pf-backdrop {
    --pf-focus: 21rem;
    --pf-veil: 0.6;
    position: fixed;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    pointer-events: none;
    contain: strict;
}
@media (min-width: 1024px) {
    .pf-backdrop {
        --pf-focus: 24rem;
    }
}
@media (min-width: 1280px) {
    .pf-backdrop {
        --pf-focus: 28rem;
    }
}
.pf-backdrop--md {
    --pf-focus: 14rem;
}
.pf-backdrop > * {
    position: absolute;
}
.pf-defs {
    width: 0;
    height: 0;
    overflow: hidden;
}

/* ── Atmosphere ─────────────────────────────────────────────────────────── */
.pf-atmosphere {
    inset: 0;
    background:
        radial-gradient(90% 60% at 50% 0%, rgba(37, 99, 235, 0.28), transparent 70%),
        linear-gradient(180deg, rgba(3, 14, 44, 0.72) 0%, rgba(5, 26, 79, 0.58) 45%, rgba(2, 10, 34, 0.84) 100%);
}
.pf-glow {
    border-radius: 50%;
    will-change: transform;
}
.pf-glow--cyan {
    top: -24vmax;
    left: -22vmax;
    width: 60vmax;
    height: 60vmax;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.26), rgba(56, 189, 248, 0) 62%);
    animation: pf-glow-cyan 28s ease-in-out infinite alternate;
}
.pf-glow--royal {
    right: -26vmax;
    bottom: -30vmax;
    width: 70vmax;
    height: 70vmax;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.4), rgba(37, 99, 235, 0) 62%);
    animation: pf-glow-royal 34s ease-in-out infinite alternate;
}
.pf-glow--gold {
    bottom: -16vmax;
    left: 4vw;
    width: 34vmax;
    height: 34vmax;
    background: radial-gradient(circle, rgba(242, 201, 76, 0.16), rgba(242, 201, 76, 0) 64%);
    animation: pf-glow-gold 26s ease-in-out infinite alternate;
}

/* ── Geometry ───────────────────────────────────────────────────────────── */
.pf-grid {
    inset: 0;
    background-image:
        linear-gradient(rgba(148, 197, 255, 0.07) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 197, 255, 0.07) 1px, transparent 1px);
    background-size: 44px 44px;
    -webkit-mask-image: radial-gradient(ellipse 70% 65% at 50% 45%, transparent 30%, #000 95%);
    mask-image: radial-gradient(ellipse 70% 65% at 50% 45%, transparent 30%, #000 95%);
}

.pf-floor {
    right: -70%;
    bottom: 0;
    left: -70%;
    height: 36vh;
    overflow: hidden;
    -webkit-mask-image: linear-gradient(to top, #000 0%, rgba(0, 0, 0, 0.55) 40%, transparent 92%);
    mask-image: linear-gradient(to top, #000 0%, rgba(0, 0, 0, 0.55) 40%, transparent 92%);
}
.pf-floor__plane {
    position: absolute;
    right: 0;
    bottom: -56px;
    left: 0;
    height: 400%;
    transform: perspective(700px) rotateX(56deg);
    transform-origin: 50% 100%;
    background-image:
        linear-gradient(rgba(56, 189, 248, 0.34) 1px, transparent 1px), linear-gradient(90deg, rgba(56, 189, 248, 0.34) 1px, transparent 1px);
    background-position: center bottom;
    background-size: 56px 56px;
    animation: pf-floor 5s linear infinite;
    will-change: transform;
}

.pf-orbit {
    aspect-ratio: 1;
}
.pf-orbit--top {
    top: -26vmax;
    right: -18vmax;
    width: 56vmax;
    transform: perspective(1400px) rotateX(62deg) rotateZ(-22deg);
}
.pf-orbit--bottom {
    bottom: -12vmax;
    left: -14vmax;
    width: 36vmax;
    transform: perspective(1200px) rotateX(58deg) rotateZ(28deg);
}
.pf-orbit__ring {
    position: absolute;
    border: 1px solid rgba(125, 211, 252, 0.24);
    border-radius: 50%;
    animation: pf-spin 70s linear infinite;
    will-change: transform;
}
.pf-orbit__ring--outer {
    inset: 0;
    box-shadow:
        0 0 28px rgba(56, 189, 248, 0.14),
        inset 0 0 28px rgba(56, 189, 248, 0.08);
}
.pf-orbit__ring--dashed {
    inset: 12%;
    border: 1px dashed rgba(96, 165, 250, 0.36);
    animation-duration: 110s;
    animation-direction: reverse;
}
.pf-orbit__ring--inner {
    inset: 25%;
    border-color: rgba(242, 201, 76, 0.26);
    animation-duration: 48s;
}
.pf-orbit__ring--outer::before,
.pf-orbit__ring--inner::before {
    position: absolute;
    top: -3px;
    left: 50%;
    width: 6px;
    height: 6px;
    margin-left: -3px;
    border-radius: 50%;
    background: #e0f7ff;
    box-shadow: 0 0 10px 3px rgba(56, 189, 248, 0.8);
    content: '';
}
.pf-orbit__ring--inner::before {
    background: #fde68a;
    box-shadow: 0 0 10px 3px rgba(242, 201, 76, 0.7);
}

.pf-hex {
    animation: pf-float 18s ease-in-out infinite alternate;
    will-change: transform, opacity;
}
.pf-hex--left {
    bottom: 16vh;
    left: 7vw;
    width: clamp(120px, 11vw, 190px);
}
.pf-hex--right {
    top: 14vh;
    right: 6vw;
    width: clamp(100px, 9vw, 160px);
    animation-delay: -9s;
    animation-direction: alternate-reverse;
}
.pf-hex__cell {
    stroke: rgba(125, 211, 252, 0.4);
    stroke-width: 1;
}
.pf-hex__cell--filled {
    fill: rgba(56, 189, 248, 0.06);
}
.pf-hex__cell--gold {
    stroke: rgba(242, 201, 76, 0.5);
}

/* ── Flowing lines ──────────────────────────────────────────────────────── */
.pf-flow {
    inset: -4%;
    width: 108%;
    height: 108%;
    stroke-linecap: round;
    will-change: transform;
}
.pf-flow--front {
    animation: pf-wave-front 26s ease-in-out infinite;
}
.pf-flow--back {
    opacity: 0.85;
    animation: pf-wave-back 34s ease-in-out infinite;
}
.pf-flow__glow {
    opacity: 0.12;
}

/* A short dash that runs the full path and rests off its end before looping (pathLength = 100). */
.pf-streak {
    stroke-dasharray: 7 193;
    stroke-dashoffset: 7;
    animation: pf-streak var(--pf-duration) linear var(--pf-delay) infinite;
}
.pf-streak--halo {
    opacity: 0.22;
}

/* ── Network ────────────────────────────────────────────────────────────── */
.pf-net {
    width: clamp(200px, 23vw, 340px);
    will-change: transform;
}
.pf-net--left {
    top: 7vh;
    left: 1vw;
    animation: pf-drift-a 24s ease-in-out infinite alternate;
}
.pf-net--right {
    --pf-offset: -4s;
    right: 1vw;
    bottom: 5vh;
    animation: pf-drift-b 28s ease-in-out infinite alternate;
}
.pf-net__link {
    stroke: rgba(125, 211, 252, 0.3);
    stroke-width: 0.8;
}
.pf-net__link--blink {
    animation: pf-blink var(--pf-duration) ease-in-out calc(var(--pf-delay) + var(--pf-offset, 0s)) infinite;
}
.pf-net__signal {
    stroke: #e0f7ff;
    stroke-width: 2;
    stroke-linecap: round;
    animation-delay: calc(var(--pf-delay) + var(--pf-offset, 0s));
}
.pf-net__node {
    fill: #bae6fd;
}
.pf-net__node--gold {
    fill: #fcd34d;
}
.pf-net__halo {
    fill: rgba(56, 189, 248, 0.4);
    transform-box: fill-box;
    transform-origin: center;
    animation: pf-pulse 4.8s ease-out calc(var(--pf-delay) + var(--pf-offset, 0s)) infinite;
}
.pf-net__halo--gold {
    fill: rgba(242, 201, 76, 0.4);
}

/* ── Light ──────────────────────────────────────────────────────────────── */
.pf-beams {
    inset: 0;
}
.pf-beam-track {
    position: absolute;
    left: 0;
    width: 100%;
    height: 0;
}
.pf-beam-track--cyan {
    top: 15%;
    --pf-duration: 12s;
    --pf-delay: -3s;
}
.pf-beam-track--gold {
    top: 82%;
    transform: rotate(-12deg);
    --pf-duration: 17s;
    --pf-delay: -9s;
}
.pf-beam-track--low {
    top: 63%;
    transform: rotate(6deg);
    --pf-duration: 21s;
    --pf-delay: -15s;
}
.pf-beam-track--sheen {
    top: 38%;
    transform: rotate(-30deg);
    --pf-duration: 24s;
    --pf-delay: -6s;
}
.pf-beam {
    position: absolute;
    top: -1px;
    left: 0;
    width: 34vw;
    height: 2px;
    border-radius: 999px;
    background: linear-gradient(90deg, transparent, rgba(125, 211, 252, 0.1) 25%, rgba(165, 243, 252, 0.85) 80%, #fff 97%, transparent);
    box-shadow: 0 0 10px rgba(56, 189, 248, 0.45);
    opacity: 0;
    animation: pf-beam var(--pf-duration) linear var(--pf-delay) infinite;
    will-change: transform, opacity;
}
.pf-beam--gold {
    width: 26vw;
    background: linear-gradient(90deg, transparent, rgba(242, 201, 76, 0.1) 25%, rgba(253, 230, 138, 0.85) 80%, #fffbeb 97%, transparent);
    box-shadow: 0 0 10px rgba(242, 201, 76, 0.4);
}
.pf-beam--sheen {
    top: -70px;
    width: 52vw;
    height: 140px;
    background: linear-gradient(90deg, transparent, rgba(186, 230, 253, 0.06) 45%, rgba(224, 247, 255, 0.1) 55%, transparent);
    box-shadow: none;
}

.pf-rail {
    top: 0;
    bottom: 0;
    width: 1px;
    background: linear-gradient(to bottom, transparent, rgba(125, 211, 252, 0.18) 20%, rgba(125, 211, 252, 0.18) 80%, transparent);
}
.pf-rail--left {
    left: 22px;
}
.pf-rail--right {
    right: 22px;
    background: linear-gradient(to bottom, transparent, rgba(242, 201, 76, 0.14) 20%, rgba(242, 201, 76, 0.14) 80%, transparent);
}
.pf-sweep {
    position: absolute;
    top: 0;
    left: -1px;
    width: 3px;
    height: 26vh;
    border-radius: 999px;
    background: linear-gradient(to bottom, transparent, rgba(165, 243, 252, 0.95), transparent);
    box-shadow: 0 0 12px rgba(56, 189, 248, 0.55);
    opacity: 0;
    animation: pf-sweep 11s ease-in-out infinite;
    will-change: transform, opacity;
}
.pf-sweep--gold {
    background: linear-gradient(to bottom, transparent, rgba(253, 230, 138, 0.9), transparent);
    box-shadow: 0 0 12px rgba(242, 201, 76, 0.5);
    animation-duration: 15s;
    animation-delay: -6s;
}

.pf-scan {
    top: 0;
    left: 0;
    width: 100%;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(125, 211, 252, 0.35) 18%,
        transparent 42%,
        transparent 58%,
        rgba(125, 211, 252, 0.35) 82%,
        transparent
    );
    opacity: 0;
    animation: pf-scan 16s linear infinite;
    will-change: transform, opacity;
}

/* ── Particles ──────────────────────────────────────────────────────────── */
.pf-particles {
    inset: 0;
}
.pf-particle {
    position: absolute;
    width: var(--pf-size);
    height: var(--pf-size);
    border-radius: 50%;
    background: #a5f3fc;
    box-shadow: 0 0 calc(var(--pf-size) * 3) rgba(56, 189, 248, 0.8);
    opacity: 0.5;
    animation: pf-particle var(--pf-duration) ease-in-out var(--pf-delay) infinite;
    will-change: transform, opacity;
}
.pf-particle--blue {
    background: #93c5fd;
    box-shadow: 0 0 calc(var(--pf-size) * 3) rgba(59, 130, 246, 0.8);
}
.pf-particle--gold {
    background: #fde68a;
    box-shadow: 0 0 calc(var(--pf-size) * 3) rgba(242, 201, 76, 0.75);
}

/* ── Calm zone and vignette ─────────────────────────────────────────────── */
.pf-veil {
    inset: 0;
    background: linear-gradient(
        90deg,
        rgba(3, 14, 44, 0) calc(50% - var(--pf-focus) - 12rem),
        rgba(3, 14, 44, var(--pf-veil)) calc(50% - var(--pf-focus) + 2rem),
        rgba(3, 14, 44, var(--pf-veil)) calc(50% + var(--pf-focus) - 2rem),
        rgba(3, 14, 44, 0) calc(50% + var(--pf-focus) + 12rem)
    );
}
.pf-vignette {
    inset: 0;
    background: radial-gradient(ellipse 120% 90% at 50% 45%, transparent 55%, rgba(2, 8, 26, 0.5) 100%);
}

/* ── Keyframes (transform and opacity only, except the SVG dash offsets) ── */
@keyframes pf-glow-cyan {
    to {
        transform: translate3d(8vmax, 6vmax, 0) scale(1.12);
    }
}
@keyframes pf-glow-royal {
    to {
        transform: translate3d(-10vmax, -6vmax, 0) scale(1.08);
    }
}
@keyframes pf-glow-gold {
    from {
        opacity: 0.6;
    }
    to {
        transform: translate3d(12vw, -4vh, 0);
        opacity: 1;
    }
}
@keyframes pf-floor {
    from {
        transform: perspective(700px) rotateX(56deg) translate3d(0, 0, 0);
    }
    to {
        transform: perspective(700px) rotateX(56deg) translate3d(0, 56px, 0);
    }
}
@keyframes pf-spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes pf-float {
    from {
        transform: translate3d(0, 0, 0) rotate(0deg);
        opacity: 0.55;
    }
    to {
        transform: translate3d(0, -2.5vh, 0) rotate(6deg);
        opacity: 0.95;
    }
}
@keyframes pf-wave-front {
    0%,
    100% {
        transform: translate3d(0, 0, 0) scaleY(1);
    }
    50% {
        transform: translate3d(-1.5%, 1.2%, 0) scaleY(1.05);
    }
}
@keyframes pf-wave-back {
    0%,
    100% {
        transform: translate3d(0, 0, 0) scaleY(1.04);
    }
    50% {
        transform: translate3d(1.8%, -1%, 0) scaleY(0.97);
    }
}
@keyframes pf-streak {
    to {
        stroke-dashoffset: -193;
    }
}
@keyframes pf-drift-a {
    to {
        transform: translate3d(1.5vw, 2vh, 0);
    }
}
@keyframes pf-drift-b {
    to {
        transform: translate3d(-1.5vw, -2.5vh, 0);
    }
}
@keyframes pf-blink {
    0%,
    100% {
        opacity: 0;
    }
    50% {
        opacity: 1;
    }
}
@keyframes pf-pulse {
    0% {
        transform: scale(0.4);
        opacity: 0.8;
    }
    70%,
    100% {
        transform: scale(1.4);
        opacity: 0;
    }
}
@keyframes pf-beam {
    0% {
        transform: translate3d(-40vw, 0, 0);
        opacity: 0;
    }
    5%,
    36% {
        opacity: 1;
    }
    42%,
    100% {
        transform: translate3d(130vw, 0, 0);
        opacity: 0;
    }
}
@keyframes pf-sweep {
    0% {
        transform: translate3d(0, -30vh, 0);
        opacity: 0;
    }
    8%,
    46% {
        opacity: 1;
    }
    54%,
    100% {
        transform: translate3d(0, 110vh, 0);
        opacity: 0;
    }
}
@keyframes pf-scan {
    0% {
        transform: translate3d(0, -2vh, 0);
        opacity: 0;
    }
    10%,
    85% {
        opacity: 1;
    }
    100% {
        transform: translate3d(0, 102vh, 0);
        opacity: 0;
    }
}
@keyframes pf-particle {
    0% {
        transform: translate3d(0, 0, 0) scale(0.5);
        opacity: 0;
    }
    20%,
    80% {
        opacity: 1;
    }
    100% {
        transform: translate3d(var(--pf-dx), var(--pf-dy), 0) scale(1);
        opacity: 0;
    }
}

/* ── Tablets: fewer particles, no sheen ─────────────────────────────────── */
@media (max-width: 1023px) {
    .pf-particle:nth-child(n + 15),
    .pf-beam-track--sheen {
        display: none;
    }
}

/* ── Phones: the column fills the screen, so keep only a light, calm layer ─ */
@media (max-width: 639px) {
    .pf-backdrop {
        --pf-veil: 0.3;
    }
    .pf-particle:nth-child(n + 11),
    .pf-flow--back,
    .pf-hex,
    .pf-orbit--bottom,
    .pf-beam-track--low,
    .pf-rail,
    .pf-scan {
        display: none;
    }
    .pf-net {
        width: 56vw;
        opacity: 0.7;
    }
    .pf-floor {
        height: 22vh;
    }
}

/* ── Reduced motion: a still composition, nothing moves ─────────────────── */
@media (prefers-reduced-motion: reduce) {
    .pf-backdrop *,
    .pf-backdrop *::before,
    .pf-backdrop *::after {
        animation: none !important;
    }
    .pf-beams,
    .pf-rail .pf-sweep,
    .pf-scan,
    .pf-streak {
        display: none;
    }
}
</style>
