<script setup lang="ts">
import { useMotionLevel } from '@/composables/useMotionLevel';
import { httpErrorMessage } from '@/lib/http';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import axios, { type AxiosError } from 'axios';
import { Mail } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        email: string;
        length?: number;
        /** Seconds left on the server's resend cooldown when the page loads. */
        resendAvailableIn?: number;
    }>(),
    { length: 6, resendAvailableIn: 0 },
);

const emit = defineEmits<{
    /** The code was accepted and the success animation has finished. */
    verified: [url: string];
    /** True from the success burst on (the page turns the card green), false again on reset. */
    celebrating: [active: boolean];
}>();

type Phase = 'input' | 'submitting' | 'verifying' | 'success' | 'error';
type HeadingKey = 'input' | 'verifying' | 'success' | 'failed';
type MessageTone = 'error' | 'success';

interface OtpResponse {
    success: boolean;
    message: string;
    redirect?: string;
    attempts_left?: number;
    expired?: boolean;
    locked?: boolean;
    retry_after?: number;
    resend_available_in?: number;
}

interface VerifyResult {
    ok: boolean;
    redirect: string;
    message: string;
    /** Expired or locked: the code can't be retried, the user has to register again. */
    isFinal: boolean;
}

interface ConfettiPiece {
    id: number;
    style: Record<string, string>;
}

/** Durations in ms and easing strings, read from the CSS variables on the root. */
interface Timings {
    easeOut: string;
    easeIn: string;
    easeInOut: string;
    spring: string;
    pop: number;
    lift: number;
    scatter: number;
    orbit: number;
    collapse: number;
    collapseStagger: number;
    crossfade: number;
    count: number;
    countFinish: number;
    errorMin: number;
    morph: number;
    burstAt: number;
    checkAt: number;
    badgeAt: number;
    check: number;
    badge: number;
    successHold: number;
    confettiMin: number;
    confettiMax: number;
    confettiDelayMax: number;
    shake: number;
    errorHold: number;
    reset: number;
    resetStagger: number;
}

const RING_SIZE = 84;
const RING_STROKE = 6;
const RING_RADIUS = (RING_SIZE - RING_STROKE) / 2;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;
const CHECK_LENGTH = 44;
/** The count waits here until the server has answered. */
const HOLD_PROGRESS = 92;
const SCATTER_RADIUS = 48;
const TILE_TILTS = [-12, 8, -6, 14, -9, 5];

const HEADINGS: Record<HeadingKey, { title: string; subtitle: string }> = {
    input: { title: 'Verify Your Email', subtitle: '' },
    verifying: { title: 'Verifying...', subtitle: 'Checking your code securely.' },
    success: { title: 'Email Verified', subtitle: 'Your account is ready. Taking you to the homepage...' },
    failed: { title: 'Verification Failed', subtitle: 'This code can no longer be used.' },
};

const page = usePage<SharedData>();
const motionLevel = useMotionLevel();
const isGlass = computed(() => Boolean(page.props.authBackground));
const isStill = computed(() => motionLevel.value === 'still');

const rootEl = ref<HTMLElement | null>(null);
const rowEl = ref<HTMLElement | null>(null);
const successCircleEl = ref<HTMLElement | null>(null);
const successGlowEl = ref<HTMLElement | null>(null);
const checkEl = ref<SVGPathElement | null>(null);
const slotEls: HTMLElement[] = [];
const inputEls: HTMLInputElement[] = [];

const digits = ref<string[]>(Array(props.length).fill(''));
const phase = ref<Phase>('input');
const headingKey = ref<HeadingKey>('input');
const activeIndex = ref<number | null>(null);
const isLifted = ref(false);
const isBlocked = ref(false);
const showRing = ref(false);
const showBadge = ref(false);
const progress = ref(0);
const confetti = ref<ConfettiPiece[]>([]);
const message = ref<{ id: number; text: string; tone: MessageTone } | null>(null);
const announcement = ref('');
const resendSeconds = ref(0);
const isResending = ref(false);

const heading = computed(() => HEADINGS[headingKey.value]);
const isComplete = computed(() => digits.value.every((digit) => digit !== ''));
const isInputDisabled = computed(() => phase.value !== 'input' || isBlocked.value);
const canResend = computed(() => resendSeconds.value === 0 && !isResending.value && phase.value === 'input' && !isBlocked.value);
const resendLabel = computed(() => {
    if (isResending.value) {
        return 'Sending...';
    }

    return resendSeconds.value > 0 ? `Resend in ${resendSeconds.value}s` : 'Resend';
});

// ── Timers, frames and animations (all cancelled on unmount) ────────────────

let timing: Timings;
let isDisposed = false;
let frameId = 0;
let countdownId: number | undefined;
let messageId = 0;
let confettiId = 0;
const timers = new Set<number>();
const animations = new Set<Animation>();

