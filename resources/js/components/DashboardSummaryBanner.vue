<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CalendarCheck, ClipboardList, GraduationCap, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    summary: {
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

// TEMPORARY HALLOWEEN THEME — EASILY REVERTIBLE
// Set to false after the season to restore the complete default banner design.
const isHalloweenTheme = true;

const stats = computed(() => [
    { label: 'Employees Monitored', value: props.summary.employees, icon: Users, accent: 'blue' },
    { label: 'Programs Conducted', value: props.summary.programs, icon: GraduationCap, accent: 'green' },
    { label: 'Active Batches', value: props.summary.active_batches, icon: CalendarCheck, accent: 'gold' },
    { label: 'Pending Submissions', value: props.summary.pending_submissions, icon: ClipboardList, accent: 'amber' },
]);
</script>

<template>
    <div
        class="relative isolate overflow-hidden rounded-2xl bg-gradient-to-br from-blue-800 via-blue-700 to-sky-600 px-6 py-5 text-white shadow-md"
        :class="{ 'halloween-theme': isHalloweenTheme }"
    >
        <!-- Decorative background texture — purely visual, sits behind existing content via negative z-index -->
        <div class="tdi-texture-institutional pointer-events-none absolute inset-0 z-[-1] opacity-[0.07]" aria-hidden="true"></div>

        <!-- TEMPORARY HALLOWEEN THEME — EASILY REVERTIBLE -->
        <div v-if="isHalloweenTheme" class="halloween-scene pointer-events-none" aria-hidden="true">
            <div class="halloween-moon"></div>
            <span class="halloween-bat halloween-bat--one"></span>
            <span class="halloween-bat halloween-bat--two"></span>
            <span class="halloween-bat halloween-bat--three"></span>
            <div class="halloween-fog halloween-fog--back"></div>
            <div class="halloween-fog halloween-fog--front"></div>
            <span class="halloween-pumpkin halloween-pumpkin--large"></span>
            <span class="halloween-pumpkin halloween-pumpkin--small"></span>
            <i class="halloween-ember halloween-ember--one"></i>
            <i class="halloween-ember halloween-ember--two"></i>
            <i class="halloween-ember halloween-ember--three"></i>
            <i class="halloween-ember halloween-ember--four"></i>
            <div class="halloween-vignette"></div>
        </div>

        <div class="relative z-10">
            <p v-if="isHalloweenTheme" class="halloween-seasonal-label">A Spooktacular Season of Learning</p>
            <p class="text-lg font-bold" :class="{ 'sm:text-xl': isHalloweenTheme }">
                {{ greeting
                }}<template v-if="firstName"
                    >, <span :class="{ 'halloween-name': isHalloweenTheme }">{{ firstName }}</span></template
                >
                👋
            </p>
            <p v-if="isHalloweenTheme" class="mt-0.5 text-sm text-white/85">
                A little Halloween magic, a lot of learning, and achievements worth celebrating.
            </p>
            <p v-else class="mt-0.5 text-sm text-white/80">Here's a quick look at what's happening across your programs today.</p>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="stat in stats"
                    :key="stat.label"
                    class="kpi-tile relative flex items-center gap-3 overflow-hidden rounded-xl bg-white/10 px-4 py-3 backdrop-blur-sm"
                    :class="[`kpi-tile--${stat.accent}`, isHalloweenTheme ? ['halloween-stat-card', `halloween-stat-card--${stat.accent}`] : '']"
                >
                    <div
                        class="kpi-tile-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15"
                        :class="isHalloweenTheme ? 'halloween-stat-icon' : ''"
                    >
                        <component :is="stat.icon" class="h-4.5 w-4.5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-lg font-extrabold tabular-nums leading-tight tracking-tight">{{ stat.value.toLocaleString() }}</p>
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

/* TEMPORARY HALLOWEEN THEME — EASILY REVERTIBLE
   Midnight navy → charcoal-purple base, pumpkin light from the right,
   violet ambience from the left. Everything decorative sits behind the z-10 content. */
.halloween-theme {
    background:
        radial-gradient(38% 75% at 86% 22%, rgba(251, 146, 60, 0.32), transparent 70%),
        radial-gradient(30% 65% at 100% 100%, rgba(234, 88, 12, 0.2), transparent 70%),
        radial-gradient(35% 85% at 0% 0%, rgba(139, 92, 246, 0.2), transparent 70%),
        radial-gradient(45% 80% at 40% 115%, rgba(91, 33, 182, 0.16), transparent 70%),
        linear-gradient(125deg, #070b1f 0%, #10142f 42%, #1c1433 74%, #2a1630 100%);
    box-shadow:
        0 14px 35px rgba(6, 4, 20, 0.35),
        inset 0 1px rgba(253, 186, 116, 0.16);
}

.halloween-scene,
.halloween-scene > * {
    position: absolute;
}

.halloween-scene {
    inset: 0;
    overflow: hidden;
}

/* Crescent moon: an inset shadow on an empty circle, with a soft halo behind it. */
.halloween-moon {
    top: 16px;
    right: 7%;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    box-shadow: inset -10px 4px 0 0 #ffe4b8;
    filter: drop-shadow(0 0 10px rgba(255, 210, 150, 0.55));
    opacity: 0.9;
}
.halloween-moon::before {
    position: absolute;
    inset: -34px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 200, 140, 0.16), transparent 65%);
    content: '';
}

