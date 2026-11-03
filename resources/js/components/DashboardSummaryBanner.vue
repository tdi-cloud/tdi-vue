<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CalendarCheck, ClipboardList, GraduationCap, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    // Deferred prop: undefined until its follow-up request finishes, so the tiles show a pulsing placeholder.
    summary?: {
        employees: number;
        programs: number;
        active_batches: number;
        pending_submissions: number;
    };
    firstName?: string | null;
}>();

const page = usePage<SharedData>();

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
});

// Prefer the full first name from the employee record; fall back to the first word of the account name
const firstName = computed(() => props.firstName || page.props.auth?.user?.name?.split(' ')[0] || '');

// TEMPORARY CHRISTMAS THEME — EASILY REVERTIBLE
// Set to false after the season to restore the complete default banner design.
const isChristmasTheme = true;

const stats = computed(() => [
    { label: 'Employees Monitored', value: props.summary?.employees, icon: Users, accent: 'blue' },
    { label: 'Programs Conducted', value: props.summary?.programs, icon: GraduationCap, accent: 'green' },
    { label: 'Active Batches', value: props.summary?.active_batches, icon: CalendarCheck, accent: 'gold' },
    { label: 'Pending Submissions', value: props.summary?.pending_submissions, icon: ClipboardList, accent: 'amber' },
]);

// Decorative-only scene data. Deterministic pseudo-random values keep the layout stable between renders.
const seeded = (index: number, salt: number): number => {
    const value = Math.sin((index + 1) * salt) * 10000;
    return value - Math.floor(value);
};

const snowflakes = Array.from({ length: 30 }, (_, index) => {
    const depth = seeded(index, 12.9898);
    return {
        left: `${(seeded(index, 78.233) * 100).toFixed(2)}%`,
        '--flake-size': `${(2.5 + depth * 4).toFixed(1)}px`,
        '--flake-opacity': (0.4 + depth * 0.55).toFixed(2),
        '--flake-fall': `${(16 - depth * 7).toFixed(1)}s`,
        '--flake-delay': `${(-seeded(index, 39.425) * 16).toFixed(1)}s`,
        '--flake-drift': `${((seeded(index, 4.1414) - 0.5) * 40).toFixed(0)}px`,
        '--flake-sway': `${(3 + seeded(index, 9.73) * 3).toFixed(1)}s`,
    };
});

// Large six-armed crystals that rotate while they fall
const crystals = Array.from({ length: 10 }, (_, index) => {
    const depth = seeded(index, 21.17);
    return {
        left: `${(4 + seeded(index, 57.3) * 90).toFixed(2)}%`,
        '--crystal-size': `${(14 + depth * 20).toFixed(0)}px`,
        '--crystal-opacity': (0.35 + depth * 0.5).toFixed(2),
        '--crystal-fall': `${(26 - depth * 10).toFixed(1)}s`,
        '--crystal-delay': `${(-seeded(index, 8.91) * 26).toFixed(1)}s`,
        '--crystal-spin': `${(9 + seeded(index, 2.37) * 9).toFixed(1)}s`,
        '--crystal-drift': `${((seeded(index, 6.61) - 0.5) * 50).toFixed(0)}px`,
    };
});

// Big soft snow particles floating horizontally across the banner
const drifters = [
    { top: '34%', '--drift-size': '12px', '--drift-duration': '38s', '--drift-delay': '-6s' },
    { top: '62%', '--drift-size': '9px', '--drift-duration': '46s', '--drift-delay': '-24s' },
    { top: '18%', '--drift-size': '7px', '--drift-duration': '52s', '--drift-delay': '-38s' },
];

const sparkles = [
    { isDesktopOnly: false, style: { top: '20%', left: '48%', '--sparkle-size': '24px', '--sparkle-delay': '0s' } },
    { isDesktopOnly: false, style: { top: '14%', left: '68%', '--sparkle-size': '18px', '--sparkle-delay': '-1.3s' } },
    { isDesktopOnly: true, style: { top: '56%', left: '58%', '--sparkle-size': '14px', '--sparkle-delay': '-2.1s' } },
    { isDesktopOnly: false, style: { top: '72%', left: '3%', '--sparkle-size': '20px', '--sparkle-delay': '-0.7s' } },
    { isDesktopOnly: true, style: { top: '40%', left: '76%', '--sparkle-size': '13px', '--sparkle-delay': '-2.8s' } },
    { isDesktopOnly: true, style: { top: '10%', left: '30%', '--sparkle-size': '12px', '--sparkle-delay': '-1.8s' } },
];

const bokehLights = [
    { top: '-10%', left: '40%', '--bokeh-size': '90px', '--bokeh-color': '250, 204, 120', '--bokeh-duration': '19s' },
    { top: '55%', left: '-4%', '--bokeh-size': '110px', '--bokeh-color': '225, 29, 72', '--bokeh-duration': '23s' },
    { top: '20%', left: '62%', '--bokeh-size': '70px', '--bokeh-color': '74, 222, 128', '--bokeh-duration': '17s' },
    { top: '70%', left: '48%', '--bokeh-size': '80px', '--bokeh-color': '165, 216, 255', '--bokeh-duration': '21s' },
    { top: '-20%', left: '12%', '--bokeh-size': '64px', '--bokeh-color': '252, 211, 77', '--bokeh-duration': '25s' },
    { top: '40%', left: '86%', '--bokeh-size': '100px', '--bokeh-color': '255, 90, 110', '--bokeh-duration': '20s' },
];

const lightSegments = 18;
const lightColors = ['red', 'green', 'gold', 'blue'];

// Scalloped wire: one quadratic dip per bulb, drawn in a 100×12 viewBox stretched to the banner width
const lightWirePath = Array.from({ length: lightSegments }, (_, index) => {
    const step = 100 / lightSegments;
    return `Q ${(index * step + step / 2).toFixed(2)} 9 ${((index + 1) * step).toFixed(2)} 1`;
}).reduce((path, segment) => `${path} ${segment}`, 'M 0 1');

// Neighbouring bulbs sit in opposite blink phases so the string alternates
const lightBulbs = Array.from({ length: lightSegments }, (_, index) => ({
    color: lightColors[index % lightColors.length],
    style: {
        left: `${(((index + 0.5) * 100) / lightSegments).toFixed(2)}%`,
        '--bulb-delay': `${((index % 2 ? -1.4 : 0) - seeded(index, 3.17) * 0.4).toFixed(2)}s`,
        '--bulb-tilt': `${((seeded(index, 7.7) - 0.5) * 24).toFixed(0)}deg`,
    },
}));

