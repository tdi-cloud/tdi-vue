<script setup lang="ts">
import { accentStyle, formatDate, formatHours, type MyCertificate, statusMeta, typeMeta } from '@/lib/certificates';
import { CalendarDays, Clock } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CertificateSeal from './CertificateSeal.vue';

const props = defineProps<{
    certificate: MyCertificate;
    /** Position in the grid, used to stagger the entrance animation. */
    index: number;
}>();

const emit = defineEmits<{
    (e: 'open', certificate: MyCertificate): void;
}>();

const meta = computed(() => typeMeta(props.certificate.type));
const status = computed(() => statusMeta(props.certificate.status));
const cardStyle = computed(() => ({ ...accentStyle(props.certificate.type), '--i': String(Math.min(props.index, 11)) }));

const cardRef = ref<HTMLElement | null>(null);

// Tilt only follows a precise pointer that can hover; touch devices and reduced motion get a still card.
const canTilt =
    typeof window !== 'undefined' && window.matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)').matches;

function onPointerMove(event: PointerEvent) {
    if (!canTilt || !cardRef.value) return;

    const rect = cardRef.value.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width;
    const y = (event.clientY - rect.top) / rect.height;

    cardRef.value.style.setProperty('--rx', `${((0.5 - y) * 7).toFixed(2)}deg`);
    cardRef.value.style.setProperty('--ry', `${((x - 0.5) * 9).toFixed(2)}deg`);
    cardRef.value.style.setProperty('--gx', `${(x * 100).toFixed(1)}%`);
    cardRef.value.style.setProperty('--gy', `${(y * 100).toFixed(1)}%`);
}

function onPointerLeave() {
    if (!cardRef.value) return;

    cardRef.value.style.setProperty('--rx', '0deg');
    cardRef.value.style.setProperty('--ry', '0deg');
}
</script>

