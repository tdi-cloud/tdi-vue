<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, CalendarRange, ClipboardCheck, Info, ListChecks, Settings2, ShieldCheck, Sparkles, Trash2, UserCheck, X } from 'lucide-vue-next';
import { ref } from 'vue';

const { confirmDialog } = useConfirm();

/*
 | The Welcome/index page passes the `tna` prop coming from
 | TnaController::bannerData(). If it is null, the parent does not
 | render this component (no TNA Tool / not logged in).
 */
const props = defineProps({
    data: { type: Object, required: true }, // { period, position, submitted }
});

const steps = [
    { icon: UserCheck, title: 'Select Supervisor', desc: 'Search for and select your immediate supervisor.' },
    { icon: ClipboardCheck, title: 'Self-Rating', desc: 'Rate each competency using the scale guide.' },
    { icon: ShieldCheck, title: 'Supervisory Rating', desc: 'Your supervisor reviews your rating.' },
];

const showInfoModal = ref(false);

async function deleteAssessment() {
    if (!props.data.assessment_id) return;
    if (!(await confirmDialog('Delete your self-rating? This action cannot be undone.'))) return;
    router.delete(route('tna.self-rating.destroy', props.data.assessment_id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <section id="tna" class="tna-banner tna-banner--premium">
        <div class="tna-banner__card">
            <!-- Premium visual accents only; no application state or data is represented here. -->
            <div class="tna-banner__glow" aria-hidden="true"></div>
            <div class="tna-banner__geometry" aria-hidden="true"></div>
            <div class="tna-banner__path" aria-hidden="true"></div>

            <div class="tna-banner__grid">
                <!-- LEFT: message -->
                <div class="tna-banner__intro">
                    <span class="tna-banner__period"> <CalendarRange :size="14" /> TNA Cycle {{ data.period }} </span>

                    <h2 class="tna-banner__title">
                        <span>Training Needs</span> <em>Analysis</em>
                        <button type="button" class="tna-banner__info-btn" aria-label="What's new about this TNA" @click="showInfoModal = true">
                            <Info :size="15" />
                        </button>
                    </h2>

                    <p class="tna-banner__lead">
                        Identify your development needs and help shape your Professional Development Plan. This assessment is conducted every <b>3 years</b>.
                    </p>

                    <div class="tna-banner__position">
                        <span class="tna-banner__position-icon"><UserCheck :size="19" /></span>
                        <span><small>Your Position</small><strong>{{ data.position }}</strong></span>
                    </div>

                    <!-- Not yet submitted -->
                    <template v-if="!data.submitted">
                        <Link :href="route('tna.self-rating')" class="tna-banner__cta">
                            Start Self-Rating
                            <ArrowRight :size="17" />
                        </Link>
                        <p class="tna-banner__note"><ListChecks :size="14" /> Takes about 10–15 minutes to complete.</p>
                    </template>

                    <!-- Submitted -->
                    <template v-else>
                        <div class="tna-banner__done">
                            <ShieldCheck :size="18" />
                            <span v-if="data.reviewed"> Your TNA result for {{ data.period }} is ready. </span>
                            <span v-else> You have submitted your self-rating for {{ data.period }}. </span>
                        </div>

                        <Link v-if="data.reviewed && data.assessment_id" :href="route('tna.result.show', data.assessment_id)" class="tna-banner__cta">
                            View TNA Result
                            <ArrowRight :size="17" />
                        </Link>
                        <a
                            v-else-if="data.assessment_id"
                            :href="route('tna.self-rating.pdf', data.assessment_id)"
                            target="_blank"
                            rel="noopener"
                            class="tna-banner__cta"
                        >
                            View / Print PDF
                            <ArrowRight :size="17" />
                        </a>

                        <div class="tna-banner__subactions">
                            <Link :href="route('tna.self-rating')" class="tna-banner__link"> <Settings2 :size="14" /> Manage </Link>
                            <button v-if="data.assessment_id && !data.reviewed" type="button" class="tna-banner__danger" @click="deleteAssessment">
                                <Trash2 :size="14" /> Delete
                            </button>
                        </div>
                        <p class="tna-banner__note">
                            <span v-if="data.reviewed"> Your supervisor has completed the supervisory rating. </span>
                            <span v-else> Please wait for the supervisory rating from your selected supervisor. </span>
                        </p>
                    </template>
                </div>

                <!-- RIGHT: existing workflow steps, presented as a visual timeline. -->
                <div class="tna-banner__journey">
                    <div class="tna-banner__journey-head"><h3>Your TNA Journey</h3><span>3 steps</span></div>
                    <ol class="tna-banner__steps">
                    <li v-for="(s, i) in steps" :key="i" class="tna-step" :class="{ 'tna-step--done': data.submitted && i < 2, 'tna-step--reviewed': data.reviewed && i === 2 }">
                        <span class="tna-step__num">{{ i + 1 }}</span>
                        <span class="tna-step__icon"><component :is="s.icon" :size="18" /></span>
                        <span class="tna-step__body">
                            <span class="tna-step__title">{{ s.title }}</span>
                            <span class="tna-step__desc">{{ s.desc }}</span>
                        </span>
                    </li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- ===== "What's new" info modal ===== -->
        <Teleport to="body">
            <div v-if="showInfoModal" class="tna-info-overlay" @click.self="showInfoModal = false">
                <div class="tna-info-modal">
                    <button type="button" class="tna-info-modal__close" aria-label="Close" @click="showInfoModal = false">
                        <X :size="18" />
                    </button>

                    <div class="tna-info-modal__badge">
                        <Sparkles :size="20" />
                    </div>

                    <h3 class="tna-info-modal__title">A New, Enhanced TNA Experience</h3>
                    <p class="tna-info-modal__lead">
                        This Training Needs Analysis is an upgraded version, fully built into the system — replacing the old manual/paper-based
                        process with a guided, end-to-end digital workflow.
                    </p>

                    <ul class="tna-info-modal__list">
                        <li>
                            <ClipboardCheck :size="16" />
                            <span>Guided self-rating with a clear competency scale, completed in about 10–15 minutes.</span>
                        </li>
                        <li>
                            <UserCheck :size="16" />
                            <span>Direct routing to your selected supervisor for review — no more manual handoffs.</span>
                        </li>
                        <li>
                            <ShieldCheck :size="16" />
                            <span>Real-time status tracking, from submission to supervisory rating to final result.</span>
                        </li>
                    </ul>

                    <button type="button" class="tna-info-modal__ok" @click="showInfoModal = false">Got it</button>
                </div>
            </div>
        </Teleport>
    </section>
</template>

<style scoped>
.tna-banner {
    padding: 3rem 1.5rem;
    background: #fff;
}
.tna-banner__card {
    position: relative;
    max-width: 1120px;
    margin: 0 auto;
    overflow: hidden;
    border-radius: 24px;
    padding: 2.5rem;
    background: linear-gradient(135deg, #0f1c48 0%, #1a2f6e 55%, #21316b 100%);
    box-shadow: 0 24px 60px rgba(15, 28, 72, 0.28);
}
.tna-banner__glow {
    position: absolute;
    top: -120px;
    right: -80px;
    width: 340px;
    height: 340px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(245, 184, 0, 0.35), transparent 70%);
    filter: blur(10px);
    pointer-events: none;
}
.tna-banner__grid {
    position: relative;
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 2.5rem;
    align-items: center;
}

/* LEFT */
.tna-banner__period {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(245, 184, 0, 0.14);
    border: 1px solid rgba(245, 184, 0, 0.45);
    color: #f5d76e;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    padding: 0.35rem 0.8rem;
    border-radius: 30px;
    text-transform: uppercase;
}
.tna-banner__title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-top: 0.9rem;
    font-size: 2rem;
    line-height: 1.1;
    font-weight: 800;
    color: #fff;
}
.tna-banner__info-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #f5d76e;
    cursor: pointer;
    transition:
        background 0.18s,
        border-color 0.18s,
        transform 0.18s;
}
.tna-banner__info-btn:hover {
    background: rgba(245, 184, 0, 0.25);
    border-color: rgba(245, 184, 0, 0.6);
    transform: scale(1.08);
}
.tna-banner__lead {
    margin-top: 0.85rem;
    font-size: 0.95rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.8);
    max-width: 34rem;
}
.tna-banner__pos {
    color: #f5d76e;
    font-weight: 700;
}