const baubles = [
    { variant: 'red', style: { right: '272px', '--bauble-length': '64px', '--bauble-size': '28px', '--bauble-delay': '0s' } },
    { variant: 'silver', style: { right: '322px', '--bauble-length': '44px', '--bauble-size': '22px', '--bauble-delay': '-1.6s' } },
    { variant: 'gold', style: { right: '368px', '--bauble-length': '76px', '--bauble-size': '30px', '--bauble-delay': '-2.9s' } },
    { variant: 'green', style: { right: '418px', '--bauble-length': '52px', '--bauble-size': '24px', '--bauble-delay': '-0.9s' } },
];

// Tree positions are percentages of the tree box, placed inside each tier's silhouette
const treeLights = [
    [18, 50],
    [27, 38],
    [27, 52],
    [27, 64],
    [45, 26],
    [45, 42],
    [45, 58],
    [45, 74],
    [64, 20],
    [64, 35],
    [64, 50],
    [64, 66],
    [64, 80],
    [84, 14],
    [84, 30],
    [84, 48],
    [84, 66],
    [84, 84],
].map(([top, left], index) => ({
    color: lightColors[(index + 1) % lightColors.length],
    style: { top: `${top}%`, left: `${left}%`, '--light-delay': index % 2 ? '-0.8s' : '0s' },
}));

const treeOrnaments = [
    { top: 34, left: 45, variant: 'red', size: 11 },
    { top: 38, left: 58, variant: 'gold', size: 10 },
    { top: 55, left: 33, variant: 'silver', size: 12 },
    { top: 56, left: 62, variant: 'red', size: 13 },
    { top: 54, left: 48, variant: 'green', size: 10 },
    { top: 75, left: 29, variant: 'gold', size: 13 },
    { top: 75, left: 52, variant: 'silver', size: 12 },
    { top: 74, left: 70, variant: 'red', size: 12 },
    { top: 89, left: 38, variant: 'green', size: 12 },
    { top: 89, left: 76, variant: 'gold', size: 13 },
].map(({ top, left, variant, size }, index) => ({
    variant,
    style: { top: `${top}%`, left: `${left}%`, '--ornament-size': `${size}px`, '--ornament-delay': `${-index * 0.45}s` },
}));
</script>

