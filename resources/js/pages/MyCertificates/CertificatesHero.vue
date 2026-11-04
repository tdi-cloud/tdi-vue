<script setup lang="ts">
import { type CertificateStats, formatDate, formatHours, typeMeta } from '@/lib/certificates';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Award, BookOpenCheck, Clock, Sparkles } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import CertificateSeal from './CertificateSeal.vue';

const props = defineProps<{
    stats: CertificateStats;
}>();

const latest = computed(() => props.stats.latest);
const issuedCount = computed(() => props.stats.by_status.Issued ?? 0);
const pendingCount = computed(() => props.stats.by_status.Pending ?? 0);

/* ---- Count-up when the statistics scroll into view ---- */
type CountKey = 'issued' | 'hours' | 'programs';

const targets = computed<Record<CountKey, number>>(() => ({
    issued: issuedCount.value,
    hours: props.stats.hours,
    programs: props.stats.programs,
}));
const shown = reactive<Record<CountKey, number>>({ issued: 0, hours: 0, programs: 0 });
const statsRef = ref<HTMLElement | null>(null);
const hasRevealed = ref(false);
let observer: IntersectionObserver | null = null;
const frames: number[] = [];

function display(key: CountKey): string {
    const value = shown[key];

    return key === 'hours'
        ? formatHours(Number.isInteger(targets.value.hours) ? Math.round(value) : Math.round(value * 10) / 10)
        : Math.round(value).toLocaleString('en-US');
}

function countUp() {
    hasRevealed.value = true;
    const keys: CountKey[] = ['issued', 'hours', 'programs'];

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        keys.forEach((key) => (shown[key] = targets.value[key]));
        return;
    }

    keys.forEach((key, i) => {
        const delay = 250 + i * 160;
        const duration = 1600;
        const target = targets.value[key];
        let startedAt: number | null = null;

        const step = (now: number) => {
            startedAt ??= now + delay;
            const elapsed = now - startedAt;

            if (elapsed >= 0) {
                const progress = Math.min(1, elapsed / duration);
                // easeOutExpo: quick start, gentle landing on the final number.
                shown[key] = target * (progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress));
                if (progress === 1) return;
            }
            frames.push(requestAnimationFrame(step));
        };
        frames.push(requestAnimationFrame(step));
    });
}

onMounted(() => {
    if (!statsRef.value || !('IntersectionObserver' in window)) {
        countUp();
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                countUp();
                observer?.disconnect();
            }
        },
        { threshold: 0.3 },
    );
    observer.observe(statsRef.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    frames.forEach((frame) => cancelAnimationFrame(frame));
});

// Fixed positions so the gold particles never shift between renders.
const particles = [
    { top: '18%', left: '8%', size: 3, delay: '0s' },
    { top: '32%', left: '46%', size: 2, delay: '1.4s' },
    { top: '12%', left: '62%', size: 4, delay: '2.6s' },
    { top: '58%', left: '88%', size: 3, delay: '0.8s' },
    { top: '70%', left: '24%', size: 2, delay: '3.2s' },
    { top: '24%', left: '93%', size: 2, delay: '2s' },
    { top: '46%', left: '71%', size: 3, delay: '4s' },
    { top: '80%', left: '56%', size: 2, delay: '1s' },
];
</script>