function readTimings(): Timings {
    const style = getComputedStyle(rootEl.value!);
    const ms = (name: string): number => {
        const value = style.getPropertyValue(name).trim();
        const amount = parseFloat(value) || 0;

        return value.endsWith('ms') ? amount : value.endsWith('s') ? amount * 1000 : amount;
    };
    const ease = (name: string): string => style.getPropertyValue(name).trim() || 'ease';

    return {
        easeOut: ease('--otp-ease-out'),
        easeIn: ease('--otp-ease-in'),
        easeInOut: ease('--otp-ease-in-out'),
        spring: ease('--otp-ease-spring'),
        pop: ms('--otp-dur-pop'),
        lift: ms('--otp-dur-lift'),
        scatter: ms('--otp-dur-scatter'),
        orbit: ms('--otp-dur-orbit'),
        collapse: ms('--otp-dur-collapse'),
        collapseStagger: ms('--otp-collapse-stagger'),
        crossfade: ms('--otp-dur-crossfade'),
        count: ms('--otp-dur-count'),
        countFinish: ms('--otp-dur-count-finish'),
        errorMin: ms('--otp-dur-error-min'),
        morph: ms('--otp-dur-morph'),
        burstAt: ms('--otp-success-burst-at'),
        checkAt: ms('--otp-success-check-at'),
        badgeAt: ms('--otp-success-badge-at'),
        check: ms('--otp-dur-check'),
        badge: ms('--otp-dur-badge'),
        successHold: ms('--otp-dur-success-hold'),
        confettiMin: ms('--otp-dur-confetti-min'),
        confettiMax: ms('--otp-dur-confetti-max'),
        confettiDelayMax: ms('--otp-confetti-delay-max'),
        shake: ms('--otp-dur-shake'),
        errorHold: ms('--otp-dur-error-hold'),
        reset: ms('--otp-dur-reset'),
        resetStagger: ms('--otp-reset-stagger'),
    };
}

/** Resolves after `ms`. After unmount it never resolves, so a running sequence simply stops. */
function wait(ms: number): Promise<void> {
    return new Promise((resolve) => {
        if (isDisposed) {
            return;
        }
        const id = window.setTimeout(() => {
            timers.delete(id);
            resolve();
        }, ms);
        timers.add(id);
    });
}

function animate(el: Element | null | undefined, keyframes: Keyframe[], options: KeyframeAnimationOptions): void {
    if (!el || isDisposed) {
        return;
    }
    animations.add(el.animate(keyframes, options));
}

function cancelAnimations(): void {
    animations.forEach((animation) => animation.cancel());
    animations.clear();
}

const easeOutCubic = (t: number): number => 1 - Math.pow(1 - t, 3);

/**
 * Counts `progress` to `to` with requestAnimationFrame (the ring and the number follow it).
 * Resolves true when it gets there, false when `shouldStop` cuts it short.
 */
function countTo(to: number, duration: number, shouldStop: () => boolean = () => false): Promise<boolean> {
    const from = progress.value;
    const startedAt = performance.now();

    return new Promise((resolve) => {
        const step = (now: number): void => {
            if (shouldStop()) {
                resolve(false);
                return;
            }
            const t = duration > 0 ? Math.min(1, (now - startedAt) / duration) : 1;
            progress.value = from + (to - from) * easeOutCubic(t);

            if (t < 1) {
                frameId = requestAnimationFrame(step);
            } else {
                resolve(true);
            }
        };

        if (!isDisposed) {
            frameId = requestAnimationFrame(step);
        }
    });
}

// ── Input boxes ──────────────────────────────────────────────────────────────

function setSlotRef(el: unknown, index: number): void {
    if (el) {
        slotEls[index] = el as HTMLElement;
    }
}

function setInputRef(el: unknown, index: number): void {
    if (el) {
        inputEls[index] = el as HTMLInputElement;
    }
}

function focusBox(index: number): void {
    const input = inputEls[Math.min(Math.max(index, 0), props.length - 1)];
    input?.focus();
    input?.select();
}

function pop(index: number): void {
    if (isStill.value) {
        return;
    }
    animate(inputEls[index], [{ transform: 'scale(1)' }, { transform: 'scale(1.08)' }, { transform: 'scale(1)' }], {
        duration: timing.pop,
        easing: timing.spring,
    });
}

/** Writes several digits starting at `start` (paste or one-time-code autofill). */
function fillFrom(start: number, value: string): void {
    const chars = value.slice(0, props.length - start).split('');
    chars.forEach((char, offset) => (digits.value[start + offset] = char));

    if (isComplete.value) {
        submit();
    } else {
        focusBox(start + chars.length);
    }
}

function onInput(index: number, event: Event): void {
    const input = event.target as HTMLInputElement;
    const inputEvent = event as InputEvent;
    // When typing, use only the typed key, so typing over a filled box replaces its digit.
    const raw = inputEvent.inputType === 'insertText' && inputEvent.data ? inputEvent.data : input.value;
    const typed = raw.replace(/\D/g, '');

    if (typed.length > 1) {
        input.value = digits.value[index];
        fillFrom(index, typed);
        return;
    }

    digits.value[index] = typed;
    input.value = typed;

    if (!typed) {
        return;
    }

    pop(index);

    if (isComplete.value) {
        submit();
    } else {
        focusBox(index + 1);
    }
}

function onKeydown(index: number, event: KeyboardEvent): void {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        event.preventDefault();
        digits.value[index - 1] = '';
        focusBox(index - 1);
    } else if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        focusBox(index - 1);
    } else if (event.key === 'ArrowRight' && index < props.length - 1) {
        event.preventDefault();
        focusBox(index + 1);
    }
}

function onPaste(event: ClipboardEvent): void {
    event.preventDefault();
    const pasted = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '');

    if (pasted && !isInputDisabled.value) {
        fillFrom(pasted.length >= props.length ? 0 : (activeIndex.value ?? 0), pasted);
    }
}

