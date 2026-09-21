<template>
    <section class="panels">
        <div class="panels__decor" aria-hidden="true">
            <span class="panels__blob panels__blob--a"></span>
            <span class="panels__blob panels__blob--b"></span>
            <span class="panels__dots"></span>
        </div>

        <div class="section-header">
            <span class="eyebrow">Learning Modalities</span>
            <h2 class="section-title">Flexible Learning for a <span class="section-title__accent">Brighter Future</span></h2>
            <p class="section-sub">
                We offer a variety of learning approaches designed to meet the needs of today's public servants —
                <strong>anytime, anywhere, and at every stage of your career.</strong>
            </p>
        </div>

        <div class="panels__grid">
            <div
                v-for="(panel, i) in panels"
                :key="i"
                class="panel"
                :class="{ 'panel--visible': panelVisible[i] }"
                :style="{ transitionDelay: `${i * 0.12}s` }"
                :data-index="i"
                ref="panelRefs"
            >
                <div class="panel__img-wrap">
                    <img :src="panel.img" :alt="panel.title" class="panel__img" loading="lazy" />
                    <div class="panel__gradient" :class="`panel__gradient--${panel.accent}`"></div>
                </div>
                <div class="panel__caption">
                    <div class="panel__icon-badge" :class="`panel__icon-badge--${panel.accent}`">
                        <component :is="panel.icon" :size="20" />
                    </div>
                    <h3 class="panel__title">{{ panel.title }}</h3>
                    <p class="panel__desc">{{ panel.desc }}</p>
                    <span class="panel__arrow" :class="`panel__arrow--${panel.accent}`" aria-hidden="true">
                        <ArrowRight :size="14" />
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { ArrowRight, Handshake, Laptop, Presentation, Trophy } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    images: { type: Object, default: () => ({}) },
});

const panelMeta = [
    {
        key: 'panel_classroom',
        fallback: 'storage/hero/cpsc.JPG',
        title: 'Classroom Training',
        desc: 'Face-to-face learning sessions led by expert facilitators.',
        icon: Presentation,
        accent: 'blue',
    },
    {
        key: 'panel_coaching',
        fallback: 'https://images.unsplash.com/photo-1491975474562-1f4e30bc9468?w=600&q=80',
        title: 'Coaching & Mentoring',
        desc: 'One-on-one guidance from experienced public servants.',
        icon: Handshake,
        accent: 'teal',
    },
    {
        key: 'panel_online',
        fallback: 'https://images.unsplash.com/photo-1506784983877-45594efa4cbe?w=600&q=80',
        title: 'Online & Blended',
        desc: 'Flexible digital learning accessible anytime, anywhere.',
        icon: Laptop,
        accent: 'purple',
    },
    {
        key: 'panel_leadership',
        fallback: '/storage/hero/ethno.jpeg',
        title: 'Leadership Programs',
        desc: 'Build the next generation of public sector leaders.',
        icon: Trophy,
        accent: 'gold',
    },
];

const panels = computed(() =>
    panelMeta.map((p) => ({
        img: props.images[p.key] ?? p.fallback,
        title: p.title,
        desc: p.desc,
        icon: p.icon,
        accent: p.accent,
    })),
);

const panelVisible = ref(panelMeta.map(() => false));
const panelRefs = ref([]);
let observer = null;

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    panelMeta.forEach((_, i) => {
                        setTimeout(() => {
                            panelVisible.value[i] = true;
                        }, i * 130); // staggered reveal, ~130ms per panel
                    });
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15 },
    );

    const section = panelRefs.value[0]?.closest('section');
    if (section) observer.observe(section);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<style scoped>
.panels {
    position: relative;
    padding: 5rem 2rem;
    max-width: 1200px;
    margin: 0 auto;
    overflow: hidden;
}

/* ===================== DECORATIVE BACKGROUND ===================== */