/* Tiny bats: clip-path silhouettes with a faint warm rim light. */
.halloween-bat {
    width: 22px;
    height: 9px;
    background: #140b22;
    clip-path: polygon(
        0 30%,
        18% 0,
        30% 35%,
        42% 20%,
        46% 0,
        50% 18%,
        54% 0,
        58% 20%,
        70% 35%,
        82% 0,
        100% 30%,
        88% 55%,
        76% 45%,
        64% 70%,
        50% 100%,
        36% 70%,
        24% 45%,
        12% 55%
    );
    filter: drop-shadow(0 0 3px rgba(253, 186, 116, 0.35));
    animation: halloween-hover 6s ease-in-out infinite alternate;
}
.halloween-bat--one {
    top: 14px;
    right: 15%;
    transform: rotate(-8deg);
}
.halloween-bat--two {
    top: 38px;
    right: 20%;
    width: 16px;
    height: 7px;
    opacity: 0.8;
    animation-delay: 1.5s;
}
.halloween-bat--three {
    top: 10px;
    right: 26%;
    width: 13px;
    height: 6px;
    opacity: 0.6;
    animation-delay: 3s;
}

/* Low fog drifting along the bottom edge. */
.halloween-fog {
    bottom: -18px;
    height: 56px;
    border-radius: 50%;
    filter: blur(8px);
    animation: halloween-drift 24s ease-in-out infinite alternate;
}
.halloween-fog--back {
    left: -25%;
    width: 90%;
    background: radial-gradient(ellipse at center, rgba(196, 181, 253, 0.12), transparent 70%);
}
.halloween-fog--front {
    right: -25%;
    width: 85%;
    bottom: -24px;
    background: radial-gradient(ellipse at center, rgba(255, 228, 196, 0.08), transparent 70%);
    animation-duration: 32s;
    animation-direction: alternate-reverse;
}

