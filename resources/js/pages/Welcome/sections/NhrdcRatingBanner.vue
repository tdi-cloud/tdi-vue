<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ClipboardCheck, UserCheck } from 'lucide-vue-next';

defineProps({
    data: { type: Object, required: true }, // { pending, rated, total, representing }
});
</script>

<template>
    <section id="nhrdc-rating" class="nhrdc-banner">
        <div class="nhrdc-banner__card">
            <div class="nhrdc-banner__glow" aria-hidden="true"></div>

            <div class="nhrdc-banner__inner">
                <div class="nhrdc-banner__icon">
                    <UserCheck :size="26" />
                </div>

                <div class="nhrdc-banner__text">
                    <span class="nhrdc-banner__eyebrow"> <ClipboardCheck :size="14" /> NHRDC Interview Rating </span>

                    <h2 v-if="data.pending" class="nhrdc-banner__title">
                        You have {{ data.pending }} nominee interview<span v-if="data.pending > 1">s</span>
                        awaiting your rating
                    </h2>
                    <h2 v-else class="nhrdc-banner__title">You have rated all {{ data.rated }} nominee<span v-if="data.rated > 1">s</span></h2>

                    <p v-if="data.pending && data.representing" class="nhrdc-banner__lead">
                        As the authorized representative of {{ data.representing }}, please rate the interview of the nominees on their behalf.
                        {{ data.rated }} of {{ data.total }} already rated.
                    </p>
                    <p v-else-if="data.pending" class="nhrdc-banner__lead">
                        As an NHRDC committee member, please rate the interview of the nominees assigned to you.
                        {{ data.rated }} of {{ data.total }} already rated.
                    </p>
                    <p v-else class="nhrdc-banner__lead">All caught up. You can still review or update your ratings anytime.</p>
                </div>

                <Link :href="route('nhrdc.programs.index')" class="nhrdc-banner__cta">
                    {{ data.pending ? 'Rate Now' : 'View Ratings' }}
                    <ArrowRight :size="17" />
                </Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.nhrdc-banner {
    padding: 0 1.5rem 1.5rem;
    background: #fff;
}
.nhrdc-banner__card {
    position: relative;
    isolation: isolate;
    max-width: 1120px;
    margin: 0 auto;
    overflow: hidden;
    border-radius: 20px;
    padding: 1.75rem 2rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background:
        radial-gradient(120% 140% at 100% 100%, rgba(14, 165, 233, 0.45) 0%, transparent 55%),
        linear-gradient(135deg, #3730a3 0%, #4338ca 35%, #4f46e5 65%, #2563eb 100%);
    box-shadow:
        0 1px 2px rgba(30, 27, 75, 0.12),
        0 20px 44px -12px rgba(55, 48, 163, 0.38),
        inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

/* Subtle dot grid, faded toward the left so text stays clean */
.nhrdc-banner__card::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1.2px);
    background-size: 18px 18px;
    -webkit-mask-image: linear-gradient(100deg, transparent 35%, #000 85%);
    mask-image: linear-gradient(100deg, transparent 35%, #000 85%);
    pointer-events: none;
}

/* Concentric rings anchored behind the CTA */
.nhrdc-banner__card::after {
    content: '';
    position: absolute;
    top: 50%;
    right: -90px;
    z-index: -1;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow:
        0 0 0 36px rgba(255, 255, 255, 0.025),
        0 0 0 37px rgba(255, 255, 255, 0.07);
    transform: translateY(-50%);
    pointer-events: none;
}
.nhrdc-banner__glow {
    position: absolute;
    top: -110px;
    left: -70px;
    z-index: -1;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, rgba(199, 210, 254, 0.08) 45%, transparent 70%);
    filter: blur(6px);
    pointer-events: none;
}
.nhrdc-banner__inner {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1.25rem;
}
.nhrdc-banner__icon {
    flex-shrink: 0;
    width: 54px;
    height: 54px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.22);
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.24), rgba(255, 255, 255, 0.08));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow:
        0 8px 18px -6px rgba(30, 27, 75, 0.45),
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
}
.nhrdc-banner__text {
    flex: 1;
    min-width: 0;
}
.nhrdc-banner__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.12);
    color: #e0e7ff;
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    padding: 0.38rem 0.75rem;
    border-radius: 999px;
}
.nhrdc-banner__title {
    margin-top: 0.65rem;
    font-size: clamp(1.15rem, 1rem + 0.6vw, 1.4rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.25;
    letter-spacing: -0.015em;
    text-wrap: balance;
    overflow-wrap: anywhere;
    text-shadow: 0 1px 2px rgba(30, 27, 75, 0.25);
}
.nhrdc-banner__lead {
    margin-top: 0.45rem;
    font-size: 0.9rem;
    line-height: 1.55;
    color: rgba(238, 242, 255, 0.86);
    max-width: 40rem;
    text-wrap: pretty;
}
.nhrdc-banner__cta {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #fff;
    color: #3730a3;
    font-weight: 800;
    font-size: 0.9rem;
    letter-spacing: 0.01em;
    padding: 0.75rem 1.45rem;
    border-radius: 12px;
    text-decoration: none;
    white-space: nowrap;
    box-shadow:
        0 1px 2px rgba(30, 27, 75, 0.15),
        0 8px 20px -4px rgba(30, 27, 75, 0.35),
        inset 0 -1px 0 rgba(67, 56, 202, 0.12);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease;
}
.nhrdc-banner__cta svg {
    transition: transform 0.2s ease;
}
.nhrdc-banner__cta:hover {
    transform: translateY(-2px);
    background: #f8faff;
    box-shadow:
        0 2px 4px rgba(30, 27, 75, 0.15),
        0 14px 28px -6px rgba(30, 27, 75, 0.45),
        inset 0 -1px 0 rgba(67, 56, 202, 0.12);
}
.nhrdc-banner__cta:hover svg {
    transform: translateX(3px);
}
.nhrdc-banner__cta:active {
    transform: translateY(0);
}
.nhrdc-banner__cta:focus-visible {
    outline: 3px solid #fff;
    outline-offset: 3px;
}

@media (max-width: 820px) {
    .nhrdc-banner__card {
        padding: 1.5rem 1.4rem;
    }
    .nhrdc-banner__card::after {
        top: auto;
        bottom: -170px;
        right: -110px;
        transform: none;
    }
    .nhrdc-banner__inner {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .nhrdc-banner__cta {
        align-self: stretch;
        justify-content: center;
        min-height: 46px;
    }
}

@media (max-width: 480px) {
    .nhrdc-banner {
        padding: 0 1rem 1.25rem;
    }
    .nhrdc-banner__card {
        padding: 1.35rem 1.15rem;
        border-radius: 18px;
    }
    .nhrdc-banner__lead {
        font-size: 0.86rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .nhrdc-banner__cta,
    .nhrdc-banner__cta svg {
        transition: none;
    }
    .nhrdc-banner__cta:hover,
    .nhrdc-banner__cta:hover svg {
        transform: none;
    }
}
</style>