<template>
    <div
        class="relative isolate overflow-hidden rounded-2xl bg-gradient-to-br from-blue-800 via-blue-700 to-sky-600 px-6 py-5 text-white shadow-md"
        :class="{ 'christmas-theme': isChristmasTheme }"
    >
        <!-- Decorative background texture — purely visual, sits behind existing content via negative z-index -->
        <div class="tdi-texture-institutional pointer-events-none absolute inset-0 z-[-1] opacity-[0.07]" aria-hidden="true"></div>

        <!-- TEMPORARY CHRISTMAS THEME — EASILY REVERTIBLE -->
        <div v-if="isChristmasTheme" class="christmas-scene pointer-events-none" aria-hidden="true">
            <!-- Background layer: ambient glow, bokeh, moon, sleigh -->
            <div class="christmas-aurora christmas-aurora--gold"></div>
            <div class="christmas-aurora christmas-aurora--red"></div>
            <div class="christmas-shimmer"></div>
            <span v-for="(bokeh, index) in bokehLights" :key="`bokeh-${index}`" class="christmas-bokeh" :style="bokeh"></span>

            <div class="christmas-moon"></div>

            <div class="christmas-sleigh-track">
                <svg class="christmas-sleigh" viewBox="0 0 132 30" fill="currentColor">
                    <circle cx="20" cy="7" r="3" />
                    <path d="M15 9 h10 l1 6 h-12 z" />
                    <path d="M4 12 q0 -4 3 -4 l2 0 l2 7 h20 q4 0 5 -6 h2 q-1 11 -9 11 h-18 q-7 0 -7 -8 z" />
                    <path d="M2 24 h32 q5 0 6 -4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    <path d="M37 12 Q 60 8 82 13" fill="none" stroke="currentColor" stroke-width="0.6" />
                    <g transform="translate(58 0)">
                        <ellipse cx="12" cy="14" rx="8" ry="3.4" />
                        <path d="M18 13 l3 -6 l2 1 l-3 6 z" />
                        <ellipse cx="23" cy="7" rx="2.6" ry="1.8" />
                        <path d="M21.5 5.5 l-1 -4 m1 2 l-2 -1 m3 2.5 l1 -4 m-0.5 2 l2 -1" fill="none" stroke="currentColor" stroke-width="0.7" />
                        <path
                            d="M6 16 l-4 5 M8 17 l0 6 M16 17 l2 5 M18 16 l4 4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.2"
                            stroke-linecap="round"
                        />
                    </g>
                    <g transform="translate(90 -1)">
                        <ellipse cx="12" cy="14" rx="8" ry="3.4" />
                        <path d="M18 13 l3 -6 l2 1 l-3 6 z" />
                        <ellipse cx="23" cy="7" rx="2.6" ry="1.8" />
                        <path d="M21.5 5.5 l-1 -4 m1 2 l-2 -1 m3 2.5 l1 -4 m-0.5 2 l2 -1" fill="none" stroke="currentColor" stroke-width="0.7" />
                        <path
                            d="M6 16 l-4 5 M8 17 l0 6 M16 17 l2 5 M18 16 l4 4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.2"
                            stroke-linecap="round"
                        />
                        <circle class="christmas-sleigh-nose" cx="25.6" cy="7.4" r="1.1" />
                    </g>
                </svg>
            </div>

            <!-- Middle layer: sparkles and snow -->
            <span
                v-for="(sparkle, index) in sparkles"
                :key="`sparkle-${index}`"
                class="christmas-sparkle"
                :class="{ 'christmas-sparkle--desktop': sparkle.isDesktopOnly }"
                :style="sparkle.style"
            ></span>

            <div class="christmas-snowfall">
                <span v-for="(flake, index) in snowflakes" :key="index" class="christmas-snowflake" :style="flake"></span>
            </div>

            <div class="christmas-crystals">
                <span v-for="(crystal, index) in crystals" :key="index" class="christmas-crystal" :style="crystal">
                    <i class="christmas-crystal-spin"></i>
                </span>
            </div>

            <div v-for="(drifter, index) in drifters" :key="`drifter-${index}`" class="christmas-drifter" :style="drifter">
                <i></i>
            </div>

            <!-- Foreground layer: decorated tree with gifts, set in its own reserved zone -->
            <div class="christmas-tree-group">
                <span class="christmas-pine christmas-pine--left"></span>
                <span class="christmas-pine christmas-pine--right"></span>
                <span class="christmas-tree-glow"></span>
                <div class="christmas-tree">
                    <span class="christmas-tree-star-rays"></span>
                    <span class="christmas-tree-star"></span>
                    <span class="christmas-tree-tier christmas-tree-tier--4"></span>
                    <span class="christmas-tree-tier christmas-tree-tier--3"></span>
                    <span class="christmas-tree-tier christmas-tree-tier--2"></span>
                    <span class="christmas-tree-tier christmas-tree-tier--1"></span>
                    <span class="christmas-tree-trunk"></span>
                    <span class="christmas-tree-garland christmas-tree-garland--1"></span>
                    <span class="christmas-tree-garland christmas-tree-garland--2"></span>
                    <span class="christmas-tree-garland christmas-tree-garland--3"></span>
                    <i
                        v-for="(light, index) in treeLights"
                        :key="`light-${index}`"
                        class="christmas-tree-light"
                        :class="`christmas-tree-light--${light.color}`"
                        :style="light.style"
                    ></i>
                    <i
                        v-for="(ornament, index) in treeOrnaments"
                        :key="`ornament-${index}`"
                        class="christmas-tree-ornament"
                        :class="`christmas-tree-ornament--${ornament.variant}`"
                        :style="ornament.style"
                    ></i>
                </div>
                <span class="christmas-snow-mound"></span>
                <span class="christmas-gift christmas-gift--ruby"><span class="christmas-gift-bow"></span></span>
                <span class="christmas-gift christmas-gift--emerald"><span class="christmas-gift-bow"></span></span>
                <span class="christmas-gift christmas-gift--champagne"><span class="christmas-gift-bow"></span></span>
                <span class="christmas-gift christmas-gift--ice"><span class="christmas-gift-bow"></span></span>
            </div>

            <!-- Light string and hanging baubles along the top edge -->
            <div class="christmas-lights">
                <svg class="christmas-lights-wire" viewBox="0 0 100 12" preserveAspectRatio="none">
                    <path :d="lightWirePath" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" />
                </svg>
                <span
                    v-for="(bulb, index) in lightBulbs"
                    :key="index"
                    class="christmas-bulb"
                    :class="`christmas-bulb--${bulb.color}`"
                    :style="bulb.style"
                ></span>
            </div>

            <span
                v-for="(bauble, index) in baubles"
                :key="`bauble-${index}`"
                class="christmas-bauble"
                :class="`christmas-bauble--${bauble.variant}`"
                :style="bauble.style"
            >
                <span class="christmas-bauble-ball"></span>
            </span>

            <div class="christmas-snowbank"></div>
            <div class="christmas-vignette"></div>
        </div>

        <div class="relative z-10" :class="{ 'christmas-content': isChristmasTheme }">
            <div :class="{ 'christmas-heading': isChristmasTheme }">
                <p v-if="isChristmasTheme" class="christmas-seasonal-label"><span aria-hidden="true">❄</span> Season of Joy &amp; Celebration</p>
                <p class="text-lg font-bold" :class="{ 'christmas-greeting sm:text-xl': isChristmasTheme }">
                    {{ greeting
                    }}<template v-if="firstName"
                        >, <span :class="{ 'christmas-name': isChristmasTheme }">{{ firstName }}</span></template
                    >
                    👋
                </p>
                <p v-if="isChristmasTheme" class="christmas-subtitle mt-0.5 text-xs text-white/85">
                    Celebrating achievements, inspiring growth, and spreading joy through learning and development.
                </p>
                <p v-else class="mt-0.5 text-sm text-white/80">Here's a quick look at what's happening across your programs today.</p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4" :aria-busy="!summary">
                <span v-if="!summary" role="status" class="sr-only">Loading summary…</span>
                <div
                    v-for="stat in stats"
                    :key="stat.label"
                    class="kpi-tile relative flex items-center gap-3 overflow-hidden rounded-xl bg-white/10 px-4 py-3 backdrop-blur-sm"
                    :class="[`kpi-tile--${stat.accent}`, isChristmasTheme ? ['christmas-stat-card', `christmas-stat-card--${stat.accent}`] : '']"
                >
                    <div
                        class="kpi-tile-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15"
                        :class="isChristmasTheme ? 'christmas-stat-icon' : ''"
                    >
                        <component :is="stat.icon" class="h-4.5 w-4.5" />
                    </div>
                    <div class="min-w-0">
                        <p v-if="stat.value !== undefined" class="text-lg font-extrabold tabular-nums leading-tight tracking-tight">
                            {{ stat.value.toLocaleString() }}
                        </p>
                        <span v-else class="my-1 block h-5 w-14 animate-pulse rounded bg-white/25" aria-hidden="true" />
                        <p class="truncate text-[11px] font-medium text-white/75">{{ stat.label }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* TDI Institutional Micro-Texture — the exact same fine grid + radial-fade
   technique used in the homepage "Digital Resources" section, tinted white for
   the banner's blue gradient. The least visible texture in the system. */
.tdi-texture-institutional {
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.6) 1px, transparent 1px);
    background-size: 32px 32px;
    -webkit-mask-image: radial-gradient(ellipse at center, black 0%, transparent 72%);
    mask-image: radial-gradient(ellipse at center, black 0%, transparent 72%);
}

/* KPI tiles — one unified system with a thin semantic accent line on top and a
   soft accent glow around the icon. Works with both the default and seasonal banner. */
.kpi-tile--blue {
    --kpi-accent: 56, 189, 248;
}
.kpi-tile--green {
    --kpi-accent: 16, 185, 129;
}
.kpi-tile--gold {
    --kpi-accent: 212, 167, 44;
}
.kpi-tile--amber {
    --kpi-accent: 245, 158, 11;
}
.kpi-tile {
    transition:
        transform 0.25s ease,
        border-color 0.25s ease,
        box-shadow 0.25s ease;
}
.kpi-tile::before {
    position: absolute;
    inset: 0 0 auto 0;
    height: 2px;
    background: linear-gradient(90deg, rgba(var(--kpi-accent), 0.95), rgba(var(--kpi-accent), 0) 85%);
    content: '';
    pointer-events: none;
}
.kpi-tile .kpi-tile-icon {
    box-shadow:
        0 0 0 1px rgba(var(--kpi-accent), 0.35),
        0 0 16px rgba(var(--kpi-accent), 0.28);
}

