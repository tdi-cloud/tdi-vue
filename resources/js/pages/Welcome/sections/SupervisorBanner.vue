<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ClipboardList, Users } from 'lucide-vue-next';

defineProps({
    data: { type: Object, required: true }, // { pending }
});
</script>

<template>
    <section id="supervisory" class="sup-banner sup-banner--premium">
        <div class="sup-banner__card">
            <div class="sup-banner__glow" aria-hidden="true"></div>
            <div class="sup-banner__geometry" aria-hidden="true"></div>
            <div class="sup-banner__path" aria-hidden="true"></div>

            <div class="sup-banner__inner">
                <div class="sup-banner__icon">
                    <Users :size="26" />
                </div>

                <div class="sup-banner__text">
                    <span class="sup-banner__eyebrow"> <ClipboardList :size="14" /> Supervisory Rating </span>

                    <h2 v-if="data.pending" class="sup-banner__title">
                        You have {{ data.pending }} self-rating<span v-if="data.pending > 1">s</span>
                        awaiting your review
                    </h2>
                    <h2 v-else class="sup-banner__title">You have rated {{ data.rated }} team member<span v-if="data.rated > 1">s</span></h2>

                    <p v-if="data.pending" class="sup-banner__lead">
                        Some of your team members selected you as their supervisor. Please review and rate their competency assessment.
                    </p>
                    <p v-else class="sup-banner__lead">All caught up. You can review the results of the team members you have rated.</p>
                </div>

                <Link :href="route('tna.supervisory.index')" class="sup-banner__cta">
                    {{ data.pending ? 'Review Now' : 'View Ratings' }}
                    <ArrowRight :size="17" />
                </Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.sup-banner {
    padding: 0 1.5rem 1.5rem;
    background: #fff;
}
.sup-banner__card {
    position: relative;
    max-width: 1120px;
    margin: 0 auto;
    overflow: hidden;
    border-radius: 20px;
    padding: 1.75rem 2rem;
    background: linear-gradient(135deg, #1d3fc4 0%, #2751d6 60%, #1a3aa8 100%);
    box-shadow: 0 18px 44px rgba(29, 63, 196, 0.28);
}
.sup-banner__glow {
    position: absolute;
    top: -100px;
    left: -60px;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.22), transparent 70%);
    pointer-events: none;
}
.sup-banner__inner {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1.25rem;
}
.sup-banner__icon {
    flex-shrink: 0;
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sup-banner__text {
    flex: 1;
    min-width: 0;
}
.sup-banner__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.3rem 0.7rem;
    border-radius: 30px;
}
.sup-banner__title {
    margin-top: 0.6rem;
    font-size: 1.3rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
}
.sup-banner__lead {
    margin-top: 0.4rem;
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.82);
    max-width: 40rem;
}
.sup-banner__cta {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #fff;
    color: #1d3fc4;
    font-weight: 800;
    font-size: 0.9rem;
    padding: 0.7rem 1.4rem;
    border-radius: 10px;
    text-decoration: none;
    transition:
        transform 0.18s,
        box-shadow 0.18s;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
}
.sup-banner__cta:hover {
    transform: translateY(-2px);
}

/* Premium companion styling for TnaBanner. Remove `sup-banner--premium` to restore the original presentation. */
.sup-banner--premium { padding: 0 1.5rem 3.5rem; background: #f7f9fd; }
.sup-banner--premium .sup-banner__card {
    max-width: 1440px;
    padding: 1.6rem 2.25rem;
    border: 1px solid rgba(178, 204, 255, 0.22);
    border-radius: 22px;
    background: linear-gradient(125deg, #06183e 0%, #0c3475 58%, #071d4a 100%);
    box-shadow: 0 18px 40px rgba(10, 32, 79, 0.22), inset 0 1px rgba(255, 255, 255, 0.1);
}
.sup-banner--premium .sup-banner__glow { top: -135px; left: auto; right: 13%; width: 340px; height: 300px; background: radial-gradient(circle, rgba(245, 184, 0, 0.26), transparent 68%); }
.sup-banner__geometry { position: absolute; top: 0; right: 0; width: 210px; height: 100%; opacity: 0.5; background: linear-gradient(135deg, transparent 28%, rgba(51, 104, 183, 0.52) 29% 48%, transparent 49%), linear-gradient(135deg, transparent 58%, rgba(245, 184, 0, 0.7) 59% 62%, transparent 63%); }
.sup-banner__path { position: absolute; bottom: -125px; left: 8%; width: 340px; height: 220px; border: 1px solid rgba(245, 184, 0, 0.6); border-radius: 50% 50% 0 0; opacity: 0.72; transform: rotate(-12deg); }
.sup-banner--premium .sup-banner__inner { gap: 1.15rem; }
.sup-banner--premium .sup-banner__icon { width: 58px; height: 58px; border: 1px solid rgba(245, 184, 0, 0.65); border-radius: 16px; background: rgba(245, 184, 0, 0.12); color: #ffe18a; box-shadow: inset 0 1px rgba(255, 255, 255, 0.09); }
.sup-banner--premium .sup-banner__eyebrow { border: 1px solid rgba(245, 184, 0, 0.6); background: rgba(245, 184, 0, 0.12); color: #ffe18a; }
.sup-banner--premium .sup-banner__title { margin-top: 0.55rem; font-size: clamp(1.25rem, 2.2vw, 1.65rem); letter-spacing: -0.025em; }
.sup-banner--premium .sup-banner__lead { max-width: 42rem; line-height: 1.5; color: rgba(255, 255, 255, 0.76); }
.sup-banner--premium .sup-banner__cta { min-height: 48px; border-radius: 999px; background: #f5b800; color: #0f1c48; box-shadow: 0 8px 20px rgba(245, 184, 0, 0.25); }
.sup-banner--premium .sup-banner__cta:hover { background: #ffca28; box-shadow: 0 11px 24px rgba(245, 184, 0, 0.38); }
.sup-banner--premium .sup-banner__cta:focus-visible { outline: 3px solid #fff; outline-offset: 3px; }

@media (max-width: 820px) {
    .sup-banner__inner {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .sup-banner__cta {
        align-self: stretch;
        justify-content: center;
    }
    .sup-banner--premium .sup-banner__card { padding: 1.4rem 1.25rem; }
    .sup-banner--premium .sup-banner__geometry { width: 120px; opacity: 0.34; }
}
</style>