function onFocus(index: number, event: FocusEvent): void {
    activeIndex.value = index;
    (event.target as HTMLInputElement).select();
}

function onBlur(index: number): void {
    if (activeIndex.value === index) {
        activeIndex.value = null;
    }
}

// ── Messages and resend ─────────────────────────────────────────────────────

function showMessage(text: string, tone: MessageTone): void {
    message.value = { id: ++messageId, text, tone };
}

function withAttemptsLeft(text: string, attemptsLeft?: number): string {
    if (attemptsLeft === undefined) {
        return text;
    }

    return `${text} ${attemptsLeft} ${attemptsLeft === 1 ? 'attempt' : 'attempts'} left.`;
}

function startCountdown(seconds: number): void {
    window.clearInterval(countdownId);
    resendSeconds.value = Math.max(0, Math.ceil(seconds));

    if (isDisposed || resendSeconds.value === 0) {
        return;
    }

    countdownId = window.setInterval(() => {
        resendSeconds.value -= 1;
        if (resendSeconds.value <= 0) {
            window.clearInterval(countdownId);
        }
    }, 1000);
}

/** The code can't be used any more: lock the inputs and Resend, and offer "Register again". */
function block(text: string): void {
    isBlocked.value = true;
    headingKey.value = 'failed';
    showMessage(text, 'error');
}

async function resend(): Promise<void> {
    if (!canResend.value) {
        return;
    }

    isResending.value = true;

    try {
        const { data } = await axios.post<OtpResponse>(route('register.resend-otp'), { email: props.email }, { silentErrors: true });

        digits.value = Array(props.length).fill('');
        showMessage(data.message, 'success');
        startCountdown(data.resend_available_in ?? 60);
        focusBox(0);
    } catch (error) {
        const response = (error as AxiosError<OtpResponse>).response;

        if (response?.status === 429) {
            // Our own cooldown sends retry_after; the route throttle only sends the Retry-After header.
            const retryAfter = Number(response.data?.retry_after ?? response.headers['retry-after']);
            startCountdown(Number.isFinite(retryAfter) ? retryAfter : 60);
            showMessage(response.data?.message ?? 'Please wait before requesting a new code.', 'error');
        } else if (response?.data?.expired) {
            block(response.data.message);
        } else {
            showMessage(httpErrorMessage(error), 'error');
        }
    } finally {
        isResending.value = false;
    }
}

// ── Verify: input → submitting → verifying → success | error → input ─────

function requestVerification(code: string): Promise<VerifyResult> {
    return axios
        .post<OtpResponse>(route('register.store'), { email: props.email, otp: code }, { silentErrors: true })
        .then(({ data }) => ({ ok: true, redirect: data.redirect ?? '/', message: data.message, isFinal: false }))
        .catch((error: AxiosError<OtpResponse>) => {
            const data = error.response?.data;

            if (error.response?.status === 422 && data?.message) {
                return {
                    ok: false,
                    redirect: '',
                    message: withAttemptsLeft(data.message, data.attempts_left),
                    isFinal: Boolean(data.expired || data.locked),
                };
            }

            return { ok: false, redirect: '', message: httpErrorMessage(error), isFinal: false };
        });
}

async function submit(): Promise<void> {
    if (phase.value !== 'input' || isBlocked.value || !isComplete.value) {
        return;
    }

    // The request starts with the animation, not after it.
    const result = requestVerification(digits.value.join(''));

    if (activeIndex.value !== null) {
        inputEls[activeIndex.value]?.blur();
    }
    phase.value = 'submitting';
    message.value = null;
    progress.value = 0;
    announcement.value = 'Verifying your code.';

    await playSubmit();

    phase.value = 'verifying';
    const outcome = await playVerifying(result);

    if (outcome.ok) {
        await playSuccess(outcome.redirect);
    } else {
        await playError(outcome);
    }
}

/** Center of each tile relative to the center of the row, measured at rest. */
function measureSlotOffsets(): { x: number; y: number }[] {
    const row = rowEl.value!.getBoundingClientRect();
    const centerX = row.left + row.width / 2;
    const centerY = row.top + row.height / 2;

    return slotEls.map((slot) => {
        const box = slot.getBoundingClientRect();

        return { x: box.left + box.width / 2 - centerX, y: box.top + box.height / 2 - centerY };
    });
}

