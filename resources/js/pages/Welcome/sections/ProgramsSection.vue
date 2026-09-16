<template>
    <section class="programs" id="programs">
        <div class="section-header">
            <span class="eyebrow">LEARNING PROGRAMS</span>
            <h2 class="section-title">Development opportunities for<br />every stage of your journey.</h2>
            <p class="section-sub">
                Explore learning experiences designed to support technical excellence, leadership readiness, and lifelong growth.
            </p>
        </div>

        <div class="programs__layout">
            <article v-for="card in programs" :key="card.title" class="program-card">
                <span class="program-card__stage">{{ card.stage }}</span>
                <div class="program-card__icon" :class="card.iconClass">
                    <component :is="card.icon" :size="22" />
                </div>
                <h3>{{ card.title }}</h3>
                <p>{{ card.desc }}</p>
                <ul>
                    <li v-for="item in card.items" :key="item">{{ item }}</li>
                </ul>
                <span class="program-card__tag">{{ card.items.length }} learning tracks</span>
            </article>
        </div>
    </section>
</template>

<script setup lang="ts">
import { Briefcase, GraduationCap, Presentation } from 'lucide-vue-next';

const programs = [
    {
        stage: 'STAGE 01',
        icon: GraduationCap,
        iconClass: 'icon--blue',
        title: 'Degree Programs',
        desc: 'Advance academically through scholarship-supported higher education pathways.',
        items: ['Masteral Programs', 'Doctoral Programs', 'Scholarship Support'],
    },
    {
        stage: 'STAGE 02',
        icon: Presentation,
        iconClass: 'icon--teal',
        title: 'Professional Development',
        desc: 'Stay current through practical and relevant development experiences.',
        items: ['Seminars & Workshops', 'Conferences', 'Learning Forums'],
    },
    {
        stage: 'STAGE 03',
        icon: Briefcase,
        iconClass: 'icon--gold',
        title: 'Workplace Learning',
        desc: 'Grow through meaningful experiences embedded in everyday work.',
        items: ['Coaching & Mentoring', 'Job Rotation', 'Stretch Assignments'],
    },
];
</script>

<style scoped>
.programs {
    padding: 5rem 2rem;
    background: #f4f6fb;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
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
    color: #1a2744;
    line-height: 1.2;
    margin-bottom: 1rem;
}
.section-sub {
    color: #6b7280;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.65;
}

/* ===================== LAYOUT ===================== */

.programs__layout {
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    align-items: stretch;
}

/* ===================== CARD ===================== */

.program-card {
    position: relative;
    background: #fff;
    border: 1px solid #eef0f6;
    border-radius: 18px;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    transition:
        box-shadow 0.25s ease,
        transform 0.25s ease,
        border-color 0.25s ease;
}
.program-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 34px rgba(15, 28, 72, 0.09);
    border-color: #dbe2f7;
}

.program-card__stage {
    display: block;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #9aa3bd;
    text-transform: uppercase;
    margin-bottom: 1rem;
}

.program-card__icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
    transition: transform 0.25s ease;
}
.program-card:hover .program-card__icon {
    transform: translateY(-2px) scale(1.06);
}
.icon--blue {
    background: #eef2ff;
    color: #1d3fc4;
}
.icon--teal {
    background: #e6fcf5;
    color: #0ca678;
}
.icon--gold {
    background: #fff9db;
    color: #e67700;
}

.program-card h3 {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1a2744;
    margin-bottom: 0.5rem;
}
.program-card p {
    font-size: 0.85rem;
    color: #6b7280;
    line-height: 1.6;
    margin-bottom: 1rem;
}
.program-card ul {
    list-style: none;
    padding: 0;
    flex: 1;
}
.program-card ul li {
    font-size: 0.82rem;
    color: #4b5563;
    padding: 0.2rem 0;
}
.program-card ul li::before {
    content: '• ';
    color: #1d3fc4;
}

.program-card__tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f3f9;
    font-size: 0.72rem;
    font-weight: 700;
    color: #1d3fc4;
    letter-spacing: 0.02em;
}

/* ===================== RESPONSIVE ===================== */

@media (max-width: 1024px) {
    .programs__layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .program-card {
        padding: 1.5rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .program-card,
    .program-card__icon {
        transition: none !important;
    }
}
</style>