/* Small, understated pumpkins peeking from the bottom-right corner. */
.halloween-pumpkin {
    bottom: 2px;
    border-radius: 48% 48% 44% 44%;
    background: radial-gradient(ellipse at 45% 35%, #f59a3c 0%, #d9681a 55%, #8f3a0c 100%);
    box-shadow: 0 0 18px rgba(249, 115, 22, 0.35);
    opacity: 0.75;
}
.halloween-pumpkin::before {
    position: absolute;
    inset: 1px 30%;
    border-right: 1px solid rgba(90, 30, 5, 0.45);
    border-left: 1px solid rgba(90, 30, 5, 0.45);
    border-radius: 50%;
    content: '';
}
.halloween-pumpkin::after {
    position: absolute;
    top: -4px;
    left: 46%;
    width: 3px;
    height: 5px;
    border-radius: 2px;
    background: #4d5a2c;
    transform: rotate(12deg);
    content: '';
}
.halloween-pumpkin--large {
    right: 2.5%;
    width: 26px;
    height: 19px;
}
.halloween-pumpkin--small {
    right: calc(2.5% + 30px);
    width: 18px;
    height: 13px;
    opacity: 0.6;
}

/* Warm floating embers. */
.halloween-ember {
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: #ffc38a;
    box-shadow: 0 0 8px 2px rgba(251, 146, 60, 0.45);
    opacity: 0.55;
    animation: halloween-ember 5s ease-in-out infinite alternate;
}
.halloween-ember--one {
    top: 28%;
    left: 52%;
}
.halloween-ember--two {
    top: 58%;
    right: 24%;
    width: 4px;
    height: 4px;
    animation-delay: 1.2s;
}
.halloween-ember--three {
    top: 20%;
    right: 36%;
    animation-delay: 2.4s;
}
.halloween-ember--four {
    right: 11%;
    bottom: 38%;
    width: 2px;
    height: 2px;
    animation-delay: 3.4s;
}

/* Cinematic edge shading. */
.halloween-vignette {
    inset: 0;
    border-radius: inherit;
    box-shadow: inset 0 0 70px rgba(3, 2, 12, 0.5);
}

.halloween-seasonal-label {
    margin-bottom: 0.25rem;
    color: #fdba74;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.halloween-name {
    color: #fdb35c;
    text-shadow: 0 0 14px rgba(251, 146, 60, 0.45);
}

/* Stat cards: translucent midnight-purple glass, accents warmed to sit in the palette. */
.halloween-theme .kpi-tile--blue {
    --kpi-accent: 129, 161, 255;
}
.halloween-theme .kpi-tile--green {
    --kpi-accent: 52, 211, 153;
}
.halloween-theme .kpi-tile--gold {
    --kpi-accent: 251, 191, 36;
}
.halloween-theme .kpi-tile--amber {
    --kpi-accent: 249, 115, 22;
}
.halloween-stat-card {
    border: 1px solid rgba(253, 186, 116, 0.14);
    background: linear-gradient(135deg, rgba(22, 17, 42, 0.64), rgba(44, 24, 58, 0.4));
    box-shadow:
        inset 0 1px rgba(255, 255, 255, 0.07),
        0 8px 22px rgba(3, 2, 12, 0.32);
}
.halloween-stat-card:hover {
    transform: translateY(-1px);
    border-color: rgba(var(--kpi-accent), 0.42);
    box-shadow:
        inset 0 1px rgba(255, 255, 255, 0.09),
        0 8px 22px rgba(3, 2, 12, 0.32),
        0 0 18px rgba(var(--kpi-accent), 0.14);
}
.halloween-stat-icon {
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(var(--kpi-accent), 0.2);
}

@keyframes halloween-hover {
    from {
        translate: 0 0;
    }
    to {
        translate: 3px -3px;
    }
}
@keyframes halloween-drift {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(8%);
    }
}
@keyframes halloween-ember {
    from {
        opacity: 0.3;
        transform: translateY(0);
    }
    to {
        opacity: 0.75;
        transform: translateY(-6px);
    }
}

@media (max-width: 640px) {
    .halloween-moon {
        top: -10px;
        right: -12px;
        width: 34px;
        height: 34px;
        opacity: 0.7;
    }
    .halloween-bat,
    .halloween-pumpkin,
    .halloween-ember--one,
    .halloween-ember--three {
        display: none;
    }
    .halloween-seasonal-label {
        max-width: 235px;
        line-height: 1.4;
    }
}

@media (prefers-reduced-motion: reduce) {
    .halloween-theme *,
    .halloween-theme *::before,
    .halloween-theme *::after {
        animation: none !important;
    }
}
</style>