async function playSubmit(): Promise<void> {
    const t = timing;

    if (isStill.value) {
        slotEls.forEach((slot) => animate(slot, [{ opacity: 1 }, { opacity: 0 }], { duration: t.crossfade, fill: 'forwards' }));
        headingKey.value = 'verifying';
        await wait(t.crossfade);
        return;
    }

    const offsets = measureSlotOffsets();
    const tilt = (index: number): number => TILE_TILTS[index % TILE_TILTS.length];

    // a) Lift: tiles rise, grow a little and their borders brighten.
    const lifted = 'translate(0px, -6px) scale(1.05)';
    isLifted.value = true;
    slotEls.forEach((slot) =>
        animate(slot, [{ transform: 'none' }, { transform: lifted }], { duration: t.lift, easing: t.easeOut, fill: 'forwards' }),
    );
    await wait(t.lift);

    // b) Scatter into a hexagon ring: one tile every 60°, starting at the top, clockwise.
    const ringPositions = offsets.map((offset, index) => {
        const angle = (index / props.length) * Math.PI * 2;
        const x = Math.sin(angle) * SCATTER_RADIUS - offset.x;
        const y = -Math.cos(angle) * SCATTER_RADIUS - offset.y;

        return `translate(${x}px, ${y}px) rotate(${tilt(index)}deg) scale(1.05)`;
    });
    slotEls.forEach((slot, index) =>
        animate(slot, [{ transform: lifted }, { transform: ringPositions[index] }], { duration: t.scatter, easing: t.spring, fill: 'forwards' }),
    );
    await wait(t.scatter);

    // c) Orbit: the row spins 270° while each tile bobs ±3px.
    animate(rowEl.value, [{ transform: 'rotate(0deg)' }, { transform: 'rotate(270deg)' }], {
        duration: t.orbit,
        easing: t.easeInOut,
        fill: 'forwards',
    });
    inputEls.forEach((input, index) => {
        const bob = index % 2 === 0 ? -3 : 3;
        animate(
            input,
            [
                { transform: 'translateY(0)' },
                { transform: `translateY(${bob}px)` },
                { transform: `translateY(${-bob}px)` },
                { transform: 'translateY(0)' },
            ],
            { duration: t.orbit, easing: 'ease-in-out' },
        );
    });
    await wait(t.orbit);

    // d) Collapse into the center, one tile after another. The whole phase lasts `collapse`.
    const tileDuration = Math.max(120, t.collapse - t.collapseStagger * (props.length - 1));
    slotEls.forEach((slot, index) =>
        animate(
            slot,
            [
                { transform: ringPositions[index], opacity: 1 },
                { transform: `translate(${-offsets[index].x}px, ${-offsets[index].y}px) rotate(${tilt(index)}deg) scale(0.2)`, opacity: 0 },
            ],
            { duration: tileDuration, delay: index * t.collapseStagger, easing: t.easeIn, fill: 'forwards' },
        ),
    );
    await wait(Math.max(0, t.collapse - t.crossfade));
    headingKey.value = 'verifying';
    await wait(t.crossfade);
}

async function playVerifying(request: Promise<VerifyResult>): Promise<VerifyResult> {
    let result: VerifyResult | null = null;
    request.then((value) => (result = value));

    showRing.value = true;
    const startedAt = performance.now();
    // An error that came back early still lets the ring count for a moment, so it never fails at 0.
    const hasError = (): boolean => result !== null && !result.ok && performance.now() - startedAt >= timing.errorMin;

    await countTo(HOLD_PROGRESS, timing.count, hasError);

    // Hold at 92 (the halo keeps pulsing) until the server answers.
    const outcome = result ?? (await request);

    if (outcome.ok) {
        await countTo(100, timing.countFinish);
    }

    return outcome;
}

function spawnConfetti(count: number): void {
    const t = timing;
    const startRadius = 34;

    confetti.value = Array.from({ length: count }, (_, index) => {
        const angle = Math.random() * Math.PI * 2;
        const distance = 50 + Math.random() * 60;
        const isDot = Math.random() < 0.5;
        const size = isDot ? 4 + Math.random() * 2 : 5 + Math.random() * 3;
        const startRotation = !isDot && Math.random() < 0.5 ? 45 : 0;

        return {
            id: ++confettiId,
            style: {
                width: `${size}px`,
                height: `${size}px`,
                borderRadius: isDot ? '50%' : '1px',
                background: `var(--otp-confetti-${(index % 4) + 1})`,
                '--from-x': `${Math.cos(angle) * startRadius}px`,
                '--from-y': `${Math.sin(angle) * startRadius}px`,
                '--to-x': `${Math.cos(angle) * (startRadius + distance)}px`,
                '--to-y': `${Math.sin(angle) * (startRadius + distance)}px`,
                '--from-rotate': `${startRotation}deg`,
                '--to-rotate': `${startRotation + (Math.random() * 2 - 1) * 360}deg`,
                animationDuration: `${t.confettiMin + Math.random() * (t.confettiMax - t.confettiMin)}ms`,
                animationDelay: `${Math.random() * t.confettiDelayMax}ms`,
            },
        };
    });
}

function removeConfetti(id: number): void {
    confetti.value = confetti.value.filter((piece) => piece.id !== id);
}

async function playSuccess(redirect: string): Promise<void> {
    const t = timing;
    phase.value = 'success';
    announcement.value = 'Email verified. Taking you to the homepage.';

    // a) Morph: the ring turns into a solid green circle and the glow grows.
    if (isStill.value) {
        animate(successCircleEl.value, [{ opacity: 0 }, { opacity: 1 }], { duration: t.crossfade, fill: 'forwards' });
        animate(successGlowEl.value, [{ opacity: 0 }, { opacity: 1 }], { duration: t.crossfade, fill: 'forwards' });
    } else {
        animate(
            successCircleEl.value,
            [
                { transform: 'scale(0.8)', opacity: 0 },
                { transform: 'scale(1.15)', opacity: 1, offset: 0.6 },
                { transform: 'scale(1)', opacity: 1 },
            ],
            { duration: t.morph, easing: t.easeOut, fill: 'forwards' },
        );
        animate(
            successGlowEl.value,
            [
                { transform: 'scale(0.6)', opacity: 0 },
                { transform: 'scale(1)', opacity: 1 },
            ],
            {
                duration: t.morph * 2,
                easing: t.easeOut,
                fill: 'forwards',
            },
        );
    }
    await wait(t.burstAt);

    // b) Burst: new heading, green card, confetti.
    headingKey.value = 'success';
    emit('celebrating', true);
    if (!isStill.value) {
        spawnConfetti(motionLevel.value === 'calm' ? 12 : 28);
    }
    await wait(t.checkAt - t.burstAt);

    // c) Checkmark draws in (already visible in reduced motion).
    if (!isStill.value) {
        animate(checkEl.value, [{ strokeDashoffset: `${CHECK_LENGTH}` }, { strokeDashoffset: '0' }], {
            duration: t.check,
            easing: t.easeOut,
            fill: 'forwards',
        });
    }
    await wait(t.badgeAt - t.checkAt);

    // d) "VERIFIED" badge, then hold before leaving.
    showBadge.value = true;
    await wait(t.badge + t.successHold);

    emit('verified', redirect);
}