<template>
    <article
        ref="cardRef"
        class="cc"
        :class="`cc--${certificate.status.toLowerCase()}`"
        :style="cardStyle"
        @click="emit('open', certificate)"
        @pointermove="onPointerMove"
        @pointerleave="onPointerLeave"
    >
        <div class="cc__frame">
            <div class="cc__paper">
                <span class="cc__corner cc__corner--tl" aria-hidden="true"></span>
                <span class="cc__corner cc__corner--tr" aria-hidden="true"></span>
                <span class="cc__corner cc__corner--bl" aria-hidden="true"></span>
                <span class="cc__corner cc__corner--br" aria-hidden="true"></span>

                <div class="cc__art">
                    <div class="cc__cover">
                        <img
                            v-if="certificate.cover_image"
                            :src="certificate.cover_image"
                            :alt="`Cover of ${certificate.program_title}`"
                            loading="lazy"
                        />
                    </div>
                    <div class="cc__seal">
                        <CertificateSeal :icon="meta.icon" :status="certificate.status" />
                    </div>

                    <div class="cc__body">
                        <p class="cc__kicker">Certificate of</p>
                        <p class="cc__type">{{ certificate.type }}</p>
                        <h3 class="cc__title">
                            <button
                                type="button"
                                class="cc__open"
                                :aria-label="`${meta.label}: ${certificate.program_title}. ${status.label}. View details`"
                                @click.stop="emit('open', certificate)"
                            >
                                {{ certificate.program_title ?? 'Training Program' }}
                            </button>
                        </h3>
                        <span class="cc__rule" aria-hidden="true"><i></i></span>
                        <dl class="cc__meta">
                            <div>
                                <dt><CalendarDays :size="11" aria-hidden="true" /> Issued</dt>
                                <dd>
                                    {{
                                        certificate.issued_date
                                            ? formatDate(certificate.issued_date, { month: 'short', day: 'numeric', year: 'numeric' })
                                            : '—'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt><Clock :size="11" aria-hidden="true" /> Hours</dt>
                                <dd>{{ certificate.hours > 0 ? formatHours(certificate.hours) : '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <footer class="cc__foot">
                    <span class="cc__number">{{ certificate.certificate_number ?? 'No. to be assigned' }}</span>
                    <span class="cc__status"><component :is="status.icon" :size="12" aria-hidden="true" />{{ status.label }}</span>
                </footer>

                <span v-if="certificate.status === 'Revoked'" class="cc__clip" aria-hidden="true"><span class="cc__ribbon">Revoked</span></span>
            </div>
            <span class="cc__shine" aria-hidden="true"></span>
        </div>
    </article>
</template>

<style scoped>
.cc {
    --serif: 'Iowan Old Style', 'Palatino Linotype', Palatino, 'Book Antiqua', 'URW Palladio L', Georgia, serif;
    --mono: ui-monospace, 'SFMono-Regular', 'Cascadia Mono', 'Segoe UI Mono', Consolas, monospace;
    --gold: #b8955a;
    --rx: 0deg;
    --ry: 0deg;
    --gx: 50%;
    --gy: 0%;
    position: relative;
    min-width: 0;
    cursor: pointer;
    perspective: 1100px;
    animation: cc-rise 0.75s cubic-bezier(0.2, 0.8, 0.2, 1) both;
    animation-delay: calc(var(--i) * 65ms);
}
@keyframes cc-rise {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

/* The frame: a warm mat around the paper, like a certificate hung on a wall. */
.cc__frame {
    --lift: 0px;
    position: relative;
    display: flex;
    height: 100%;
    aspect-ratio: 5 / 6;
    padding: 9px;
    background: linear-gradient(150deg, #ffffff 0%, #f1ece1 100%);
    border: 1px solid #e4dccb;
    border-radius: 4px;
    box-shadow:
        0 1px 0 #fff inset,
        0 18px 40px -24px rgb(12 23 57 / 0.5),
        0 3px 10px -4px rgb(12 23 57 / 0.12);
    transform: rotateX(var(--rx)) rotateY(var(--ry)) translateY(var(--lift));
    transform-style: preserve-3d;
    transition:
        transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1),
        box-shadow 0.4s ease,
        border-color 0.4s ease;
}
.cc:hover .cc__frame {
    --lift: -6px;
    border-color: rgb(var(--accent-rgb) / 0.45);
    box-shadow:
        0 1px 0 #fff inset,
        0 34px 60px -28px rgb(12 23 57 / 0.55),
        0 8px 20px -8px rgb(var(--accent-rgb) / 0.25);
    transition-duration: 0.15s, 0.4s, 0.4s;
}
.cc:focus-within .cc__frame {
    border-color: rgb(var(--accent-rgb) / 0.6);
}

/* Ivory paper with a fine CSS grain */
.cc__paper {
    position: relative;
    display: flex;
    width: 100%;
    flex-direction: column;
    color: #2a2418;
    background:
        radial-gradient(120% 80% at 50% 0%, rgb(255 255 255 / 0.7), transparent 60%),
        radial-gradient(90% 60% at 100% 100%, rgb(201 174 120 / 0.16), transparent 70%),
        repeating-linear-gradient(0deg, rgb(110 90 50 / 0.018) 0 1px, transparent 1px 3px),
        repeating-linear-gradient(90deg, rgb(110 90 50 / 0.014) 0 1px, transparent 1px 4px), #fbf8f0;
    border-radius: 2px;
    box-shadow: 0 0 0 1px rgb(184 149 90 / 0.25);
}
.cc__paper::before,
.cc__paper::after {
    position: absolute;
    z-index: 2;
    pointer-events: none;
    content: '';
}
.cc__paper::before {
    inset: 6px;
    border: 1px solid rgb(184 149 90 / 0.7);
}
.cc__paper::after {
    inset: 9px;
    border: 1px solid rgb(184 149 90 / 0.3);
}
.cc__corner {
    position: absolute;
    z-index: 3;
    width: 7px;
    height: 7px;
    background: var(--gold);
    transform: rotate(45deg);
}
.cc__corner--tl {
    top: 3px;
    left: 3px;
}
.cc__corner--tr {
    top: 3px;
    right: 3px;
}
.cc__corner--bl {
    bottom: 3px;
    left: 3px;
}
.cc__corner--br {
    right: 3px;
    bottom: 3px;
}

.cc__art {
    position: relative;
    display: flex;
    flex: 1;
    flex-direction: column;
    transition:
        filter 0.4s ease,
        opacity 0.4s ease;
}
/* Program cover as a vignette that fades into the paper */
.cc__cover {
    position: relative;
    height: 92px;
    margin: 12px 12px 0;
    overflow: hidden;
    background: radial-gradient(circle at 30% 20%, rgb(255 255 255 / 0.6), transparent 60%), linear-gradient(135deg, var(--accent-tint), #efe5cf);
}
.cc__cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: saturate(0.85) sepia(0.12);
    -webkit-mask-image: linear-gradient(to bottom, #000 35%, transparent 100%);
    mask-image: linear-gradient(to bottom, #000 35%, transparent 100%);
    transition: transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.cc__cover::after {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgb(var(--accent-rgb) / 0.22), transparent 75%);
    mix-blend-mode: multiply;
    content: '';
}
.cc:hover .cc__cover img {
    transform: scale(1.04);
}
.cc__seal {
    position: relative;
    z-index: 4;
    width: 54px;
    height: 54px;
    margin: -30px auto 0;
}
.cc__seal :deep(.seal) {
    transition: transform 0.45s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.cc:hover .cc__seal :deep(.seal) {
    transform: scale(1.09) rotate(-6deg);
}

.cc__body {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    padding: 0.55rem 1.4rem 0.9rem;
    text-align: center;
}
.cc__kicker {
    margin: 0;
    color: #8a7650;
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.34em;
    text-transform: uppercase;
}
.cc__type {
    margin: 0.1rem 0 0.4rem;
    color: var(--accent-deep);
    font-family: var(--serif);
    font-size: 1.3rem;
    font-style: italic;
    font-weight: 600;
    line-height: 1.15;
}
.cc__title {
    margin: 0;
    width: 100%;
}
.cc__open {
    display: -webkit-box;
    width: 100%;
    overflow: hidden;
    padding: 0;
    color: #1b2440;
    font-family: var(--serif);
    font-size: 1.04rem;
    font-weight: 700;
    line-height: 1.3;
    text-align: center;
    background: none;
    border: 0;
    cursor: pointer;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}
/* The whole card is clickable; the title button is its keyboard entry, so the focus ring wraps the frame. */
.cc__open:focus-visible {
    outline: none;
}
.cc:has(.cc__open:focus-visible) .cc__frame {
    box-shadow:
        0 0 0 3px #fff,
        0 0 0 6px rgb(var(--accent-rgb) / 0.75),
        0 18px 40px -24px rgb(12 23 57 / 0.5);
}
.cc__rule {
    display: flex;
    width: 58%;
    align-items: center;
    gap: 7px;
    margin: auto 0 0.7rem;
    padding-top: 0.75rem;
}
.cc__rule::before,
.cc__rule::after {
    height: 1px;
    flex: 1;
    background: linear-gradient(90deg, transparent, var(--gold));
    content: '';
}
.cc__rule::after {
    background: linear-gradient(90deg, var(--gold), transparent);
}
.cc__rule i {
    width: 5px;
    height: 5px;
    background: var(--gold);
    transform: rotate(45deg);
}
.cc__meta {
    display: grid;
    width: 100%;
    grid-template-columns: 1fr 1fr;
    margin: 0;
}
.cc__meta > div + div {
    border-left: 1px solid rgb(184 149 90 / 0.35);
}
.cc__meta dt {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #8a7650;
    font-size: 0.56rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
}
.cc__meta dd {
    margin: 0.15rem 0 0;
    color: #2a2418;
    font-size: 0.8rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.cc__foot {
    position: relative;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.35rem 0.75rem;
    margin: 0 12px 12px;
    padding: 0.6rem 0.35rem 0;
    border-top: 1px dotted rgb(184 149 90 / 0.55);
}
.cc__number {
    color: #5c4f36;
    font-family: var(--mono);
    font-size: 0.66rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-shadow: 0 1px 0 rgb(255 255 255 / 0.9);
}
.cc__status {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}
.cc--issued .cc__status {
    color: #0f7a4f;
}
.cc--pending .cc__status {
    color: #8a5d00;
}
.cc--revoked .cc__status {
    color: #9b1c1c;
}

/* Light sweep across the paper on hover, plus a glare that follows the pointer */
.cc__shine {
    position: absolute;
    inset: 9px;
    z-index: 4;
    overflow: hidden;
    pointer-events: none;
    background:
        linear-gradient(115deg, transparent 38%, rgb(255 255 255 / 0.55) 48%, transparent 58%) no-repeat,
        radial-gradient(circle at var(--gx) var(--gy), rgb(255 255 255 / 0.28), transparent 45%);
    background-position:
        160% 0,
        0 0;
    background-size:
        260% 100%,
        100% 100%;
    opacity: 0;
    transition:
        opacity 0.4s ease,
        background-position 0s 0.4s;
}
.cc:hover .cc__shine {
    background-position:
        -60% 0,
        0 0;
    opacity: 1;
    transition:
        opacity 0.3s ease,
        background-position 1.1s cubic-bezier(0.2, 0.8, 0.2, 1);
}

/* Pending: muted, unsealed */
.cc--pending .cc__art {
    filter: saturate(0.35);
    opacity: 0.84;
}
/* Revoked: grayscale, with a diagonal ribbon across the certificate */
.cc--revoked .cc__art {
    filter: grayscale(1);
    opacity: 0.6;
}
.cc--revoked .cc__corner {
    background: #9a9a9a;
}
.cc__clip {
    position: absolute;
    inset: 0;
    z-index: 4;
    overflow: hidden;
    pointer-events: none;
}
.cc__ribbon {
    position: absolute;
    top: 46%;
    left: -15%;
    width: 130%;
    padding: 0.4rem 0;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.55em;
    text-align: center;
    text-transform: uppercase;
    background: linear-gradient(90deg, rgb(127 29 29 / 0.88), rgb(153 27 27 / 0.94), rgb(127 29 29 / 0.88));
    box-shadow: 0 6px 16px rgb(127 29 29 / 0.3);
    transform: rotate(-24deg);
}

/* Dark mode: the surrounding mat darkens; the paper stays ivory. */
.dark .cc__frame {
    background: linear-gradient(150deg, #1d2742 0%, #131b30 100%);
    border-color: #2a3654;
    box-shadow:
        0 1px 0 rgb(255 255 255 / 0.05) inset,
        0 22px 44px -22px rgb(0 0 0 / 0.75);
}
.dark .cc:hover .cc__frame {
    border-color: rgb(var(--accent-rgb) / 0.55);
    box-shadow:
        0 1px 0 rgb(255 255 255 / 0.05) inset,
        0 36px 64px -28px rgb(0 0 0 / 0.85),
        0 0 0 1px rgb(var(--accent-rgb) / 0.2);
}
.dark .cc__paper {
    filter: brightness(0.93);
}
.dark .cc:has(.cc__open:focus-visible) .cc__frame {
    box-shadow:
        0 0 0 3px #0a0f1e,
        0 0 0 6px rgb(var(--accent-rgb) / 0.85);
}

@media (prefers-reduced-motion: reduce) {
    .cc {
        animation: none;
    }
    .cc__frame,
    .cc__cover img,
    .cc__seal :deep(.seal),
    .cc__shine {
        transition: none;
    }
    .cc:hover .cc__frame {
        --lift: 0px;
    }
    .cc:hover .cc__cover img,
    .cc:hover .cc__seal :deep(.seal) {
        transform: none;
    }
    .cc:hover .cc__shine {
        opacity: 0;
    }
}
</style>