.tna-banner__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.4rem;
    background: #f5b800;
    color: #0f1c48;
    font-weight: 800;
    font-size: 0.9rem;
    padding: 0.7rem 1.4rem;
    border-radius: 10px;
    text-decoration: none;
    transition:
        transform 0.18s,
        box-shadow 0.18s,
        background 0.18s;
    box-shadow: 0 8px 22px rgba(245, 184, 0, 0.32);
}
.tna-banner__cta:hover {
    transform: translateY(-2px);
    background: #ffca28;
    box-shadow: 0 12px 26px rgba(245, 184, 0, 0.42);
}

.tna-banner__note {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.9rem;
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.6);
}

/* Manage / Delete secondary actions */
.tna-banner__subactions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.9rem;
}
.tna-banner__link,
.tna-banner__danger {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    border: 1px solid rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.9);
    transition:
        background 0.18s,
        border-color 0.18s,
        color 0.18s;
}
.tna-banner__link:hover {
    background: rgba(255, 255, 255, 0.14);
    border-color: rgba(255, 255, 255, 0.5);
}
.tna-banner__danger {
    border-color: rgba(248, 113, 113, 0.5);
    color: #fca5a5;
    background: rgba(248, 113, 113, 0.1);
}
.tna-banner__danger:hover {
    background: rgba(248, 113, 113, 0.2);
    color: #fff;
    border-color: #f87171;
}
.tna-banner__done {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    margin-top: 1.4rem;
    background: rgba(34, 197, 94, 0.16);
    border: 1px solid rgba(34, 197, 94, 0.5);
    color: #a7f3d0;
    font-weight: 700;
    font-size: 0.88rem;
    padding: 0.6rem 1rem;
    border-radius: 10px;
}

