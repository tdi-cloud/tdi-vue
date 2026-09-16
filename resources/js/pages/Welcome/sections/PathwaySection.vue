<template>
    <section ref="sectionEl" class="pathway">
        <div class="section-header">
            <span class="eyebrow">LEARNING PATHWAY</span>
            <h2 class="section-title">
                From learning need to<br />
                <span class="section-title__accent">measurable impact.</span>
            </h2>
            <p class="section-sub">A structured journey that transforms learning into stronger performance and recognition.</p>
        </div>

        <div class="pathway__track">
            <!-- Connecting line — desktop/tablet only; see .pathway__mobile-connector for the stacked layout -->
            <div class="pathway__line" aria-hidden="true">
                <div class="pathway__line-fill" :class="{ 'is-visible': visible }"></div>
            </div>

            <div class="pathway__nodes">
                <template v-for="(step, i) in steps" :key="step.title">
                    <div
                        class="pathway__node"
                        :class="{ 'is-visible': visible, 'pathway__node--final': step.final }"
                        :style="{ transitionDelay: `${i * 0.15}s` }"
                    >
                        <span class="pathway__node-num" :class="{ 'pathway__node-num--final': step.final }">{{ step.num }}</span>
                        <div class="pathway__node-circle" :class="{ 'pathway__node-circle--final': step.final }">
                            <component :is="step.icon" :size="22" />
                        </div>
                        <h3 class="pathway__node-title">{{ step.title }}</h3>
                        <p class="pathway__node-desc">{{ step.desc }}</p>
                    </div>

                    <!-- Mobile-only connector between stacked stages -->
                    <div
                        v-if="!step.final"
                        class="pathway__mobile-connector"
                        :class="{ 'is-visible': visible }"
                        :style="{ transitionDelay: `${i * 0.15 + 0.1}s` }"
                        aria-hidden="true"
                    ></div>
                </template>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { Award, BookOpen, ClipboardList, Search } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const steps = [
    { num: '01', icon: Search, title: 'Assess', desc: 'Identify needs and growth priorities.', final: false },
    { num: '02', icon: ClipboardList, title: 'Plan', desc: 'Map targeted development actions.', final: false },
    { num: '03', icon: BookOpen, title: 'Learn', desc: 'Engage in relevant experiences.', final: false },
    { num: '04', icon: Award, title: 'Achieve', desc: 'Translate learning into impact.', final: true },
];

const sectionEl = ref<HTMLElement | null>(null);
const visible = ref(false);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    visible.value = true;
                    observer?.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.2 },
    );
    if (sectionEl.value) observer.observe(sectionEl.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<style scoped>
.pathway {
    padding: 5rem 2rem;
    background: #fff;
}

.section-header {
    text-align: center;
    margin-bottom: 3.5rem;
}
.eyebrow {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    color: #0ca678;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    display: block;
}
.section-title {
    font-size: clamp(1.6rem, 3vw, 2.5rem);
    font-weight: 800;
    letter-spacing: -0.01em;
    color: #1a2744;
    line-height: 1.2;
    margin-bottom: 1rem;
}
.section-title__accent {
    color: #1d3fc4;
}
.section-sub {
    color: #6b7280;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.65;
}

/* ===================== TRACK ===================== */

.pathway__track {
    position: relative;
    max-width: 1100px;
    margin: 0 auto;
}

/* Desktop/tablet connecting line — spans from the center of the first
   node to the center of the last (each node is an equal-width flex
   column, so 12.5%/87.5% lands exactly on their horizontal centers). */
.pathway__line {
    position: absolute;
    top: 3rem;
    left: 12.5%;
    right: 12.5%;
    height: 2px;
    background: #e2e6f3;
    border-radius: 2px;
    overflow: hidden;
    z-index: 0;
}
.pathway__line-fill {
    position: absolute;
    inset: 0;
    width: 0%;
    background: linear-gradient(90deg, #1d3fc4 0%, #1d3fc4 70%, #e67700 100%);
    transition: width 1.3s cubic-bezier(0.16, 1, 0.3, 1) 0.1s;
}
.pathway__line-fill.is-visible {
    width: 100%;
}

.pathway__mobile-connector {
    display: none;
}

.pathway__nodes {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}

/* ===================== NODE ===================== */

.pathway__node {
    flex: 1 1 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 0 0.5rem;
    cursor: default;

    opacity: 0;
    transform: translateY(18px);
    transition:
        opacity 0.6s ease,
        transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.pathway__node.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.pathway__node-num {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #1d3fc4;
    background: #eef2ff;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    margin-bottom: 0.85rem;
    transition:
        color 0.25s ease,
        background 0.25s ease;
}
.pathway__node-num--final {
    color: #e67700;
    background: #fff9db;
}

.pathway__node-circle {
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 50%;
    background: #eef2ff;
    color: #1d3fc4;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    box-shadow: 0 0 0 6px #fff;
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        background 0.25s ease,
        color 0.25s ease;
}
.pathway__node:hover .pathway__node-circle {
    transform: translateY(-3px) scale(1.05);
    box-shadow:
        0 0 0 6px #fff,
        0 10px 22px rgba(29, 63, 196, 0.22);
}
.pathway__node-circle--final {
    background: #fff9db;
    color: #e67700;
}
.pathway__node--final:hover .pathway__node-circle {
    box-shadow:
        0 0 0 6px #fff,
        0 10px 22px rgba(230, 119, 0, 0.22);
}

.pathway__node-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1a2744;
    margin-bottom: 0.5rem;
}
.pathway__node--final .pathway__node-title {
    font-weight: 800;
}

.pathway__node-desc {
    font-size: 0.82rem;
    color: #6b7280;
    line-height: 1.6;
    max-width: 12.5rem;
    margin: 0 auto;
}

/* ===================== RESPONSIVE ===================== */

@media (max-width: 900px) {
    .pathway__line {
        display: none;
    }

    .pathway__nodes {
        flex-direction: column;
        align-items: center;
        gap: 0;
    }

    .pathway__node {
        flex: 0 0 auto;
        width: 100%;
        max-width: 22rem;
        padding: 0;
    }

    .pathway__mobile-connector {
        display: block;
        width: 2px;
        height: 2.25rem;
        margin: 0 auto;
        background: linear-gradient(#1d3fc4, #c7cde3);
        opacity: 0;
        transform: scaleY(0);
        transform-origin: top;
        transition:
            opacity 0.5s ease,
            transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .pathway__mobile-connector.is-visible {
        opacity: 1;
        transform: scaleY(1);
    }
}

@media (max-width: 480px) {
    .pathway {
        padding: 4rem 1.5rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pathway__node,
    .pathway__line-fill,
    .pathway__mobile-connector {
        transition: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
    .pathway__line-fill {
        width: 100% !important;
    }
}
</style>