.panels__decor {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
}
.panels__blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(50px);
    opacity: 0.5;
}
.panels__blob--a {
    top: -60px;
    left: -80px;
    width: 260px;
    height: 260px;
    background: radial-gradient(circle, #dbeafe 0%, rgba(219, 234, 254, 0) 70%);
}
.panels__blob--b {
    bottom: -80px;
    right: -60px;
    width: 320px;
    height: 320px;
    background: radial-gradient(circle, #e0e7ff 0%, rgba(224, 231, 255, 0) 70%);
}
.panels__dots {
    position: absolute;
    top: 30px;
    right: 40px;
    width: 90px;
    height: 90px;
    background-image: radial-gradient(#bfdbfe 1.5px, transparent 1.5px);
    background-size: 14px 14px;
    opacity: 0.5;
}

/* ===================== HEADER ===================== */

.section-header {
    position: relative;
    z-index: 1;
    text-align: center;
    max-width: 680px;
    margin: 0 auto 3rem;
}
.eyebrow {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #1d3fc4;
    background: #eaf0ff;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    margin-bottom: 1rem;
}
.section-title {
    font-size: clamp(1.6rem, 3.2vw, 2.5rem);
    font-weight: 800;
    color: #1a2744;
    line-height: 1.2;
    margin-bottom: 1rem;
}
.section-title__accent {
    background: linear-gradient(90deg, #1d3fc4, #3b82f6);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.section-sub {
    color: #6b7280;
    line-height: 1.65;
    font-size: 0.95rem;
}
.section-sub strong {
    color: #374357;
    font-weight: 600;
}

/* ===================== GRID ===================== */

.panels__grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
}

.panel {
    border-radius: 22px;
    overflow: hidden;
    position: relative;
    aspect-ratio: 3 / 4;
    max-height: 400px;
    box-shadow: 0 10px 30px rgba(15, 28, 72, 0.1);

    /* Hidden state */
    opacity: 0;
    transform: translateY(24px);

    transition:
        opacity 0.6s ease,
        transform 0.6s ease,
        box-shadow 0.35s ease;
}

.panel--visible {
    opacity: 1;
    transform: translateY(0);
}

.panel:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 40px rgba(15, 28, 72, 0.16);
}

.panel:hover .panel__img {
    transform: scale(1.06);
}

.panel__img-wrap {
    position: absolute;
    inset: 0;
    overflow: hidden;
}
.panel__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.panel__gradient {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(10, 21, 60, 0.88) 0%, rgba(10, 21, 60, 0.25) 55%, rgba(10, 21, 60, 0.05) 100%);
}
.panel__gradient--blue {
    background: linear-gradient(to top, rgba(12, 32, 92, 0.9) 0%, rgba(29, 63, 196, 0.18) 55%, rgba(29, 63, 196, 0.02) 100%);
}
.panel__gradient--teal {
    background: linear-gradient(to top, rgba(6, 46, 43, 0.9) 0%, rgba(12, 166, 120, 0.16) 55%, rgba(12, 166, 120, 0.02) 100%);
}
.panel__gradient--purple {
    background: linear-gradient(to top, rgba(35, 20, 74, 0.9) 0%, rgba(124, 58, 237, 0.18) 55%, rgba(124, 58, 237, 0.02) 100%);
}
.panel__gradient--gold {
    background: linear-gradient(to top, rgba(58, 36, 4, 0.9) 0%, rgba(230, 119, 0, 0.18) 55%, rgba(230, 119, 0, 0.02) 100%);
}

.panel__caption {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.35rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.4rem;
}

.panel__icon-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: #fff;
    margin-bottom: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.35);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    transition: transform 0.3s ease;
}
.panel:hover .panel__icon-badge {
    transform: translateY(-2px) scale(1.05);
}
.panel__icon-badge--blue {
    background: rgba(29, 63, 196, 0.85);
}
.panel__icon-badge--teal {
    background: rgba(12, 166, 120, 0.85);
}
.panel__icon-badge--purple {
    background: rgba(124, 58, 237, 0.85);
}
.panel__icon-badge--gold {
    background: rgba(230, 119, 0, 0.85);
}

.panel__title {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.25;
}
.panel__desc {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.78);
    line-height: 1.5;
    max-width: 90%;
}

.panel__arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1.5px solid rgba(255, 255, 255, 0.55);
    color: #fff;
    margin-top: 0.4rem;
    transition:
        background 0.3s ease,
        color 0.3s ease;
}
.panel__arrow--blue {
    color: #dbe4ff;
}
.panel__arrow--teal {
    color: #d3f9ee;
}
.panel__arrow--purple {
    color: #ece2ff;
}
.panel__arrow--gold {
    color: #ffe9cc;
}
.panel:hover .panel__arrow {
    color: #fff;
    border-color: transparent;
}
.panel:hover .panel__arrow--blue {
    background: #1d3fc4;
}
.panel:hover .panel__arrow--teal {
    background: #0ca678;
}
.panel:hover .panel__arrow--purple {
    background: #7c3aed;
}
.panel:hover .panel__arrow--gold {
    background: #e67700;
}

/* ===================== RESPONSIVE ===================== */

@media (max-width: 1024px) {
    .panels__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 640px) {
    .panels {
        padding: 3.5rem 1.25rem;
    }
    .panels__grid {
        grid-template-columns: 1fr;
    }
    .panel {
        aspect-ratio: 4 / 3;
        max-height: 280px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .panel,
    .panel__img,
    .panel__icon-badge,
    .panel__arrow {
        transition: none !important;
    }
}
</style>