async function playError(result: VerifyResult): Promise<void> {
    phase.value = 'error';
    announcement.value = '';

    if (!isStill.value) {
        animate(
            rootEl.value,
            [0, -8, 8, -6, 6, -3, 0].map((x) => ({ transform: `translateX(${x}px)` })),
            { duration: timing.shake, easing: 'ease-in-out' },
        );
    }

    if (result.isFinal) {
        block(result.message);
        return;
    }

    headingKey.value = 'input';
    showMessage(result.message, 'error');
    await wait(timing.errorHold);
    await resetToInput();
}

async function resetToInput(): Promise<void> {
    const t = timing;

    cancelAnimations();
    emit('celebrating', false);
    isLifted.value = false;
    showRing.value = false;
    showBadge.value = false;
    confetti.value = [];
    digits.value = Array(props.length).fill('');
    headingKey.value = 'input';

    slotEls.forEach((slot, index) =>
        animate(
            slot,
            isStill.value
                ? [{ opacity: 0 }, { opacity: 1 }]
                : [
                      { opacity: 0, transform: 'scale(0.8)' },
                      { opacity: 1, transform: 'scale(1)' },
                  ],
            {
                duration: isStill.value ? t.crossfade : t.reset,
                delay: isStill.value ? 0 : index * t.resetStagger,
                easing: t.easeOut,
                fill: 'backwards',
            },
        ),
    );

    phase.value = 'input';
    await nextTick();
    focusBox(0);
}

onMounted(() => {
    timing = readTimings();
    startCountdown(props.resendAvailableIn);
    focusBox(0);
});

onBeforeUnmount(() => {
    isDisposed = true;
    timers.forEach((id) => window.clearTimeout(id));
    timers.clear();
    cancelAnimationFrame(frameId);
    window.clearInterval(countdownId);
    cancelAnimations();
});
</script>