<template>
    <header class="mch">
        <div class="mch__backdrop" aria-hidden="true">
            <span class="mch__rings"></span>
            <span class="mch__glow"></span>
            <span
                v-for="(particle, i) in particles"
                :key="i"
                class="mch__particle"
                :style="{
                    top: particle.top,
                    left: particle.left,
                    width: `${particle.size}px`,
                    height: `${particle.size}px`,
                    animationDelay: particle.delay,
                }"
            ></span>
        </div>

        <div class="mch__inner">
            <div class="mch__copy">
                <Link :href="route('home')" class="mch__back"><ArrowLeft :size="15" aria-hidden="true" /> Back to Home</Link>
                <p class="mch__eyebrow"><span aria-hidden="true"></span>My Professional Journey<span aria-hidden="true"></span></p>
                <h1 class="mch__title">My Certificates</h1>
                <p class="mch__lede">Every achievement represents a step forward in your professional growth.</p>
            </div>

            <!-- Decorative stack of certificates -->
            <div class="mch__art" aria-hidden="true">
                <span class="mch__sheet mch__sheet--back"></span>
                <span class="mch__sheet mch__sheet--mid"></span>
                <div class="mch__sheet mch__sheet--front">
                    <span class="mch__bar mch__bar--kicker"></span>
                    <span class="mch__bar mch__bar--title"></span>
                    <span class="mch__bar mch__bar--line"></span>
                    <span class="mch__bar mch__bar--line mch__bar--short"></span>
                    <span class="mch__signatures"><i></i><i></i></span>
                    <span class="mch__seal"><CertificateSeal :icon="Award" ribbons engraved /></span>
                </div>
            </div>
        </div>

        <dl ref="statsRef" class="mch__stats" :class="{ 'mch__stats--revealed': hasRevealed }">
            <div class="mch__stat" style="--d: 0">
                <dt><Award :size="14" aria-hidden="true" /> Certificates Earned</dt>
                <dd>
                    <span class="mch__num" aria-hidden="true">{{ display('issued') }}</span>
                    <span class="sr-only">{{ issuedCount }}</span>
                    <small v-if="pendingCount">+{{ pendingCount }} awaiting issuance</small>
                    <small v-else>officially issued</small>
                </dd>
            </div>
            <div class="mch__stat" style="--d: 1">
                <dt><Clock :size="14" aria-hidden="true" /> Training Hours</dt>
                <dd>
                    <span class="mch__num" aria-hidden="true">{{ display('hours') }}<em>hrs</em></span>
                    <span class="sr-only">{{ formatHours(stats.hours) }} hours</span>
                    <small>of recognized learning</small>
                </dd>
            </div>
            <div class="mch__stat" style="--d: 2">
                <dt><BookOpenCheck :size="14" aria-hidden="true" /> Programs Completed</dt>
                <dd>
                    <span class="mch__num" aria-hidden="true">{{ display('programs') }}</span>
                    <span class="sr-only">{{ stats.programs }}</span>
                    <small>with a certificate on record</small>
                </dd>
            </div>
            <div class="mch__stat mch__stat--latest" style="--d: 3">
                <dt><Sparkles :size="14" aria-hidden="true" /> Latest Achievement</dt>
                <dd v-if="latest">
                    <span class="mch__latest-type">{{ typeMeta(latest.type).label.replace('Certificate of ', '') }}</span>
                    <small class="mch__latest-program">{{ latest.program_title }}</small>
                    <small v-if="latest.issued_date">{{ formatDate(latest.issued_date, { month: 'short', day: 'numeric', year: 'numeric' }) }}</small>
                </dd>
                <dd v-else>
                    <span class="mch__latest-type mch__latest-type--empty">Yet to come</span>
                    <small>Your first issued certificate will appear here</small>
                </dd>
            </div>
        </dl>
    </header>
</template>