/* ==========================================================================
   TEMPORARY CHRISTMAS THEME — EASILY REVERTIBLE
   Midnight evergreen base, burgundy ambience from the left, warm gold light
   from the right. Everything decorative sits behind the z-10 content; the
   large tree gets its own reserved zone so it never covers the greeting or cards.
   ========================================================================== */
.christmas-theme {
    background:
        radial-gradient(34% 90% at 90% 40%, rgba(246, 196, 104, 0.34), transparent 70%),
        radial-gradient(40% 90% at 0% 0%, rgba(159, 18, 57, 0.46), transparent 70%),
        radial-gradient(38% 80% at 18% 120%, rgba(127, 29, 29, 0.34), transparent 70%),
        radial-gradient(50% 70% at 55% -20%, rgba(186, 230, 253, 0.12), transparent 70%),
        linear-gradient(120deg, #2a0a17 0%, #0d2a24 34%, #0a2230 66%, #0c1a2e 100%);
    box-shadow:
        0 16px 38px rgba(4, 16, 14, 0.38),
        inset 0 1px rgba(246, 214, 150, 0.2),
        inset 0 0 0 1px rgba(246, 214, 150, 0.08);
}

.christmas-scene,
.christmas-scene > * {
    position: absolute;
}

.christmas-scene {
    inset: 0;
    overflow: hidden;
}

/* --- Background: ambient glows, bokeh, golden sheen --- */
.christmas-aurora {
    border-radius: 50%;
    filter: blur(28px);
    animation: christmas-aurora 18s ease-in-out infinite alternate;
}
.christmas-aurora--gold {
    top: -10%;
    right: 2%;
    width: 36%;
    height: 100%;
    background: radial-gradient(circle, rgba(250, 204, 120, 0.3), transparent 65%);
}
.christmas-aurora--red {
    bottom: -30%;
    left: -6%;
    width: 42%;
    height: 100%;
    background: radial-gradient(circle, rgba(225, 29, 72, 0.26), transparent 65%);
    animation-duration: 22s;
    animation-direction: alternate-reverse;
}

.christmas-shimmer {
    inset: 0;
    background: linear-gradient(105deg, transparent 35%, rgba(255, 226, 160, 0.1) 47%, rgba(255, 244, 214, 0.16) 50%, transparent 62%);
    background-size: 250% 100%;
    animation: christmas-shimmer 11s ease-in-out infinite;
}

.christmas-bokeh {
    width: var(--bokeh-size);
    height: var(--bokeh-size);
    border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--bokeh-color), 0.22) 0%, rgba(var(--bokeh-color), 0.1) 45%, transparent 70%);
    box-shadow: inset 0 0 0 1px rgba(var(--bokeh-color), 0.08);
    animation: christmas-bokeh-drift var(--bokeh-duration) ease-in-out infinite alternate;
}

/* --- Large golden crescent moon in the upper-right --- */
.christmas-moon {
    top: 30px;
    right: 18px;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    box-shadow: inset -16px 6px 0 0 #fff0c4;
    filter: drop-shadow(0 0 14px rgba(255, 220, 150, 0.7));
    animation: christmas-float 7s ease-in-out infinite alternate;
}
.christmas-moon::before {
    position: absolute;
    inset: -46px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 214, 140, 0.22), transparent 65%);
    content: '';
    animation: christmas-glow-pulse 5s ease-in-out infinite alternate;
}