<template>
    <div
        ref="rootEl"
        class="otp flex flex-col items-center gap-5 font-sans"
        :class="{ 'otp--glass': isGlass, 'otp--still': isStill, 'is-lifted': isLifted }"
        :style="{ '--otp-count': length }"
    >
        <!-- Icon -->
        <div class="otp-icon flex h-16 w-16 items-center justify-center rounded-2xl">
            <Mail class="h-8 w-8" />
        </div>

        <!-- Title and subtitle, crossfaded per state -->
        <div class="grid w-full text-center">
            <Transition name="otp-fade">
                <div :key="headingKey" class="[grid-area:1/1]">
                    <h1 class="otp-title text-2xl font-bold">{{ heading.title }}</h1>
                    <p class="otp-subtitle mt-1 text-sm">
                        <template v-if="headingKey === 'input'">
                            Enter the {{ length }}-digit code sent to <strong>{{ email }}</strong>
                        </template>
                        <template v-else>{{ heading.subtitle }}</template>
                    </p>
                </div>
            </Transition>
        </div>

        <!-- Animation area -->
        <form class="w-full" novalidate @submit.prevent="submit">
            <div class="otp-stage">
                <div ref="rowEl" class="otp-row" role="group" aria-label="Verification code" @paste="onPaste">
                    <span
                        v-for="(digit, i) in digits"
                        :key="i"
                        :ref="(el: unknown) => setSlotRef(el, i)"
                        class="otp-slot"
                        :class="{ 'is-active': activeIndex === i && phase === 'input' }"
                    >
                        <input
                            :ref="(el: unknown) => setInputRef(el, i)"
                            :value="digit"
                            class="otp-box"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            :autocomplete="i === 0 ? 'one-time-code' : 'off'"
                            :aria-label="`Digit ${i + 1} of ${length}`"
                            :disabled="isInputDisabled"
                            @input="onInput(i, $event)"
                            @keydown="onKeydown(i, $event)"
                            @focus="onFocus(i, $event)"
                            @blur="onBlur(i)"
                        />
                        <span v-if="activeIndex === i && !digit && phase === 'input'" class="otp-caret" aria-hidden="true"></span>
                    </span>
                </div>

                <Transition name="otp-ring">
                    <div
                        v-if="showRing"
                        class="otp-ring"
                        :class="{ 'is-success': phase === 'success', 'is-error': phase === 'error' }"
                        aria-hidden="true"
                    >
                        <span v-if="phase === 'verifying' && motionLevel === 'full'" class="otp-halo"></span>
                        <span ref="successGlowEl" class="otp-success-glow"></span>
                        <span class="otp-confetti-origin">
                            <span
                                v-for="piece in confetti"
                                :key="piece.id"
                                class="otp-confetti"
                                :style="piece.style"
                                @animationend="removeConfetti(piece.id)"
                            ></span>
                        </span>
                        <svg class="otp-ring__svg" :viewBox="`0 0 ${RING_SIZE} ${RING_SIZE}`">
                            <circle
                                class="otp-ring__track"
                                :cx="RING_SIZE / 2"
                                :cy="RING_SIZE / 2"
                                :r="RING_RADIUS"
                                fill="none"
                                :stroke-width="RING_STROKE"
                            />
                            <circle
                                class="otp-ring__progress"
                                :cx="RING_SIZE / 2"
                                :cy="RING_SIZE / 2"
                                :r="RING_RADIUS"
                                fill="none"
                                :stroke-width="RING_STROKE"
                                stroke-linecap="round"
                                :stroke-dasharray="RING_CIRCUMFERENCE"
                                :stroke-dashoffset="RING_CIRCUMFERENCE * (1 - progress / 100)"
                                :stroke-opacity="progress > 0 ? 1 : 0"
                            />
                        </svg>
                        <span class="otp-ring__value">{{ Math.round(progress) }}</span>
                        <span ref="successCircleEl" class="otp-success-circle">
                            <svg class="h-full w-full" :viewBox="`0 0 ${RING_SIZE} ${RING_SIZE}`">
                                <path
                                    ref="checkEl"
                                    class="otp-check"
                                    d="M27 43l10.5 10L57 33"
                                    fill="none"
                                    stroke-width="6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    :style="{ strokeDasharray: CHECK_LENGTH, strokeDashoffset: isStill ? 0 : CHECK_LENGTH }"
                                />
                            </svg>
                        </span>
                    </div>
                </Transition>

                <Transition name="otp-badge">
                    <span v-if="showBadge" class="otp-badge">Verified</span>
                </Transition>
            </div>

            <!-- Lets Enter submit the form; auto-submit covers everyone else. -->
            <button type="submit" class="sr-only" tabindex="-1">Verify code</button>
        </form>

        <!-- Status line -->
        <div class="otp-message w-full" aria-live="polite">
            <span class="sr-only">{{ announcement }}</span>
            <Transition name="otp-fade" mode="out-in">
                <p v-if="message" :key="message.id" :class="message.tone === 'error' ? 'otp-message--error' : 'otp-message--success'">
                    {{ message.text }}
                </p>
            </Transition>
            <Link v-if="isBlocked" :href="route('register')" class="otp-link mt-1 inline-block">Register again</Link>
        </div>

        <!-- Footer -->
        <p class="otp-footer text-sm">
            Didn't receive the code?
            <button type="button" class="otp-link" :disabled="!canResend" @click="resend">{{ resendLabel }}</button>
        </p>
    </div>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
 * THEME TOKENS: every color in this component comes from these variables.
 * Default (.otp)  = Plain: white card on the light blue gradient (no background photo).
 * .otp--glass     = Glass: translucent blue card over the dark-blue photo overlay.
 * Contrast ratios are against the card surface.
 * ═══════════════════════════════════════════════════════════════════════════ */
.otp {
    --otp-text: #111827; /* gray-900 */
    --otp-muted: #4b5563; /* gray-600, 7.5:1 */
    --otp-box: #f3f4f6; /* gray-100 */
    --otp-box-border: #9ca3af; /* gray-400 */
    --otp-accent: #1d4ed8; /* blue-700, the auth button blue, 6.7:1 */
    --otp-accent-glow: rgb(29 78 216 / 0.2);
    --otp-success: #15803d; /* green-700, 5:1 */
    --otp-success-glow: rgb(22 163 74 / 0.3);
    --otp-error: #b91c1c; /* red-700, 6.5:1 */
    --otp-check: #ffffff;
    --otp-confetti-1: #16a34a; /* green */
    --otp-confetti-2: #2563eb; /* accent blue */
    --otp-confetti-3: #f59e0b; /* gold */
    --otp-confetti-4: #0284c7; /* sky */
    --otp-icon-bg: #eff6ff; /* mail icon tile, same on both surfaces */
    --otp-icon: #3b82f6;
}