<style scoped>
.mch {
    --serif: 'Iowan Old Style', 'Palatino Linotype', Palatino, 'Book Antiqua', 'URW Palladio L', Georgia, serif;
    --champagne: #e9d29a;
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: 6.25rem 2rem 9.5rem;
    color: #fff;
    background:
        radial-gradient(60% 80% at 85% 0%, rgb(212 175 106 / 0.16), transparent 62%),
        radial-gradient(55% 75% at 0% 100%, rgb(76 110 245 / 0.22), transparent 65%), linear-gradient(160deg, #060c20 0%, #0b1638 48%, #122660 100%);
}
.mch__backdrop {
    position: absolute;
    inset: 0;
    z-index: -1;
    pointer-events: none;
}
/* Fine concentric rings, like the security pattern on a printed certificate */
.mch__rings {
    position: absolute;
    top: -30%;
    right: -12%;
    width: 900px;
    height: 900px;
    background: repeating-radial-gradient(circle, rgb(233 210 154 / 0.07) 0 1px, transparent 1px 16px);
    border-radius: 50%;
    -webkit-mask-image: radial-gradient(circle, #000 20%, transparent 68%);
    mask-image: radial-gradient(circle, #000 20%, transparent 68%);
}
.mch__glow {
    position: absolute;
    top: 8%;
    right: 14%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgb(233 210 154 / 0.16), transparent 65%);
    filter: blur(10px);
}
.mch__particle {
    position: absolute;
    background: var(--champagne);
    border-radius: 50%;
    box-shadow: 0 0 8px 1px rgb(233 210 154 / 0.6);
    opacity: 0.25;
    animation: mch-twinkle 6s ease-in-out infinite;
}
@keyframes mch-twinkle {
    0%,
    100% {
        opacity: 0.15;
        transform: scale(0.8);
    }
    50% {
        opacity: 0.85;
        transform: scale(1.15);
    }
}

.mch__inner {
    position: relative;
    display: grid;
    max-width: 1200px;
    grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
    align-items: center;
    gap: 2rem;
    margin: 0 auto;
}
.mch__back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 1.6rem;
    color: rgb(255 255 255 / 0.7);
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.2s;
}
.mch__back:hover {
    color: #fff;
}
.mch__back:focus-visible {
    border-radius: 4px;
    outline: 2px solid var(--champagne);
    outline-offset: 4px;
}
.mch__eyebrow {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0 0 1rem;
    color: var(--champagne);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.32em;
    text-transform: uppercase;
}
.mch__eyebrow span {
    width: 28px;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--champagne));
}
.mch__eyebrow span:last-child {
    background: linear-gradient(90deg, var(--champagne), transparent);
}
.mch__title {
    margin: 0;
    font-family: var(--serif);
    font-size: clamp(2.5rem, 6vw, 4.25rem);
    font-weight: 700;
    letter-spacing: -0.015em;
    line-height: 1.02;
    background: linear-gradient(180deg, #ffffff 30%, #e8dcc0 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.mch__lede {
    max-width: 480px;
    margin: 1.1rem 0 0;
    color: rgb(226 232 245 / 0.82);
    font-size: clamp(0.95rem, 1.4vw, 1.08rem);
    line-height: 1.65;
}

/* Decorative certificate stack */
.mch__art {
    position: relative;
    justify-self: center;
    width: min(100%, 380px);
    aspect-ratio: 1.414;
    animation: mch-float 7s ease-in-out infinite;
}
@keyframes mch-float {
    0%,
    100% {
        transform: translateY(0) rotate(-5deg);
    }
    50% {
        transform: translateY(-10px) rotate(-4deg);
    }
}
.mch__sheet {
    position: absolute;
    inset: 0;
    border-radius: 3px;
}
.mch__sheet--back {
    background: #d9cfb8;
    opacity: 0.35;
    transform: rotate(11deg) translate(18px, -6px);
}
.mch__sheet--mid {
    background: #e9e1cd;
    opacity: 0.6;
    transform: rotate(5deg) translate(9px, -2px);
}
.mch__sheet--front {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 14% 12% 10%;
    background:
        radial-gradient(120% 90% at 50% 0%, #fff, transparent 60%), repeating-linear-gradient(0deg, rgb(110 90 50 / 0.02) 0 1px, transparent 1px 3px),
        #fbf8f0;
    box-shadow:
        0 40px 70px -30px rgb(0 0 0 / 0.7),
        0 0 0 1px rgb(184 149 90 / 0.4);
}
.mch__sheet--front::before,
.mch__sheet--front::after {
    position: absolute;
    pointer-events: none;
    content: '';
}
.mch__sheet--front::before {
    inset: 9px;
    border: 1.5px solid #b8955a;
}
.mch__sheet--front::after {
    inset: 13px;
    border: 1px solid rgb(184 149 90 / 0.45);
}
.mch__bar {
    display: block;
    height: 6px;
    border-radius: 3px;
}
.mch__bar--kicker {
    width: 38%;
    height: 4px;
    background: #c9ad78;
}
.mch__bar--title {
    width: 72%;
    height: 12px;
    margin-top: 9%;
    background: linear-gradient(90deg, #13275f, #23408f);
    border-radius: 2px;
}
.mch__bar--line {
    width: 60%;
    height: 4px;
    margin-top: 6%;
    background: #d9d2c2;
}
.mch__bar--short {
    width: 42%;
    margin-top: 3%;
}
.mch__signatures {
    display: flex;
    width: 100%;
    justify-content: space-between;
    margin-top: auto;
}
.mch__signatures i {
    width: 28%;
    height: 1px;
    background: #8f8670;
}
.mch__seal {
    --accent-deep: #13275f;
    position: absolute;
    bottom: 4%;
    left: 50%;
    width: 22%;
    aspect-ratio: 100 / 130;
    transform: translateX(-50%);
}

/* Statistics ledger */
.mch__stats {
    position: relative;
    display: grid;
    max-width: 1200px;
    grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(0, 1.35fr);
    margin: 3.25rem auto 0;
    padding-top: 1.6rem;
    border-top: 1px solid rgb(233 210 154 / 0.22);
}
.mch__stats::before {
    position: absolute;
    top: -1px;
    left: 0;
    width: 140px;
    height: 1px;
    background: linear-gradient(90deg, var(--champagne), transparent);
    content: '';
}
.mch__stat {
    min-width: 0;
    padding: 0 1.75rem;
    opacity: 0;
    transform: translateY(12px);
    transition:
        opacity 0.7s ease,
        transform 0.7s cubic-bezier(0.2, 0.8, 0.2, 1);
    transition-delay: calc(var(--d) * 120ms);
}
.mch__stat:first-child {
    padding-left: 0;
}
.mch__stat + .mch__stat {
    border-left: 1px solid rgb(233 210 154 / 0.16);
}
.mch__stats--revealed .mch__stat {
    opacity: 1;
    transform: none;
}
.mch__stat dt {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    color: rgb(226 232 245 / 0.7);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.mch__stat dt svg {
    color: var(--champagne);
}
.mch__stat dd {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    margin: 0.65rem 0 0;
}
.mch__num {
    color: #fff;
    font-size: clamp(2.2rem, 4vw, 3.1rem);
    font-weight: 300;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.035em;
    line-height: 1;
}
.mch__num em {
    margin-left: 0.3rem;
    color: var(--champagne);
    font-size: 0.9rem;
    font-style: normal;
    font-weight: 600;
    letter-spacing: 0.04em;
}
.mch__stat small {
    color: rgb(226 232 245 / 0.6);
    font-size: 0.76rem;
    line-height: 1.4;
}
.mch__latest-type {
    color: var(--champagne);
    font-family: var(--serif);
    font-size: clamp(1.45rem, 2.5vw, 1.85rem);
    font-style: italic;
    font-weight: 600;
    line-height: 1.1;
}
.mch__latest-type--empty {
    color: rgb(233 210 154 / 0.7);
}
.mch__latest-program {
    display: -webkit-box;
    overflow: hidden;
    color: rgb(255 255 255 / 0.85) !important;
    font-weight: 600;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
}

@media (max-width: 960px) {
    .mch__inner {
        grid-template-columns: 1fr;
    }
    .mch__art {
        display: none;
    }
    .mch__stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 1.75rem;
    }
    .mch__stat:nth-child(3) {
        padding-left: 0;
        border-left: 0;
    }
}
@media (max-width: 640px) {
    .mch {
        padding: 5.5rem 1rem 8rem;
    }
    .mch__back {
        margin-bottom: 1.1rem;
    }
    .mch__stats {
        margin-top: 2.25rem;
    }
    .mch__stat {
        padding: 0 0 0 1rem;
    }
    .mch__stat:nth-child(odd) {
        padding-left: 0;
        border-left: 0;
    }
    .mch__stat--latest {
        grid-column: 1 / -1;
        padding: 1.25rem 0 0;
        border-top: 1px solid rgb(233 210 154 / 0.16);
        border-left: 0 !important;
    }
}

@media (prefers-reduced-motion: reduce) {
    .mch__particle,
    .mch__art {
        animation: none;
    }
    .mch__art {
        transform: rotate(-5deg);
    }
    .mch__stat {
        transition: none;
    }
}
</style>