/* RIGHT: steps */
.tna-banner__steps {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    list-style: none;
    margin: 0;
    padding: 0;
}
.tna-step {
    position: relative;
    display: grid;
    grid-template-columns: auto auto 1fr;
    align-items: center;
    gap: 0.85rem;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    padding: 0.85rem 1rem;
}
.tna-step--done {
    border-color: rgba(34, 197, 94, 0.4);
    background: rgba(34, 197, 94, 0.1);
}
.tna-step__num {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(245, 184, 0, 0.2);
    color: #f5d76e;
    font-size: 0.72rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}
.tna-step__icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}
.tna-step__body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.tna-step__title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #fff;
}
.tna-step__desc {
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.62);
    line-height: 1.4;
}

@media (max-width: 860px) {
    .tna-banner__card {
        padding: 1.75rem;
    }
    .tna-banner__grid {
        grid-template-columns: 1fr;
        gap: 1.75rem;
    }
    .tna-banner__title {
        font-size: 1.6rem;
    }
}

/* "What's new" info modal */
.tna-info-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 28, 72, 0.55);
    backdrop-filter: blur(2px);
}
.tna-info-modal {
    position: relative;
    width: 100%;
    max-width: 26rem;
    border-radius: 20px;
    padding: 2rem 1.75rem 1.75rem;
    background: linear-gradient(160deg, #0f1c48 0%, #1a2f6e 60%, #21316b 100%);
    box-shadow: 0 24px 60px rgba(15, 28, 72, 0.4);
    color: #fff;
}
.tna-info-modal__close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    border: none;
    color: rgba(255, 255, 255, 0.75);
    cursor: pointer;
    transition:
        background 0.18s,
        color 0.18s;
}
.tna-info-modal__close:hover {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
}

.tna-info-modal__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(245, 184, 0, 0.16);
    border: 1px solid rgba(245, 184, 0, 0.4);
    color: #f5d76e;
    margin-bottom: 0.9rem;
}
.tna-info-modal__title {
    font-size: 1.2rem;
    font-weight: 800;
    color: #fff;
}
.tna-info-modal__lead {
    margin-top: 0.6rem;
    font-size: 0.86rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.75);
}
.tna-info-modal__list {
    list-style: none;
    margin: 1.25rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}