.otp--glass {
    --otp-text: #ffffff;
    --otp-muted: rgb(255 255 255 / 0.8);
    --otp-box: rgb(255 255 255 / 0.1);
    --otp-box-border: rgb(255 255 255 / 0.3);
    --otp-accent: #60a5fa; /* blue-400, readable on the dark overlay */
    --otp-accent-glow: rgb(96 165 250 / 0.5);
    --otp-success: #22c55e; /* green-500 */
    --otp-success-glow: rgb(74 222 128 / 0.55);
    --otp-error: #fca5a5; /* red-300 */
    --otp-check: #ffffff;
    --otp-confetti-1: #4ade80;
    --otp-confetti-2: #60a5fa;
    --otp-confetti-3: #fcd34d;
    --otp-confetti-4: #7dd3fc;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * TIMING: CSS and JS (via getComputedStyle) both read these, so tune here.
 * ═══════════════════════════════════════════════════════════════════════════ */
.otp {
    --otp-ease-out: cubic-bezier(0.22, 1, 0.36, 1);
    --otp-ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
    --otp-ease-in: cubic-bezier(0.55, 0, 1, 0.45);
    --otp-ease-in-out: cubic-bezier(0.65, 0, 0.35, 1);

    /* Input */
    --otp-dur-pop: 150ms;
    --otp-dur-glow-pulse: 1.2s;
    --otp-dur-caret: 1s;

    /* Submit: lift → scatter → orbit → collapse (about 1.75s) */
    --otp-dur-lift: 250ms;
    --otp-dur-scatter: 300ms;
    --otp-dur-orbit: 750ms;
    --otp-dur-collapse: 450ms; /* the whole phase; each tile runs this minus the stagger */
    --otp-collapse-stagger: 60ms;
    --otp-dur-crossfade: 200ms; /* title swaps, and every fade in reduced motion */

    /* Verifying */
    --otp-dur-ring-in: 250ms;
    --otp-dur-count: 1600ms; /* 0 → 92 */
    --otp-dur-count-finish: 300ms; /* 92 → 100 */
    --otp-dur-error-min: 600ms; /* an early error waits this long into the count */
    --otp-dur-halo: 1s;

    /* Success: the *-at values are start times from the moment the code is accepted */
    --otp-dur-morph: 300ms;
    --otp-success-burst-at: 300ms;
    --otp-success-check-at: 400ms;
    --otp-success-badge-at: 900ms;
    --otp-dur-check: 400ms;
    --otp-dur-badge: 250ms;
    --otp-dur-confetti-min: 700ms;
    --otp-dur-confetti-max: 900ms;
    --otp-confetti-delay-max: 120ms;
    --otp-dur-success-hold: 1500ms;

    /* Error and reset */
    --otp-dur-error-color: 200ms;
    --otp-dur-shake: 400ms;
    --otp-dur-error-hold: 1000ms;
    --otp-dur-reset: 300ms;
    --otp-reset-stagger: 50ms;
}

/* ── Sizes ── */
.otp {
    --otp-gap: 8px;
    /* 112px = the layout's side padding (2 × 24px) plus the card's (2 × 32px), so the row never overflows a phone. */
    --otp-box-w: min(44px, calc((100vw - 112px - (var(--otp-count) - 1) * var(--otp-gap)) / var(--otp-count)));
    --otp-box-h: calc(var(--otp-box-w) * 0.75 + 19px); /* 44 → 52, 36 → 46 */
}

@media (max-width: 419px) {
    .otp {
        --otp-gap: 6px;
    }
}

/* ── Header ── */
.otp-icon {
    background: var(--otp-icon-bg);
    color: var(--otp-icon);
}

.otp-title {
    color: var(--otp-text);
}

.otp-subtitle {
    min-height: 2.5rem;
    color: var(--otp-muted);
    overflow-wrap: anywhere;
}

.otp-subtitle strong {
    color: var(--otp-text);
    font-weight: 600;
}

/* ── Stage: every layer sits in the same centered grid cell ── */
.otp-stage {
    position: relative;
    display: grid;
    place-items: center;
    width: 100%;
    height: 190px;
}

.otp-stage > * {
    grid-area: 1 / 1;
}

/* ── Input boxes ── */
.otp-row {
    display: flex;
    gap: var(--otp-gap);
}

.otp-slot {
    position: relative;
    isolation: isolate;
    border-radius: 10px;
}

.otp-slot::before,
.otp-slot::after {
    content: '';
    position: absolute;
    pointer-events: none;
    opacity: 0;
}

/* Accent border: the active box, and every box while the tiles lift */
.otp-slot::before {
    inset: 0;
    z-index: 1;
    border: 2px solid var(--otp-accent);
    border-radius: inherit;
    transition: opacity var(--otp-dur-lift) var(--otp-ease-out);
}

/* Soft outer glow behind the active box */
.otp-slot::after {
    inset: -3px;
    z-index: -1;
    border-radius: 13px;
    box-shadow:
        0 0 0 3px var(--otp-accent-glow),
        0 0 16px 2px var(--otp-accent-glow);
}

.otp-slot.is-active::before,
.is-lifted .otp-slot::before {
    opacity: 1;
}

.otp-slot.is-active::after {
    animation: otp-glow-pulse var(--otp-dur-glow-pulse) ease-in-out infinite;
}

.otp--still .otp-slot.is-active::after {
    animation: none;
    opacity: 1;
}

.otp-box {
    display: block;
    width: var(--otp-box-w);
    height: var(--otp-box-h);
    padding: 0;
    border: 1.5px solid var(--otp-box-border);
    border-radius: inherit;
    background: var(--otp-box);
    color: var(--otp-text);
    caret-color: transparent;
    font: inherit;
    font-size: min(1.375rem, calc(var(--otp-box-w) * 0.5));
    font-weight: 700;
    text-align: center;
    outline: none;
    appearance: none;
}

.otp-box::selection {
    background: transparent;
}

.otp-box:disabled {
    cursor: default;
    opacity: 1;
    -webkit-text-fill-color: currentColor;
}

.otp-caret {
    position: absolute;
    top: 28%;
    left: 50%;
    z-index: 2;
    width: 2px;
    height: 44%;
    margin-left: -1px;
    border-radius: 1px;
    background: var(--otp-accent);
    pointer-events: none;
    animation: otp-caret-blink var(--otp-dur-caret) steps(1) infinite;
}

/* ── Progress ring and success circle ── */
.otp-ring {
    position: relative;
    width: 84px;
    height: 84px;
}

.otp-ring > * {
    position: absolute;
}

.otp-halo {
    inset: 0;
    border-radius: 50%;
    background: var(--otp-accent);
    opacity: 0;
    animation: otp-halo var(--otp-dur-halo) var(--otp-ease-out) infinite;
}

.otp-ring__svg {
    inset: 0;
    width: 100%;
    height: 100%;
    overflow: visible;
    transform: rotate(-90deg);
}

.otp-ring__track {
    stroke: var(--otp-accent);
    stroke-opacity: 0.18;
}

.otp-ring__progress {
    stroke: var(--otp-accent);
    transition: stroke var(--otp-dur-error-color) ease;
}

.otp-ring__value {
    inset: 0;
    display: grid;
    place-items: center;
    color: var(--otp-text);
    font-size: 1.25rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    transition:
        color var(--otp-dur-error-color) ease,
        opacity var(--otp-dur-morph) var(--otp-ease-out);
}

.otp-ring.is-error .otp-ring__progress {
    stroke: var(--otp-error);
}

.otp-ring.is-error .otp-ring__value {
    color: var(--otp-error);
}

.otp-ring.is-success .otp-ring__progress {
    stroke: var(--otp-success);
}

.otp-ring.is-success .otp-ring__value {
    opacity: 0;
}

.otp-success-glow {
    inset: -40px;
    border-radius: 50%;
    background: radial-gradient(circle, var(--otp-success-glow) 0%, transparent 68%);
    opacity: 0;
}

.otp-success-circle {
    inset: 0;
    border-radius: 50%;
    background: var(--otp-success);
    box-shadow: 0 0 24px var(--otp-success-glow);
    opacity: 0;
}

.otp-check {
    stroke: var(--otp-check);
}

/* ── Confetti ── */
.otp-confetti-origin {
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
}

.otp-confetti {
    position: absolute;
    top: 0;
    left: 0;
    animation-name: otp-confetti;
    animation-timing-function: var(--otp-ease-out);
    animation-fill-mode: both;
}

/* ── Badge ── */
.otp-badge {
    position: absolute;
    top: calc(50% + 56px);
    right: 0;
    left: 0;
    width: fit-content;
    margin: 0 auto;
    padding: 2px 10px;
    border: 1px solid var(--otp-success);
    border-radius: 999px;
    box-shadow: 0 0 12px var(--otp-success-glow);
    color: var(--otp-success);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

/* ── Message line and footer ── */
.otp-message {
    min-height: 2.5rem;
    font-size: 0.875rem;
    text-align: center;
}

.otp-message--error {
    color: var(--otp-error);
}

.otp-message--success {
    color: var(--otp-success);
}

.otp-footer {
    color: var(--otp-muted);
    text-align: center;
}

.otp-link {
    border-radius: 4px;
    color: var(--otp-accent);
    font-weight: 600;
}

.otp-link:hover:not(:disabled) {
    text-decoration: underline;
    text-underline-offset: 3px;
}

.otp-link:focus-visible {
    outline: 2px solid var(--otp-accent);
    outline-offset: 2px;
}

.otp-link:disabled {
    color: var(--otp-muted);
    cursor: default;
    font-weight: 500;
}

/* ── Vue transitions ── */
.otp-fade-enter-active,
.otp-fade-leave-active {
    transition: opacity var(--otp-dur-crossfade) ease;
}

.otp-fade-enter-from,
.otp-fade-leave-to {
    opacity: 0;
}

.otp-ring-enter-active {
    transition:
        opacity var(--otp-dur-ring-in) var(--otp-ease-out),
        transform var(--otp-dur-ring-in) var(--otp-ease-spring);
}

.otp-ring-leave-active {
    transition: opacity var(--otp-dur-crossfade) ease;
}

.otp-ring-enter-from {
    opacity: 0;
    transform: scale(0.6);
}

.otp-ring-leave-to {
    opacity: 0;
}

.otp-badge-enter-active {
    transition:
        opacity var(--otp-dur-badge) var(--otp-ease-out),
        transform var(--otp-dur-badge) var(--otp-ease-spring);
}

.otp-badge-enter-from {
    opacity: 0;
    transform: translateY(6px) scale(0.8);
}

/* Reduced motion: plain crossfades only */
.otp--still .otp-ring-enter-active,
.otp--still .otp-badge-enter-active {
    transition: opacity var(--otp-dur-crossfade) ease;
}

.otp--still .otp-ring-enter-from,
.otp--still .otp-badge-enter-from {
    transform: none;
}

/* ── Keyframes ── */
@keyframes otp-glow-pulse {
    0%,
    100% {
        opacity: 0.6;
    }
    50% {
        opacity: 1;
    }
}

@keyframes otp-caret-blink {
    50% {
        opacity: 0;
    }
}

@keyframes otp-halo {
    from {
        transform: scale(1);
        opacity: 0.35;
    }
    to {
        transform: scale(1.35);
        opacity: 0;
    }
}

@keyframes otp-confetti {
    from {
        transform: translate(var(--from-x), var(--from-y)) translate(-50%, -50%) rotate(var(--from-rotate));
        opacity: 1;
    }
    70% {
        opacity: 1;
    }
    to {
        transform: translate(var(--to-x), var(--to-y)) translate(-50%, -50%) rotate(var(--to-rotate));
        opacity: 0;
    }
}
</style>
