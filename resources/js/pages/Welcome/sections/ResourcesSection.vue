<template>
    <section class="resources" id="resources">
        <div class="resources__texture" aria-hidden="true"></div>

        <div class="resources__inner">
            <div class="section-header">
                <span class="eyebrow">DIGITAL RESOURCES</span>
                <h2 class="section-title">
                    Everything you need,<br />
                    <span class="section-title__accent">in one resource center.</span>
                </h2>
                <p class="section-sub">Access key references, templates, and forms that support your development journey.</p>
            </div>

            <div class="resources__grid">
                <div v-for="item in resources" :key="item.title" class="resource-card">
                    <div class="resource-card__icon" :style="{ background: item.color + '1A', color: item.color }">
                        <component :is="item.icon" :size="22" />
                    </div>
                    <h3 class="resource-card__title">{{ item.title }}</h3>
                    <p class="resource-card__desc">{{ item.desc }}</p>
                    <span class="resource-card__action">
                        Access Resource
                        <ArrowRight :size="14" :style="{ color: item.color }" />
                    </span>
                </div>
            </div>

            <div class="resources__divider" aria-hidden="true"></div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { ArrowRight, BookOpen, FileSignature, FileStack, ScrollText } from 'lucide-vue-next';

const resources = [
    { icon: ScrollText, title: 'Circulars', desc: 'Official TDI issuances and memoranda.', color: '#3B5BDB' },
    { icon: BookOpen, title: 'Training Materials', desc: 'Modules, slides, and reference readings.', color: '#0CA678' },
    { icon: FileStack, title: 'Templates', desc: 'Pre-formatted documents for reports.', color: '#4C6EF5' },
    { icon: FileSignature, title: 'Application Forms', desc: 'Forms for programs and scholarships.', color: '#F59F00' },
];
</script>

<style scoped>
.resources {
    position: relative;
    padding: 5rem 2rem;
    background: #f4f6fb;
    overflow: hidden;
}

/* Faint institutional grid texture — purely decorative, fades toward the
   edges so it reads as depth rather than a busy pattern. */
.resources__texture {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(29, 63, 196, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(29, 63, 196, 0.05) 1px, transparent 1px);
    background-size: 48px 48px;
    -webkit-mask-image: radial-gradient(ellipse at center, black 0%, transparent 72%);
    mask-image: radial-gradient(ellipse at center, black 0%, transparent 72%);
    pointer-events: none;
}

.resources__inner {
    position: relative;
    z-index: 1;
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

/* ===================== GRID ===================== */

.resources__grid {
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
}

/* ===================== CARD ===================== */

.resource-card {
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid #eef0f6;
    border-radius: 16px;
    padding: 1.75rem;
    cursor: pointer;
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        border-color 0.25s ease;
}
.resource-card:hover,
.resource-card:focus-visible {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(15, 28, 72, 0.08);
    border-color: #dbe2f7;
}
.resource-card:focus-visible {
    outline: 2px solid #1d3fc4;
    outline-offset: 2px;
}

.resource-card__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.1rem;
    transition: transform 0.25s ease;
}
.resource-card:hover .resource-card__icon {
    transform: translateY(-1px) scale(1.05);
}

.resource-card__title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1a2744;
    margin-bottom: 0.4rem;
}
.resource-card__desc {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.55;
    flex: 1;
    margin-bottom: 1.1rem;
}

.resource-card__action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #1a2744;
    padding-top: 0.9rem;
    border-top: 1px solid #f1f3f9;
}
.resource-card__action svg {
    transition: transform 0.25s ease;
}
.resource-card:hover .resource-card__action svg {
    transform: translateX(3px);
}

/* Thin institutional divider — signals a deliberate close to the section
   before the CTA banner beneath it. */
.resources__divider {
    width: 64px;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, #1d3fc4, #0ca678);
    margin: 3.5rem auto 0;
    opacity: 0.55;
}

@media (max-width: 1024px) {
    .resources__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 480px) {
    .resources {
        padding: 4rem 1.5rem;
    }
    .resources__grid {
        grid-template-columns: 1fr;
    }
}

@media (prefers-reduced-motion: reduce) {
    .resource-card,
    .resource-card__icon,
    .resource-card__action svg {
        transition: none !important;
    }
}
</style>