.tna-info-modal__list li {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    font-size: 0.82rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.85);
}
.tna-info-modal__list li svg {
    flex-shrink: 0;
    margin-top: 0.15rem;
    color: #f5d76e;
}

.tna-info-modal__ok {
    display: block;
    width: 100%;
    margin-top: 1.5rem;
    background: #f5b800;
    color: #0f1c48;
    font-weight: 800;
    font-size: 0.88rem;
    padding: 0.65rem 1rem;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition:
        background 0.18s,
        transform 0.18s;
}
.tna-info-modal__ok:hover {
    background: #ffca28;
    transform: translateY(-1px);
}

/* Premium institutional redesign. Remove `tna-banner--premium` from the section to restore the original presentation. */
.tna-banner--premium { padding: 3.5rem 1.5rem; background: #f7f9fd; }
.tna-banner--premium .tna-banner__card {
    max-width: 1440px;
    padding: 2.75rem 3rem;
    border: 1px solid rgba(178, 204, 255, 0.22);
    border-radius: 25px;
    background: linear-gradient(125deg, #06183e 0%, #0c3475 56%, #071d4a 100%);
    box-shadow: 0 22px 45px rgba(10, 32, 79, 0.25), inset 0 1px rgba(255, 255, 255, 0.1);
}
.tna-banner--premium .tna-banner__glow { top: -130px; right: 23%; width: 430px; height: 380px; background: radial-gradient(circle, rgba(245, 184, 0, 0.22), transparent 68%); }
.tna-banner__geometry { position: absolute; inset: 0 auto auto 0; width: 220px; height: 220px; opacity: 0.55; background: linear-gradient(135deg, rgba(245, 184, 0, 0.85), transparent 33%), linear-gradient(135deg, transparent 44%, rgba(45, 101, 180, 0.4) 45% 57%, transparent 58%); clip-path: polygon(0 0, 100% 0, 0 100%); }
.tna-banner__path { position: absolute; right: -12%; bottom: -80%; width: 72%; height: 130%; border: 2px solid rgba(245, 184, 0, 0.86); border-radius: 50% 0 0 0; opacity: 0.8; transform: rotate(-21deg); }
.tna-banner--premium .tna-banner__grid { grid-template-columns: minmax(0, 1.15fr) minmax(390px, 0.9fr); gap: 3rem; align-items: stretch; }
.tna-banner--premium .tna-banner__intro { display: flex; flex-direction: column; align-items: flex-start; justify-content: center; }
.tna-banner--premium .tna-banner__period { padding: 0.4rem 0.8rem; background: rgba(245, 184, 0, 0.12); border-color: #f5b800; color: #ffe18a; }
.tna-banner--premium .tna-banner__title { flex-wrap: wrap; margin-top: 1rem; font-size: clamp(2rem, 3.3vw, 3rem); line-height: 1.03; letter-spacing: -0.045em; }
.tna-banner--premium .tna-banner__title em { color: #f8c646; font-style: normal; }
.tna-banner--premium .tna-banner__info-btn { width: 30px; height: 30px; border-color: #f5b800; color: #f8c646; }
.tna-banner--premium .tna-banner__info-btn:focus-visible, .tna-banner--premium .tna-banner__cta:focus-visible, .tna-banner--premium .tna-banner__link:focus-visible, .tna-banner--premium .tna-banner__danger:focus-visible { outline: 3px solid #fff; outline-offset: 3px; }
.tna-banner--premium .tna-banner__lead { max-width: 31rem; margin-top: 0.8rem; font-size: 1rem; line-height: 1.55; }
.tna-banner__position { display: flex; align-items: center; gap: 0.75rem; width: min(100%, 32rem); margin-top: 1.25rem; padding: 0.75rem 0.9rem; border: 1px solid rgba(172, 206, 255, 0.2); border-radius: 14px; background: rgba(6, 34, 83, 0.55); box-shadow: inset 0 1px rgba(255, 255, 255, 0.06); }
.tna-banner__position-icon { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 50%; background: rgba(9, 53, 118, 0.95); color: #fff; }
.tna-banner__position small, .tna-banner__position strong { display: block; }
.tna-banner__position small { color: #f8c646; font-size: 0.62rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; }
.tna-banner__position strong { margin-top: 0.12rem; color: #fff; font-size: 0.93rem; }
.tna-banner--premium .tna-banner__cta { min-height: 53px; margin-top: 1rem; padding: 0.8rem 1.55rem; border-radius: 999px; font-size: 0.95rem; }
.tna-banner--premium .tna-banner__done { margin-top: 1rem; background: rgba(16, 143, 105, 0.35); border-color: rgba(86, 226, 171, 0.55); color: #dcffee; }
.tna-banner--premium .tna-banner__note { max-width: 29rem; line-height: 1.4; }
.tna-banner--premium .tna-banner__journey { padding: 1.2rem 1.35rem; border: 1px solid rgba(178, 211, 255, 0.6); border-radius: 19px; background: linear-gradient(135deg, rgba(11, 50, 111, 0.72), rgba(3, 24, 66, 0.68)); box-shadow: inset 0 1px rgba(255, 255, 255, 0.12); backdrop-filter: blur(8px); }
.tna-banner__journey-head { display: flex; align-items: baseline; justify-content: space-between; padding-bottom: 0.85rem; border-bottom: 1px solid rgba(191, 214, 255, 0.23); }
.tna-banner__journey-head h3 { margin: 0; color: #fff; font-size: 1.18rem; font-weight: 800; }
.tna-banner__journey-head span { color: #c9ddff; font-size: 0.78rem; }
.tna-banner--premium .tna-banner__steps { gap: 0; margin-top: 0.25rem; }
.tna-banner--premium .tna-step { grid-template-columns: 29px 42px 1fr; gap: 0.75rem; min-height: 77px; padding: 0.7rem 0; border: 0; border-radius: 0; background: transparent; }
.tna-banner--premium .tna-step:not(:last-child)::after { position: absolute; top: 48px; bottom: -4px; left: 14px; width: 1px; background: rgba(182, 211, 255, 0.48); content: ''; }
.tna-banner--premium .tna-step__num { z-index: 1; width: 29px; height: 29px; background: linear-gradient(135deg, #ffe47d, #e8a819); color: #102355; font-size: 0.85rem; box-shadow: 0 0 0 5px rgba(15, 55, 121, 0.8); }
.tna-banner--premium .tna-step__icon { width: 42px; height: 42px; border: 1px solid rgba(181, 211, 255, 0.35); border-radius: 50%; background: rgba(8, 45, 105, 0.75); }
.tna-banner--premium .tna-step__title { font-size: 0.9rem; }.tna-banner--premium .tna-step__desc { margin-top: 0.12rem; font-size: 0.74rem; }
.tna-banner--premium .tna-step--done, .tna-banner--premium .tna-step--reviewed { background: transparent; }
.tna-banner--premium .tna-step--done .tna-step__num, .tna-banner--premium .tna-step--reviewed .tna-step__num { background: #69d7a0; color: #073c38; }
.tna-banner--premium .tna-step--done .tna-step__icon, .tna-banner--premium .tna-step--reviewed .tna-step__icon { border-color: rgba(105, 215, 160, 0.75); color: #9ef1c1; }

@media (max-width: 1023px) { .tna-banner--premium .tna-banner__card { padding: 2rem; }.tna-banner--premium .tna-banner__grid { grid-template-columns: 1fr; gap: 1.75rem; }.tna-banner--premium .tna-banner__journey { max-width: 640px; } }
@media (max-width: 520px) { .tna-banner--premium { padding: 2.25rem 1rem; }.tna-banner--premium .tna-banner__card { padding: 1.5rem 1.15rem; border-radius: 18px; }.tna-banner--premium .tna-banner__title { font-size: 2rem; }.tna-banner--premium .tna-banner__cta { width: 100%; justify-content: center; }.tna-banner__geometry { width: 130px; height: 130px; }.tna-banner--premium .tna-banner__journey { padding: 1rem; }.tna-banner--premium .tna-step { grid-template-columns: 26px 36px 1fr; gap: 0.55rem; }.tna-banner--premium .tna-step__icon { width: 36px; height: 36px; }.tna-banner--premium .tna-step:not(:last-child)::after { left: 13px; }.tna-banner--premium .tna-step__desc { font-size: 0.69rem; } }
</style>