/* --- Sleigh silhouette crossing the distant sky --- */
.christmas-sleigh-track {
    top: 36%;
    left: 0;
    width: 100%;
    height: 30px;
    animation: christmas-sleigh-cross 46s linear infinite;
}
.christmas-sleigh {
    position: absolute;
    left: -160px;
    width: 124px;
    height: 28px;
    color: rgba(255, 236, 200, 0.26);
    filter: drop-shadow(0 0 5px rgba(255, 214, 140, 0.3));
    animation: christmas-sleigh-bob 3.5s ease-in-out infinite alternate;
}
.christmas-sleigh-nose {
    fill: #ff5a6b;
    filter: drop-shadow(0 0 2px #ff5a6b);
}

/* --- Large glowing four-point sparkles --- */
.christmas-sparkle {
    width: var(--sparkle-size);
    height: var(--sparkle-size);
    animation: christmas-twinkle 3.4s ease-in-out var(--sparkle-delay) infinite;
}
.christmas-sparkle::before,
.christmas-sparkle::after {
    position: absolute;
    content: '';
}
.christmas-sparkle::before {
    inset: -80%;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 226, 160, 0.4), transparent 60%);
}
.christmas-sparkle::after {
    inset: 0;
    background: linear-gradient(135deg, #ffffff, #ffe7a8 55%, #f6c453);
    clip-path: polygon(50% 0, 58% 42%, 100% 50%, 58% 58%, 50% 100%, 42% 58%, 0 50%, 42% 42%);
}

/* --- Small snow: each flake is a full-height column so translateY(100%) spans the banner --- */
.christmas-snowfall,
.christmas-crystals {
    inset: 0;
}
.christmas-snowflake {
    position: absolute;
    top: 0;
    width: var(--flake-size);
    height: 100%;
    animation:
        christmas-fall var(--flake-fall) linear var(--flake-delay) infinite,
        christmas-sway var(--flake-sway) ease-in-out var(--flake-delay) infinite alternate;
    will-change: transform;
}
.christmas-snowflake::before {
    position: absolute;
    top: 0;
    left: 0;
    width: var(--flake-size);
    height: var(--flake-size);
    border-radius: 50%;
    background: radial-gradient(circle, #ffffff 0%, rgba(235, 245, 255, 0.85) 45%, rgba(220, 238, 255, 0) 100%);
    opacity: var(--flake-opacity);
    content: '';
}

/* --- Large crystals: the column falls and sways, the inner element spins --- */
.christmas-crystal {
    position: absolute;
    top: 0;
    width: var(--crystal-size);
    height: 100%;
    opacity: var(--crystal-opacity);
    animation:
        christmas-fall var(--crystal-fall) linear var(--crystal-delay) infinite,
        christmas-crystal-sway calc(var(--crystal-fall) / 3) ease-in-out var(--crystal-delay) infinite alternate;
    will-change: transform;
}
.christmas-crystal-spin {
    position: absolute;
    top: 0;
    left: 0;
    width: var(--crystal-size);
    height: var(--crystal-size);
    filter: drop-shadow(0 0 4px rgba(200, 230, 255, 0.8));
    animation: christmas-spin var(--crystal-spin) linear infinite;
}
.christmas-crystal-spin::before,
.christmas-drifter i {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #ffffff, #d6ecff);
    -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round'%3E%3Cpath d='M12 1.5v21M12 5 9.6 2.6M12 5l2.4-2.4M12 19l-2.4 2.4M12 19l2.4 2.4M12 9 8.8 7.2M12 9l3.2-1.8M12 15l-3.2 1.8M12 15l3.2 1.8'/%3E%3Cpath transform='rotate(60 12 12)' d='M12 1.5v21M12 5 9.6 2.6M12 5l2.4-2.4M12 19l-2.4 2.4M12 19l2.4 2.4M12 9 8.8 7.2M12 9l3.2-1.8M12 15l-3.2 1.8M12 15l3.2 1.8'/%3E%3Cpath transform='rotate(120 12 12)' d='M12 1.5v21M12 5 9.6 2.6M12 5l2.4-2.4M12 19l-2.4 2.4M12 19l2.4 2.4M12 9 8.8 7.2M12 9l3.2-1.8M12 15l-3.2 1.8M12 15l3.2 1.8'/%3E%3C/svg%3E")
        center / contain no-repeat;
    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.5' stroke-linecap='round'%3E%3Cpath d='M12 1.5v21M12 5 9.6 2.6M12 5l2.4-2.4M12 19l-2.4 2.4M12 19l2.4 2.4M12 9 8.8 7.2M12 9l3.2-1.8M12 15l-3.2 1.8M12 15l3.2 1.8'/%3E%3Cpath transform='rotate(60 12 12)' d='M12 1.5v21M12 5 9.6 2.6M12 5l2.4-2.4M12 19l-2.4 2.4M12 19l2.4 2.4M12 9 8.8 7.2M12 9l3.2-1.8M12 15l-3.2 1.8M12 15l3.2 1.8'/%3E%3Cpath transform='rotate(120 12 12)' d='M12 1.5v21M12 5 9.6 2.6M12 5l2.4-2.4M12 19l-2.4 2.4M12 19l2.4 2.4M12 9 8.8 7.2M12 9l3.2-1.8M12 15l-3.2 1.8M12 15l3.2 1.8'/%3E%3C/svg%3E")
        center / contain no-repeat;
    content: '';
}

/* --- Larger crystals floating horizontally across the banner --- */
.christmas-drifter {
    left: 0;
    width: 100%;
    height: calc(var(--drift-size) * 2);
    animation: christmas-drift-across var(--drift-duration) linear var(--drift-delay) infinite;
}
.christmas-drifter i {
    left: -40px;
    right: auto;
    width: calc(var(--drift-size) * 2);
    height: calc(var(--drift-size) * 2);
    opacity: 0.55;
    animation: christmas-spin 12s linear infinite;
}

/* --- Light string along the top edge --- */
.christmas-lights {
    top: 0;
    right: 0;
    left: 0;
    height: 30px;
}
.christmas-lights-wire {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 20px;
    color: #1b3327;
    filter: drop-shadow(0 1px 0 rgba(255, 255, 255, 0.1));
}
.christmas-bulb {
    position: absolute;
    top: 8px;
    width: 10px;
    height: 15px;
    margin-left: -5px;
    border-radius: 50% 50% 50% 50% / 40% 40% 60% 60%;
    background: radial-gradient(circle at 38% 30%, #ffffff 0%, rgb(var(--bulb-color)) 42%, rgba(var(--bulb-color), 0.8) 100%);
    box-shadow:
        0 0 8px 2px rgba(var(--bulb-color), 0.85),
        0 0 22px 6px rgba(var(--bulb-color), 0.35);
    transform: rotate(var(--bulb-tilt));
    transform-origin: 50% -3px;
    animation: christmas-bulb-glow 2.8s ease-in-out var(--bulb-delay) infinite;
}
.christmas-bulb::before {
    position: absolute;
    top: -4px;
    left: 2px;
    width: 6px;
    height: 5px;
    border-radius: 1px;
    background: linear-gradient(90deg, #26352c, #4b5d50, #26352c);
    content: '';
}
.christmas-bulb--red {
    --bulb-color: 255, 77, 94;
}
.christmas-bulb--green {
    --bulb-color: 74, 222, 128;
}
.christmas-bulb--gold {
    --bulb-color: 252, 211, 77;
}
.christmas-bulb--blue {
    --bulb-color: 125, 200, 255;
}

/* --- Large hanging baubles that swing from the top edge --- */
.christmas-bauble {
    top: 0;
    width: var(--bauble-size);
    height: var(--bauble-length);
    transform-origin: 50% 0;
    animation: christmas-pendulum 4.5s ease-in-out var(--bauble-delay) infinite alternate;
}
.christmas-bauble::before {
    position: absolute;
    top: 6px;
    left: 50%;
    width: 1px;
    height: calc(100% - var(--bauble-size) - 6px);
    background: linear-gradient(rgba(246, 214, 150, 0.3), rgba(246, 214, 150, 0.85));
    content: '';
}
.christmas-bauble-ball {
    position: absolute;
    bottom: 0;
    left: 0;
    width: var(--bauble-size);
    height: var(--bauble-size);
    border-radius: 50%;
    background:
        radial-gradient(circle at 32% 28%, rgba(255, 255, 255, 0.95) 0 5%, rgba(255, 255, 255, 0) 26%),
        linear-gradient(180deg, transparent 43%, var(--bauble-band) 43% 57%, transparent 57%),
        radial-gradient(circle at 40% 35%, var(--bauble-light), var(--bauble-dark) 78%);
    box-shadow:
        0 0 20px var(--bauble-glow),
        inset -4px -6px 10px rgba(0, 0, 0, 0.3);
}
.christmas-bauble-ball::before {
    position: absolute;
    top: -5px;
    left: 50%;
    width: 9px;
    height: 6px;
    margin-left: -4.5px;
    border-radius: 2px 2px 1px 1px;
    background: linear-gradient(90deg, #a8740f, #ffe7a8, #a8740f);
    content: '';
}
.christmas-bauble--red {
    --bauble-light: #ff6b7d;
    --bauble-dark: #8f1230;
    --bauble-band: rgba(255, 221, 140, 0.85);
    --bauble-glow: rgba(255, 90, 110, 0.5);
}
.christmas-bauble--gold {
    --bauble-light: #ffe7a8;
    --bauble-dark: #a8740f;
    --bauble-band: rgba(200, 30, 58, 0.8);
    --bauble-glow: rgba(252, 211, 77, 0.55);
}
.christmas-bauble--green {
    --bauble-light: #6ee7a8;
    --bauble-dark: #0b5a3a;
    --bauble-band: rgba(255, 221, 140, 0.85);
    --bauble-glow: rgba(74, 222, 128, 0.45);
}
.christmas-bauble--silver {
    --bauble-light: #ffffff;
    --bauble-dark: #8796a8;
    --bauble-band: rgba(200, 30, 58, 0.75);
    --bauble-glow: rgba(210, 225, 245, 0.5);
}

/* --- Tree group: background pines, decorated tree, snow mound, gifts --- */
.christmas-tree-group {
    top: 6px;
    right: 8px;
    bottom: 0;
    width: 240px;
}
.christmas-tree-group > *,
.christmas-tree > * {
    position: absolute;
}
.christmas-pine {
    background: linear-gradient(180deg, #123b2c, #071f17);
    clip-path: polygon(50% 0, 66% 30%, 58% 30%, 80% 62%, 70% 62%, 100% 100%, 0 100%, 30% 62%, 20% 62%, 42% 30%, 34% 30%);
    opacity: 0.85;
}
.christmas-pine--left {
    bottom: 22px;
    left: 4px;
    width: 58px;
    height: 48%;
}
.christmas-pine--right {
    right: 10px;
    bottom: 26px;
    width: 50px;
    height: 40%;
}
.christmas-tree-glow {
    top: 10%;
    left: 50%;
    width: 220px;
    height: 220px;
    margin-left: -110px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(250, 204, 120, 0.34), transparent 62%);
    animation: christmas-glow-pulse 4s ease-in-out infinite alternate;
}
.christmas-tree {
    top: 24px;
    bottom: 16px;
    left: 50px;
    width: 140px;
    filter: drop-shadow(0 6px 12px rgba(0, 0, 0, 0.4));
}
.christmas-tree-tier {
    left: 50%;
    background:
        linear-gradient(0deg, rgba(240, 248, 255, 0.6) 0, rgba(240, 248, 255, 0) 16%),
        linear-gradient(90deg, rgba(0, 0, 0, 0.25) 0%, transparent 40%, rgba(255, 214, 140, 0.18) 75%, rgba(0, 0, 0, 0.15) 100%),
        linear-gradient(180deg, #23955c 0%, #11703f 55%, #0a4a2e 100%);
    clip-path: polygon(50% 0, 100% 88%, 88% 100%, 75% 90%, 62% 100%, 50% 90%, 38% 100%, 25% 90%, 12% 100%, 0 88%);
    transform: translateX(-50%);
}
.christmas-tree-tier--1 {
    top: 6%;
    width: 42%;
    height: 24%;
}
.christmas-tree-tier--2 {
    top: 21%;
    width: 62%;
    height: 27%;
}
.christmas-tree-tier--3 {
    top: 38%;
    width: 82%;
    height: 30%;
}
.christmas-tree-tier--4 {
    top: 56%;
    width: 100%;
    height: 34%;
}
.christmas-tree-trunk {
    bottom: 0;
    left: 50%;
    width: 16px;
    height: 12%;
    margin-left: -8px;
    border-radius: 2px;
    background: linear-gradient(90deg, #4a2a13, #7c4a24 55%, #4a2a13);
}
.christmas-tree-star {
    top: -10px;
    left: 50%;
    z-index: 3;
    width: 34px;
    height: 34px;
    margin-left: -17px;
    background: linear-gradient(135deg, #fffaf0, #f6c453 55%, #d99a1b);
    clip-path: polygon(50% 0, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
    filter: drop-shadow(0 0 8px rgba(255, 214, 120, 1));
    animation: christmas-star-pulse 2.8s ease-in-out infinite alternate;
}
.christmas-tree-star-rays {
    top: -30px;
    left: 50%;
    z-index: 2;
    width: 84px;
    height: 84px;
    margin-left: -42px;
    border-radius: 50%;
    background: repeating-conic-gradient(rgba(255, 226, 150, 0.45) 0deg 5deg, transparent 5deg 30deg);
    -webkit-mask-image: radial-gradient(circle, black 0 18%, transparent 68%);
    mask-image: radial-gradient(circle, black 0 18%, transparent 68%);
    animation: christmas-spin 26s linear infinite;
}
.christmas-tree-garland {
    z-index: 1;
    height: 12px;
    border-bottom: 2.5px dotted rgba(255, 224, 150, 0.95);
    border-radius: 0 0 50% 50% / 0 0 100% 100%;
    filter: drop-shadow(0 0 3px rgba(255, 210, 120, 0.8));
}
.christmas-tree-garland--1 {
    top: 26%;
    left: 32%;
    width: 36%;
    transform: rotate(6deg);
}
.christmas-tree-garland--2 {
    top: 45%;
    left: 22%;
    width: 56%;
    transform: rotate(-5deg);
}
.christmas-tree-garland--3 {
    top: 66%;
    left: 13%;
    width: 74%;
    transform: rotate(4deg);
}
.christmas-tree-light {
    z-index: 2;
    width: 6px;
    height: 6px;
    margin: -3px 0 0 -3px;
    border-radius: 50%;
    background: radial-gradient(circle, #ffffff 0 25%, rgb(var(--light-color)) 60%);
    box-shadow: 0 0 7px 2px rgba(var(--light-color), 0.75);
    animation: christmas-tree-blink 1.6s ease-in-out var(--light-delay) infinite;
}
.christmas-tree-light--red {
    --light-color: 255, 77, 94;
}
.christmas-tree-light--green {
    --light-color: 134, 239, 172;
}
.christmas-tree-light--gold {
    --light-color: 252, 211, 77;
}
.christmas-tree-light--blue {
    --light-color: 125, 200, 255;
}
.christmas-tree-ornament {
    z-index: 2;
    width: var(--ornament-size);
    height: var(--ornament-size);
    border-radius: 50%;
    background: radial-gradient(circle at 34% 30%, #ffffff 0 8%, var(--ornament-light) 30%, var(--ornament-dark) 100%);
    box-shadow: 0 0 8px var(--ornament-glow);
    translate: -50% -40%;
    transform-origin: 50% -3px;
    animation: christmas-ornament-swing 3s ease-in-out var(--ornament-delay) infinite alternate;
}
.christmas-tree-ornament::before {
    position: absolute;
    top: -3px;
    left: 50%;
    width: 4px;
    height: 3px;
    margin-left: -2px;
    border-radius: 1px;
    background: #e8c46a;
    content: '';
}
.christmas-tree-ornament--red {
    --ornament-light: #ff6b7d;
    --ornament-dark: #9b1532;
    --ornament-glow: rgba(255, 90, 110, 0.6);
}
.christmas-tree-ornament--gold {
    --ornament-light: #ffe29a;
    --ornament-dark: #b07d18;
    --ornament-glow: rgba(252, 211, 77, 0.6);
}
.christmas-tree-ornament--silver {
    --ornament-light: #ffffff;
    --ornament-dark: #8796a8;
    --ornament-glow: rgba(220, 232, 248, 0.6);
}
.christmas-tree-ornament--green {
    --ornament-light: #8af0b8;
    --ornament-dark: #0b5a3a;
    --ornament-glow: rgba(110, 231, 168, 0.5);
}

.christmas-snow-mound {
    right: -16px;
    bottom: -18px;
    left: -16px;
    height: 46px;
    border-radius: 50%;
    background: radial-gradient(ellipse at 50% 30%, rgba(245, 250, 255, 0.55), rgba(220, 236, 255, 0.18) 55%, transparent 72%);
}

/* --- Oversized gift boxes with lids, ribbons and floating bows --- */
.christmas-gift {
    border-radius: 3px;
    background:
        linear-gradient(90deg, transparent calc(50% - 3px), var(--ribbon) calc(50% - 3px) calc(50% + 3px), transparent calc(50% + 3px)),
        radial-gradient(circle at 25% 60%, rgba(255, 255, 255, 0.14) 0 2px, transparent 2.5px) 0 0 / 10px 10px,
        linear-gradient(160deg, var(--box-light), var(--box-dark));
    box-shadow:
        0 6px 12px rgba(0, 0, 0, 0.35),
        inset -6px 0 10px rgba(0, 0, 0, 0.18);
}
.christmas-gift::before {
    position: absolute;
    top: 0;
    right: -3px;
    left: -3px;
    height: 26%;
    border-radius: 3px;
    background:
        linear-gradient(90deg, transparent calc(50% - 3px), var(--ribbon) calc(50% - 3px) calc(50% + 3px), transparent calc(50% + 3px)),
        linear-gradient(180deg, var(--box-light), var(--box-dark));
    box-shadow: 0 3px 4px rgba(0, 0, 0, 0.3);
    content: '';
}
.christmas-gift-bow {
    position: absolute;
    top: -11px;
    left: 50%;
    width: 30px;
    height: 14px;
    margin-left: -15px;
    background: radial-gradient(circle at 50% 78%, var(--ribbon) 0 3.5px, transparent 4px);
    filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.3));
    animation: christmas-bow-float 3s ease-in-out infinite alternate;
}
.christmas-gift-bow::before,
.christmas-gift-bow::after {
    position: absolute;
    top: 0;
    width: 13px;
    height: 11px;
    border: 3px solid var(--ribbon);
    border-radius: 60% 40% 40% 60% / 60% 50% 50% 40%;
    content: '';
}
.christmas-gift-bow::before {
    left: 1px;
    transform: rotate(-22deg);
}
.christmas-gift-bow::after {
    right: 1px;
    transform: scaleX(-1) rotate(-22deg);
}
.christmas-gift--ruby {
    bottom: 6px;
    left: 0;
    width: 54px;
    height: 42px;
    --box-light: #ef3b57;
    --box-dark: #7f0f2a;
    --ribbon: #f6d38a;
}
.christmas-gift--emerald {
    bottom: 0;
    left: 40px;
    width: 38px;
    height: 29px;
    --box-light: #23a86a;
    --box-dark: #0a4f33;
    --ribbon: #ff5a6b;
}
.christmas-gift--emerald .christmas-gift-bow {
    animation-delay: -1s;
}
.christmas-gift--champagne {
    right: 4px;
    bottom: 6px;
    width: 48px;
    height: 38px;
    --box-light: #fbe3a6;
    --box-dark: #b8862a;
    --ribbon: #c81e3a;
}
.christmas-gift--champagne .christmas-gift-bow {
    animation-delay: -2s;
}
.christmas-gift--ice {
    right: 42px;
    bottom: 0;
    width: 32px;
    height: 24px;
    --box-light: #dbeefe;
    --box-dark: #6f8fb3;
    --ribbon: #c81e3a;
}
.christmas-gift--ice .christmas-gift-bow {
    transform: scale(0.8);
    animation-delay: -0.5s;
}

/* Soft snow glow along the bottom, and cinematic edge shading. */
.christmas-snowbank {
    right: -10%;
    bottom: -40px;
    left: -10%;
    height: 80px;
    border-radius: 50%;
    background: radial-gradient(ellipse at center, rgba(235, 245, 255, 0.14), transparent 70%);
}
.christmas-vignette {
    inset: 0;
    border-radius: inherit;
    box-shadow: inset 0 0 70px rgba(2, 10, 10, 0.45);
}

/* --- Greeting typography --- */
.christmas-seasonal-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.75rem;
    margin-bottom: 0.25rem;
    color: #f6d38a;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-shadow: 0 0 12px rgba(246, 196, 104, 0.45);
    text-transform: uppercase;
}
.christmas-greeting {
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.45);
}
.christmas-name {
    background: linear-gradient(100deg, #ffe7a8 0%, #f6c453 45%, #ffd9a0 70%, #f6c453 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    filter: drop-shadow(0 0 10px rgba(246, 196, 104, 0.4));
}
.christmas-subtitle {
    max-width: 34rem;
    text-shadow: 0 1px 8px rgba(0, 0, 0, 0.45);
}

/* --- Stat cards: translucent evergreen → burgundy glass with gold edges --- */
.christmas-theme .kpi-tile--blue {
    --kpi-accent: 165, 216, 255;
}
.christmas-theme .kpi-tile--green {
    --kpi-accent: 52, 211, 153;
}
.christmas-theme .kpi-tile--gold {
    --kpi-accent: 246, 196, 83;
}
.christmas-theme .kpi-tile--amber {
    --kpi-accent: 251, 92, 112;
}
.christmas-stat-card {
    border: 1px solid rgba(246, 214, 150, 0.18);
    background: linear-gradient(135deg, rgba(8, 44, 33, 0.7), rgba(72, 14, 32, 0.48));
    box-shadow:
        inset 0 1px rgba(255, 255, 255, 0.08),
        0 8px 22px rgba(2, 12, 10, 0.32);
}
.christmas-stat-card:hover {
    transform: translateY(-2px);
    border-color: rgba(var(--kpi-accent), 0.45);
    box-shadow:
        inset 0 1px rgba(255, 255, 255, 0.1),
        0 12px 26px rgba(2, 12, 10, 0.4),
        0 0 20px rgba(var(--kpi-accent), 0.18);
}
.christmas-stat-icon {
    border: 1px solid rgba(246, 214, 150, 0.2);
    background: rgba(var(--kpi-accent), 0.2);
}

/* --- Keyframes --- */
@keyframes christmas-fall {
    from {
        transform: translate3d(0, -8%, 0);
    }
    to {
        transform: translate3d(0, 100%, 0);
    }
}
@keyframes christmas-sway {
    from {
        translate: calc(var(--flake-drift) * -0.5) 0;
    }
    to {
        translate: var(--flake-drift) 0;
    }
}
@keyframes christmas-crystal-sway {
    from {
        translate: calc(var(--crystal-drift) * -0.5) 0;
    }
    to {
        translate: var(--crystal-drift) 0;
    }
}
@keyframes christmas-spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes christmas-drift-across {
    0% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(calc(50% + 40px), 14px, 0);
    }
    100% {
        transform: translate3d(calc(100% + 80px), -6px, 0);
    }
}
@keyframes christmas-bulb-glow {
    0%,
    40% {
        opacity: 1;
    }
    55%,
    90% {
        opacity: 0.35;
    }
    100% {
        opacity: 1;
    }
}
@keyframes christmas-tree-blink {
    0%,
    45% {
        opacity: 1;
        transform: scale(1);
    }
    55%,
    100% {
        opacity: 0.3;
        transform: scale(0.75);
    }
}
@keyframes christmas-twinkle {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.6) rotate(0deg);
    }
    50% {
        opacity: 1;
        transform: scale(1) rotate(45deg);
    }
}
@keyframes christmas-pendulum {
    from {
        transform: rotate(-9deg);
    }
    to {
        transform: rotate(9deg);
    }
}
@keyframes christmas-ornament-swing {
    from {
        transform: rotate(-12deg);
    }
    to {
        transform: rotate(12deg);
    }
}
@keyframes christmas-bow-float {
    from {
        translate: 0 0;
        rotate: -4deg;
    }
    to {
        translate: 0 -3px;
        rotate: 4deg;
    }
}
@keyframes christmas-aurora {
    from {
        transform: translate3d(0, 0, 0) scale(1);
    }
    to {
        transform: translate3d(-8%, 6%, 0) scale(1.08);
    }
}
@keyframes christmas-bokeh-drift {
    from {
        transform: translate3d(0, 0, 0) scale(1);
    }
    to {
        transform: translate3d(30px, -14px, 0) scale(1.15);
    }
}
@keyframes christmas-shimmer {
    0% {
        background-position: 120% 0;
    }
    60%,
    100% {
        background-position: -40% 0;
    }
}
@keyframes christmas-sleigh-cross {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(calc(100% + 180px));
    }
}
@keyframes christmas-sleigh-bob {
    from {
        translate: 0 0;
    }
    to {
        translate: 0 -5px;
    }
}
@keyframes christmas-float {
    from {
        translate: 0 0;
    }
    to {
        translate: -4px 5px;
    }
}
@keyframes christmas-glow-pulse {
    from {
        opacity: 0.65;
    }
    to {
        opacity: 1;
    }
}
@keyframes christmas-star-pulse {
    from {
        transform: scale(0.9) rotate(-8deg);
    }
    to {
        transform: scale(1.1) rotate(8deg);
    }
}

/* --- Responsive composition --- */

/* Wide screens: the content steps aside so the full tree owns the right-hand zone. */
@media (min-width: 1360px) {
    .christmas-theme {
        min-height: 200px;
    }
    .christmas-content {
        padding-right: 250px;
    }
}

/* Tablets and laptops: a compact tree beside the greeting, above the cards. */
@media (max-width: 1359px) {
    .christmas-bauble,
    .christmas-moon {
        display: none;
    }
    .christmas-tree-group {
        top: 12px;
        right: 6px;
        bottom: auto;
        height: 188px;
        transform: scale(0.58);
        transform-origin: 100% 0;
    }
}
@media (min-width: 640px) and (max-width: 1359px) {
    .christmas-heading {
        padding-right: 150px;
    }
}

/* Phones: drop the tree and heavier layers, keep the lights, moon and lighter snow. */
@media (max-width: 639px) {
    .christmas-tree-group,
    .christmas-sleigh-track,
    .christmas-drifter,
    .christmas-sparkle--desktop,
    .christmas-bokeh:nth-of-type(n + 4),
    .christmas-snowflake:nth-child(n + 15),
    .christmas-crystal:nth-child(n + 6) {
        display: none;
    }
    .christmas-moon {
        display: block;
        top: -22px;
        right: -22px;
        width: 56px;
        height: 56px;
        opacity: 0.75;
    }
    .christmas-seasonal-label {
        max-width: 235px;
        line-height: 1.4;
    }
}

@media (prefers-reduced-motion: reduce) {
    .christmas-theme *,
    .christmas-theme *::before,
    .christmas-theme *::after {
        animation: none !important;
    }
    .christmas-snowfall,
    .christmas-crystals,
    .christmas-drifter,
    .christmas-sleigh-track {
        display: none;
    }
}
</style>
